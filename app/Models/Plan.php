<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'amount_kobo', 'duration_months', 'billing_interval', 'paystack_plan_code', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function getAmountNairaAttribute(): float
    {
        return $this->amount_kobo / 100;
    }
}
