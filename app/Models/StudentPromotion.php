<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentPromotion extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'student_id', 'from_academic_session_id', 'to_academic_session_id',
        'from_school_class_id', 'to_school_class_id', 'action', 'note', 'performed_by',
    ];

    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function fromSession(): BelongsTo { return $this->belongsTo(AcademicSession::class, 'from_academic_session_id'); }
    public function toSession(): BelongsTo { return $this->belongsTo(AcademicSession::class, 'to_academic_session_id'); }
    public function fromClass(): BelongsTo { return $this->belongsTo(SchoolClass::class, 'from_school_class_id'); }
    public function toClass(): BelongsTo { return $this->belongsTo(SchoolClass::class, 'to_school_class_id'); }
    public function performedBy(): BelongsTo { return $this->belongsTo(User::class, 'performed_by'); }
}
