<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubjectScore extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'term_id', 'school_class_id', 'subject_id', 'student_id',
        'ca_score', 'ca_max', 'assignment_score', 'assignment_max',
        'exam_score', 'exam_max', 'teacher_comment', 'entered_by',
    ];

    protected $casts = [
        'ca_score' => 'float', 'ca_max' => 'float',
        'assignment_score' => 'float', 'assignment_max' => 'float',
        'exam_score' => 'float', 'exam_max' => 'float',
    ];

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
