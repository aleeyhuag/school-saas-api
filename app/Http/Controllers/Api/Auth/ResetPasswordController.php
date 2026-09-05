<?php
namespace App\Http\Controllers\Api\Auth;
use App\Http\Controllers\Controller; use App\Services\AccountActionTokenService; use Illuminate\Support\Facades\Hash; use Illuminate\Validation\ValidationException;
class ResetPasswordController extends Controller { public function __invoke(AccountActionTokenService $tokens){$v=request()->validate(['token'=>['required','string'],'user_id'=>['required','integer'],'password'=>['required','string','min:8','confirmed']]); $purpose=request()->boolean('setup') ? 'account_setup' : 'password_reset'; $user=$tokens->consume($v['token'],$purpose,(int)$v['user_id']); $user->update(['password'=>Hash::make($v['password'])]); $user->tokens()->delete(); return response()->json(['message'=>'Password reset successfully. You can now log in.']);}}
