<?php
namespace App\Http\Controllers\Api\Auth;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Services\AuditLogService;
use App\Services\SubscriptionService;
use Illuminate\Validation\ValidationException;
class LoginController extends Controller {
    public function __invoke(LoginRequest $request, AuditLogService $auditLogs, SubscriptionService $subscriptionService) {
        $v=$request->validated(); $email=strtolower(trim($v['email']));
        $candidates=User::where('email',$email)->with('school')->get();
        $matches=$candidates->filter(fn($u)=>Hash::check($v['password'],$u->password));
        if($v['school_id'] ?? null) $matches=$matches->where('school_id',(int)$v['school_id']);
        if($matches->count() > 1) {
            return response()->json(['message'=>'This email belongs to more than one school. Select the school you want to enter.','school_choices'=>$matches->map(fn($u)=>['id'=>$u->school_id,'name'=>$u->school?->name])->values()],409);
        }
        $user=$matches->first();
        if(!$user) throw ValidationException::withMessages(['email'=>['The provided credentials are incorrect.']]);
        if($user->status==='disabled') throw ValidationException::withMessages(['email'=>['This account has been disabled. Contact your school admin.']]);
        if($user->status==='pending') throw ValidationException::withMessages(['email'=>['This account is awaiting approval from your school admin.']]);
        $school=null; $isBillingLock=false;
        if($user->school_id){
            $school=School::whereKey($user->school_id)->select('id','is_active','deactivation_reason')->first();
            if($school) $school=$subscriptionService->enforceLiveExpiry($school);
            $billingReasons=['trial_expired','subscription_expired']; $canManageBilling=$user->hasRole('proprietor')||$user->hasRole('principal');
            $isBillingLock=$school && in_array($school->deactivation_reason,$billingReasons,true);
            $isBlocked=$school && !$school->is_active && !($canManageBilling&&$isBillingLock);
            if($isBlocked && $user->hasRole('proprietor')){
                $activeBranchId=$user->accessibleSchools()->where('schools.id','!=',$school->id)->where('schools.is_active',true)->value('schools.id');
                if($activeBranchId){$user->update(['school_id'=>$activeBranchId]);$school=School::find($activeBranchId);$isBlocked=false;}
            }
            if($isBlocked){$messages=['trial_expired'=>'Your free trial has ended. Subscribe to keep access.','subscription_expired'=>'Your subscription has lapsed. Renew to regain access.']; throw ValidationException::withMessages(['email'=>[$messages[$school->deactivation_reason]??'Your school has been disabled. Please contact your proprietor or principal.']]);}
        }
        $token=$user->createToken($v['device_name']??$request->userAgent()??'unknown-device')->plainTextToken;
        if($user->school_id) $auditLogs->record($request,'login','User logged in successfully.',['role'=>$user->getRoleNames()->values()->all()]);
        return response()->json(['user'=>$user->only(['id','name','email','school_id','status']),'roles'=>$user->getRoleNames(),'token'=>$token,'billing_locked'=>(bool)$isBillingLock]);
    }
}
