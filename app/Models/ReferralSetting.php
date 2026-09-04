<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $fillable = ['year_one_percentage', 'year_two_percentage', 'enabled'];

    protected $casts = [
        'year_one_percentage' => 'decimal:2',
        'year_two_percentage' => 'decimal:2',
        'enabled' => 'boolean',
    ];

    /**
     * Always exactly one row (seeded by the creating migration). This
     * is the single source of truth for both commission tiers — Super
     * Admin edits it from the billing page, SubscriptionService reads
     * it when a referred school's payment is confirmed.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'year_one_percentage' => 15.00,
            'year_two_percentage' => 5.00,
            'enabled' => true,
        ]);
    }
}
