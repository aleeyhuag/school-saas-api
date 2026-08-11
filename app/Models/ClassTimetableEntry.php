<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class ClassTimetableEntry extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'school_class_id', 'subject_id', 'user_id',
        'day_of_week', 'period', 'start_time', 'end_time',
        'room', 'academic_session_id',
    ];

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function academicSession()
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * Human-readable day name, derived from the stored integer.
     */
    public function getDayNameAttribute(): string
    {
        return ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'][$this->day_of_week] ?? '';
    }
}
