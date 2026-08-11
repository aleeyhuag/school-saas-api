<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'posted_by', 'title', 'body',
        'audience', 'school_class_id', 'include_parents', 'notify_by_email',
    ];

    protected $casts = [
        'include_parents' => 'boolean',
        'notify_by_email' => 'boolean',
    ];

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }

    /**
     * The single source of truth for "can this user see this
     * announcement" — used both to build a user's feed and (in the
     * notification fan-out) to decide who gets emailed. Keeping it
     * as one query scope means the feed and the email list can never
     * quietly disagree with each other.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('audience', 'whole_school');

            if ($user->hasAnyRole(['proprietor', 'principal', 'bursar', 'exam_officer', 'teacher'])) {
                $q->orWhere('audience', 'staff_only');
            }

            if ($user->hasRole('student') && $user->student) {
                $q->orWhere(function (Builder $q2) use ($user) {
                    $q2->where('audience', 'class')
                        ->where('school_class_id', $user->student->school_class_id);
                });
            }

            if ($user->hasRole('parent')) {
                $childClassIds = $user->children()->pluck('students.school_class_id')->unique()->values();
                if ($childClassIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $q2) use ($childClassIds) {
                        $q2->where('audience', 'class')
                            ->where('include_parents', true)
                            ->whereIn('school_class_id', $childClassIds);
                    });
                }
            }

            if ($user->hasRole('teacher')) {
                $classTeacherClassIds = TeacherAssignment::where('user_id', $user->id)
                    ->where('is_class_teacher', true)
                    ->pluck('school_class_id');

                if ($classTeacherClassIds->isNotEmpty()) {
                    $q->orWhere(function (Builder $q2) use ($classTeacherClassIds) {
                        $q2->where('audience', 'class')
                            ->whereIn('school_class_id', $classTeacherClassIds);
                    });
                }
            }
        });
    }
}
