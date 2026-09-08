<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use Illuminate\Support\Facades\Auth;

class SubjectController extends Controller
{
    public function index()
    {
        $query = Subject::orderBy('name');

        if (Auth::user()->hasRole('teacher')) {
            $user = Auth::user();
            $assignments = TeacherAssignment::where('school_id', $user->school_id)
                ->where('user_id', $user->id)
                ->get(['school_class_id', 'subject_id', 'is_class_teacher']);

            $subjectIds = $assignments->whereNotNull('subject_id')->pluck('subject_id');
            $classTeacherClassIds = $assignments->where('is_class_teacher', true)->pluck('school_class_id');

            $query->where(function ($q) use ($subjectIds, $classTeacherClassIds) {
                if ($subjectIds->isNotEmpty()) {
                    $q->whereIn('id', $subjectIds);
                }

                if ($classTeacherClassIds->isNotEmpty()) {
                    $q->orWhereHas('schoolClasses', function ($classes) use ($classTeacherClassIds) {
                        $classes->whereIn('school_classes.id', $classTeacherClassIds);
                    });
                }
            });
        }

        return $query->get();
    }

    public function store(SubjectRequest $request)
    {
        $subject = Subject::create($request->validated());

        return response()->json($subject, 201);
    }

    public function show(Subject $subject)
    {
        if (Auth::user()->hasRole('teacher')) {
            $user = Auth::user();
            $allowed = TeacherAssignment::where('school_id', $user->school_id)
                ->where('user_id', $user->id)
                ->where(function ($q) use ($subject) {
                    $q->where('subject_id', $subject->id)
                        ->orWhere(function ($classTeacher) use ($subject) {
                            $classTeacher->where('is_class_teacher', true)
                                ->whereIn('school_class_id', $subject->schoolClasses()->pluck('school_classes.id'));
                        });
                })->exists();
            abort_unless($allowed, 403, 'You are not assigned to this subject.');
        }

        return $subject->load('schoolClasses');
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $subject->update($request->validated());

        return $subject;
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();

        return response()->json(['message' => 'Subject deleted.']);
    }
}
