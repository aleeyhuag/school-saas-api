<?php

namespace App\Http\Controllers\Api\Timetable;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\ClassTimetableEntry;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ClassTimetableController extends Controller
{
    /** Full timetable for an authorized class. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'academic_session_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $schoolId = (int) $user->school_id;
        $classId = (int) $data['school_class_id'];

        $this->assertClassViewAccess($user, $classId);
        $this->assertSessionBelongsToSchool((int) $data['academic_session_id'], $schoolId);

        return ClassTimetableEntry::with(['subject', 'teacher:id,name', 'schoolClass:id,name,arm'])
            ->where('school_id', $schoolId)
            ->where('school_class_id', $classId)
            ->where('academic_session_id', $data['academic_session_id'])
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get()
            ->groupBy('day_of_week')
            ->map->values();
    }

    /**
     * Teachers: class teachers see the complete timetable for their class(es);
     * subject-only teachers see their assigned timetable entries.
     */
    public function mySchedule(Request $request)
    {
        $data = $request->validate([
            'academic_session_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $schoolId = (int) $user->school_id;
        $this->assertSessionBelongsToSchool((int) $data['academic_session_id'], $schoolId);

        $assignments = TeacherAssignment::where('school_id', $schoolId)
            ->where('user_id', $user->id)
            ->get(['school_class_id', 'subject_id', 'is_class_teacher']);

        $classTeacherIds = $assignments->where('is_class_teacher', true)->pluck('school_class_id')->unique();

        $query = ClassTimetableEntry::with(['subject', 'schoolClass:id,name,arm', 'teacher:id,name'])
            ->where('school_id', $schoolId)
            ->where('academic_session_id', $data['academic_session_id']);

        if ($classTeacherIds->isNotEmpty()) {
            // A Class Teacher sees every subject/teacher in their own class(es).
            $query->whereIn('school_class_id', $classTeacherIds);
        } elseif ($assignments->isNotEmpty()) {
            // A subject-only teacher sees only entries matching their assignments.
            $query->where(function ($q) use ($assignments) {
                foreach ($assignments as $assignment) {
                    $q->orWhere(function ($sub) use ($assignment) {
                        $sub->where('school_class_id', $assignment->school_class_id);
                        if ($assignment->subject_id !== null) {
                            $sub->where('subject_id', $assignment->subject_id);
                        } else {
                            $sub->where('user_id', request()->user()->id);
                        }
                    });
                }
            });
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->orderBy('day_of_week')->orderBy('period')->get()
            ->groupBy('day_of_week')->map->values();
    }

    /**
     * Create/update a timetable slot from an existing TeacherAssignment.
     * The client never chooses subject and teacher independently.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'school_class_id' => ['required', 'integer'],
            'teacher_assignment_id' => ['required', 'integer'],
            'day_of_week' => ['required', 'integer', 'between:1,5'],
            'period' => ['required', 'integer', 'min:1'],
            'room' => ['nullable', 'string', 'max:100'],
            'academic_session_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $schoolId = (int) $user->school_id;
        $this->assertSessionBelongsToSchool((int) $data['academic_session_id'], $schoolId);

        $assignment = TeacherAssignment::with(['teacher:id,name', 'subject:id,name'])
            ->where('school_id', $schoolId)
            ->whereKey($data['teacher_assignment_id'])
            ->where('school_class_id', $data['school_class_id'])
            ->whereNotNull('subject_id')
            ->first();

        if (!$assignment) {
            throw ValidationException::withMessages([
                'teacher_assignment_id' => ['Select a valid subject-teacher assignment for this class.'],
            ]);
        }

        $entry = ClassTimetableEntry::updateOrCreate(
            [
                'school_id' => $schoolId,
                'school_class_id' => $assignment->school_class_id,
                'day_of_week' => $data['day_of_week'],
                'period' => $data['period'],
                'academic_session_id' => $data['academic_session_id'],
            ],
            [
                'subject_id' => $assignment->subject_id,
                'user_id' => $assignment->user_id,
                'room' => $data['room'] ?? null,
            ]
        );

        return response()->json($entry->load(['subject', 'teacher:id,name', 'schoolClass:id,name,arm']), 201);
    }

    public function destroy(ClassTimetableEntry $classTimetableEntry)
    {
        abort_unless($classTimetableEntry->school_id === Auth::user()->school_id, 404);
        $classTimetableEntry->delete();
        return response()->json(['message' => 'Entry removed.']);
    }

    private function assertSessionBelongsToSchool(int $sessionId, int $schoolId): void
    {
        abort_unless(
            AcademicSession::withoutGlobalScopes()->whereKey($sessionId)->where('school_id', $schoolId)->exists(),
            404
        );
    }

    private function assertClassViewAccess($user, int $classId): void
    {
        $schoolId = (int) $user->school_id;
        abort_unless(
            SchoolClass::withoutGlobalScopes()->whereKey($classId)->where('school_id', $schoolId)->exists(),
            404
        );

        if ($user->hasAnyRole(['proprietor', 'principal', 'exam_officer'])) {
            return;
        }

        if ($user->hasRole('teacher')) {
            $allowed = TeacherAssignment::withoutGlobalScopes()
                ->where('school_id', $schoolId)
                ->where('user_id', $user->id)
                ->where('school_class_id', $classId)
                ->exists();
            abort_unless($allowed, 403);
            return;
        }

        if ($user->hasRole('student')) {
            abort_unless($user->student && (int) $user->student->school_class_id === $classId, 403);
            return;
        }

        if ($user->hasRole('parent')) {
            $allowed = $user->children()->where('school_class_id', $classId)->exists();
            abort_unless($allowed, 403);
            return;
        }

        abort(403);
    }
}
