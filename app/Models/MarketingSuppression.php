<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingSuppression extends Model
{
    protected $fillable = ['email', 'unsubscribed_at'];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];
}
