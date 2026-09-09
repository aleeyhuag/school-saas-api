<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use App\Models\MarketingSuppression;
use App\Models\PlatformLead;
use App\Models\User;
use App\Services\MarketingCampaignDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformCampaignController extends Controller
{
    public function index() { return response()->json(EmailCampaign::latest()->get()); }

    public function show(EmailCampaign $emailCampaign)
    {
        return response()->json($emailCampaign->loadCount(['recipients' => fn ($q) => $q->where('status', 'sent')])->load('recipients'));
    }

    public function store(Request $request, MarketingCampaignDispatcher $dispatcher)
    {
        $v = $request->validate(['name'=>['required','string','max:255'],'subject'=>['required','string','max:255'],'html_body'=>['required','string','max:500000'],'audience'=>['required','in:proprietors,principals,both,leads,all']]);
        $campaign = DB::transaction(function () use ($v) {
            $c = EmailCampaign::create([...$v, 'status'=>'queued']);
            $recipients=[];
            if (in_array($v['audience'], ['proprietors','principals','both','all'], true)) {
                $roles=in_array($v['audience'], ['both','all'], true)?['proprietor','principal']:[$v['audience']==='proprietors'?'proprietor':'principal'];
                User::with('school:id,name')->where('status','approved')->whereHas('roles',fn($q)=>$q->whereIn('name',$roles))->get(['id','email','name','school_id'])->each(function($u)use(&$recipients){if($u->email)$recipients[strtolower($u->email)]=['email'=>strtolower($u->email),'name'=>$u->name,'school_name'=>$u->school?->name];});
            }
            if (in_array($v['audience'], ['leads','all'], true)) PlatformLead::where('unsubscribed',false)->get()->each(fn($l)=>$recipients[strtolower($l->email)]=['email'=>strtolower($l->email),'name'=>$l->name,'school_name'=>$l->school_name]);
            foreach($recipients as $r){if(MarketingSuppression::where('email',$r['email'])->exists())continue;EmailCampaignRecipient::create(['email_campaign_id'=>$c->id,...$r]);}
            $c->update(['total_count'=>$c->recipients()->count()]); return $c;
        });
        // Start immediately; subsequent batches are released every five minutes by the scheduler.
        $dispatcher->release(10);
        return response()->json($campaign->fresh(),201);
    }

    public function cancel(EmailCampaign $emailCampaign)
    {
        if(in_array($emailCampaign->status,['completed','cancelled'],true)) throw ValidationException::withMessages(['campaign'=>['This campaign can no longer be cancelled.']]);
        $emailCampaign->update(['status'=>'cancelled','cancelled_at'=>now()]);
        $emailCampaign->recipients()->whereIn('status',['pending','queued','sending'])->update(['status'=>'cancelled']);
        return response()->json($emailCampaign);
    }
}
