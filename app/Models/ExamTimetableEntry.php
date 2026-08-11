<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class ExamTimetableEntry extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'term_id', 'subject_id',
        'exam_date', 'start_time', 'end_time',
        'venue', 'notes',
    ];

    protected $casts = [
        'exam_date' => 'date',
    ];

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function term()
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * The classes that sit this exam.
     */
    public function schoolClasses()
    {
        return $this->belongsToMany(SchoolClass::class, 'exam_timetable_classes');
    }
}
