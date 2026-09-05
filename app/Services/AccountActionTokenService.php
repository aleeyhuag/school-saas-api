<?php
namespace App\Services;
use App\Models\User; use Illuminate\Support\Facades\Hash; use Illuminate\Support\Str; use Illuminate\Validation\ValidationException;
class AccountActionTokenService {
 public function issue(User $user,string $purpose,int $minutes=60): string { \DB::table('account_action_tokens')->where('user_id',$user->id)->where('purpose',$purpose)->delete(); $raw=Str::random(64); \DB::table('account_action_tokens')->insert(['user_id'=>$user->id,'purpose'=>$purpose,'token_hash'=>hash('sha256',$raw),'expires_at'=>now()->addMinutes($minutes),'created_at'=>now(),'updated_at'=>now()]); return $raw; }
 public function consume(string $raw,string $purpose,?int $userId=null): User { $row=\DB::table('account_action_tokens')->where('purpose',$purpose)->where('token_hash',hash('sha256',$raw))->where('expires_at','>',now())->first(); if(!$row || ($userId && (int)$row->user_id!==$userId)) throw ValidationException::withMessages(['token'=>['This link is invalid or has expired.']]); $user=User::find($row->user_id); \DB::table('account_action_tokens')->where('id',$row->id)->delete(); if(!$user) throw ValidationException::withMessages(['token'=>['This link is invalid or has expired.']]); return $user; }
}
