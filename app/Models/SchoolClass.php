<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use BelongsToSchool;

    protected $table = 'school_classes';

    protected $fillable = ['school_id', 'name', 'arm', 'level'];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Display name, e.g. "JSS 1A"
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->name . ' ' . ($this->arm ?? ''));
    }
}
