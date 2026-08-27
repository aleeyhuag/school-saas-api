<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtExam extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = [
        'school_id', 'term_id', 'subject_id', 'title', 'instructions',
        'duration_minutes', 'starts_at', 'ends_at', 'pass_mark',
        'randomize_questions', 'randomize_options', 'published',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'pass_mark' => 'decimal:2',
        'randomize_questions' => 'boolean',
        'randomize_options' => 'boolean',
        'published' => 'boolean',
    ];

    public function term(): BelongsTo { return $this->belongsTo(Term::class); }
    public function subject(): BelongsTo { return $this->belongsTo(Subject::class); }
    public function schoolClasses(): BelongsToMany { return $this->belongsToMany(SchoolClass::class, 'cbt_exam_classes'); }
    public function questions(): HasMany { return $this->hasMany(CbtQuestion::class)->orderBy('position'); }
    public function attempts(): HasMany { return $this->hasMany(CbtAttempt::class); }
}
