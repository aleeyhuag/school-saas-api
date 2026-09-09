<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class ReferralPartner extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'referral_code', 'status', 'notes',
        'bank_name', 'account_name', 'account_number',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function ($partner) {
            if (! $partner->referral_code) {
                $partner->referral_code = static::generateUniqueCode();
            }
        });
    }

    protected static function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function referredSchools(): HasMany
    {
        return $this->hasMany(School::class, 'referred_by_partner_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(ReferralCommission::class);
    }
}
