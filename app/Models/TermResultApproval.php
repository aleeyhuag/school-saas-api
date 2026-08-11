<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TermResultApproval extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'school_class_id', 'term_id', 'approved_by', 'approved_at',
        'locked_by', 'locked_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }
}
