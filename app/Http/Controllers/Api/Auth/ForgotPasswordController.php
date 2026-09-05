<?php
namespace App\Http\Controllers\Api\Auth;
use App\Http\Controllers\Controller; use App\Models\User; use App\Services\AccountActionTokenService; use App\Notifications\PasswordResetNotification;
class ForgotPasswordController extends Controller { public function __invoke(AccountActionTokenService $tokens){request()->validate(['email'=>['required','email']]); $users=User::where('email',strtolower(trim(request('email'))))->get(); foreach($users as $user){ if($user->status==='approved'){try{$user->notify(new PasswordResetNotification($tokens->issue($user,'password_reset')));}catch(\Throwable $e){report($e);}}} return response()->json(['message'=>'If an account exists for that email, a password reset link has been sent.']);}}
