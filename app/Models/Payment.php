<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'school_id', 'subscription_id', 'plan_id', 'amount_kobo', 'method', 'status',
        'reference_code', 'proof_path', 'paystack_reference', 'rejection_reason',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    protected $appends = ['proof_url'];

    public function getProofUrlAttribute(): ?string
    {
        // Signed + time-limited rather than a permanently public
        // /storage/... URL — a bank transfer screenshot shouldn't
        // sit at a guessable, unauthenticated link forever. Served
        // through MediaController, which also avoids the broken-
        // symlink issue (see its docblock).
        if (! $this->proof_path) {
            return null;
        }

        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'media.payment-proof',
            now()->addMinutes(30),
            ['payment' => $this->id]
        );
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
