<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncOperation extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'client_uuid', 'school_id', 'user_id', 'type', 'recorded_at',
        'payload', 'status', 'result', 'conflict_data', 'resolution',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'payload' => 'array',
        'result' => 'array',
        'conflict_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
