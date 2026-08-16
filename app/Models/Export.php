<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * A queued export request (full school backup, class-wide report card
 * bundle, etc). Deliberately NOT using the BelongsToSchool trait —
 * same reasoning as Payment: Super Admin's platform-wide exports have
 * no school context at all, and that global scope blocks everything
 * for a user with school_id = null (see Stage 38's security fix).
 * Controllers scope this manually instead.
 */
class Export extends Model
{
    protected $fillable = [
        'school_id', 'user_id', 'type', 'params', 'status',
        'file_path', 'file_name', 'error_message', 'completed_at',
    ];

    protected $casts = [
        'params' => 'array',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['download_url'];

    public function getDownloadUrlAttribute(): ?string
    {
        if ($this->status !== 'completed' || ! $this->file_path) {
            return null;
        }

        return URL::temporarySignedRoute(
            'exports.download',
            now()->addMinutes(30),
            ['export' => $this->id]
        );
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
