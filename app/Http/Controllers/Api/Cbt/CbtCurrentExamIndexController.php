<?php

namespace App\Http\Controllers\Api\Cbt;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Models\TeacherAssignment;
use App\Models\Term;
use Illuminate\Support\Facades\Auth;

class CbtCurrentExamIndexController extends Controller
{
    public function __invoke()
    {
        $user = Auth::user();
        $term = Term::where('school_id', $user->school_id)
            ->whereHas('academicSession', fn ($q) => $q->where('is_current', true))
            ->first();

        abort_unless($term, 409, 'No current academic term is configured for this school.');

        $query = CbtExam::with(['subject', 'term.academicSession', 'schoolClasses', 'questions.options'])
            ->withCount('attempts')
            ->where('school_id', $user->school_id)
            ->where('term_id', $term->id)
            ->latest();

        if ($user->hasRole('teacher')) {
            $assignments = TeacherAssignment::where('school_id', $user->school_id)
                ->where('user_id', $user->id)
                ->get();
            $classIds = $assignments->pluck('school_class_id')->unique();
            $subjectIds = $assignments->whereNotNull('subject_id')->pluck('subject_id')->unique();
            $classTeacherIds = $assignments->where('is_class_teacher', true)->pluck('school_class_id')->unique();

            $query->whereHas('schoolClasses', fn ($c) => $c->whereIn('school_classes.id', $classIds))
                ->where(function ($q) use ($subjectIds, $classTeacherIds) {
                    $q->whereIn('subject_id', $subjectIds)
                        ->orWhereHas('schoolClasses', fn ($c) => $c->whereIn('school_classes.id', $classTeacherIds));
                });
        }

        return $query->get();
    }
}
