<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtAttempt extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'cbt_exam_id', 'student_id', 'started_at', 'expires_at',
        'submitted_at', 'status', 'score', 'percentage', 'passed',
        'attempted_count', 'unanswered_count', 'time_used_seconds',
    ];
    protected $casts = [
        'started_at' => 'datetime', 'expires_at' => 'datetime', 'submitted_at' => 'datetime',
        'score' => 'decimal:2', 'percentage' => 'decimal:2', 'passed' => 'boolean',
    ];
    public function exam(): BelongsTo { return $this->belongsTo(CbtExam::class, 'cbt_exam_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function answers(): HasMany { return $this->hasMany(CbtAnswer::class); }
}
