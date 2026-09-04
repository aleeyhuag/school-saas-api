<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnrollmentApplication extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'first_name', 'last_name', 'date_of_birth', 'gender',
        'school_class_id', 'guardian_name', 'guardian_email', 'guardian_phone',
        'payment_proof_path', 'status', 'rejection_reason',
        'reviewed_by', 'reviewed_at', 'created_student_id',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'created_student_id');
    }
}
