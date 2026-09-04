<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReferralPartner extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'referral_code', 'status', 'notes'];

    protected static function booted(): void
    {
        static::creating(function (ReferralPartner $partner) {
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
