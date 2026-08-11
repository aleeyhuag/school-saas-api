<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'user_id', 'school_class_id', 'admission_number',
        'first_name', 'last_name', 'date_of_birth', 'gender',
        'guardian_name', 'guardian_phone', 'status',
    ];

    protected $casts = [
        'status' => 'string',
        'date_of_birth' => 'date',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * Parent/guardian LOGIN ACCOUNTS linked to this student — distinct
     * from the free-text guardian_name/guardian_phone fields, which
     * are just contact info and don't grant any dashboard access.
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'student_guardians');
    }

    public function promotions(): HasMany
    {
        return $this->hasMany(StudentPromotion::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
