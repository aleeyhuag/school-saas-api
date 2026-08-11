<?php

namespace App\Http\Controllers\Api\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Models\Attendance;
use App\Models\TeacherAssignment;
use App\Services\AttendanceService;
use App\Models\Student;
use App\Models\Term;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService) {}

    /**
     * Mark (or re-mark) attendance for a whole class in one request.
     *
     * Body shape:
     * {
     *   "term_id": 1,
     *   "school_class_id": 1,
     *   "subject_id": null,        // omit/null = whole-day attendance
     *   "date": "2026-07-23",
     *   "records": [
     *     { "student_id": 1, "status": "present" },
     *     { "student_id": 2, "status": "absent" }
     *   ]
     * }
     *
     * Uses upsert-by-unique-key logic (update if already marked today,
     * create otherwise) so a teacher can safely re-submit corrections.
     */
    public function store(MarkAttendanceRequest $request)
    {
        $validated = $request->validated();
        $user = Auth::user();

        // Proprietors and principals can mark attendance for any class.
        // Everyone else follows a stricter split:
        //  - Whole-day attendance (subject_id is null) — the OFFICIAL
        //    register that feeds the report card — can only be marked
        //    by that class's class teacher.
        //  - Subject/period attendance (subject_id is set) — optional,
        //    supplementary monitoring — can only be marked by the
        //    subject teacher actually assigned to that class+subject.
        if (! $user->hasRole(['proprietor', 'principal'])) {
            $isWholeDayMark = empty($validated['subject_id']);

            $isAssigned = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $validated['school_class_id'])
                ->when($isWholeDayMark, function ($q) {
                    // Whole-day: must specifically be the class teacher
                    $q->where('is_class_teacher', true);
                }, function ($q) use ($validated) {
                    // Subject-level: must be assigned to this exact subject
                    $q->where('subject_id', $validated['subject_id']);
                })
                ->exists();

            if (! $isAssigned) {
                $message = $isWholeDayMark
                    ? 'Only this class\'s class teacher can mark whole-day attendance.'
                    : 'You are not assigned to teach this subject in this class.';

                throw ValidationException::withMessages([
                    'school_class_id' => [$message],
                ]);
            }
        }

        $schoolClass = SchoolClass::findOrFail($validated['school_class_id']);
        $term = Term::findOrFail($validated['term_id']);

        if ($term->school_id !== $schoolClass->school_id) {
            throw ValidationException::withMessages(['term_id' => ['The term and class must belong to the same school.']]);
        }

        $studentIds = collect($validated['records'])->pluck('student_id')->map(fn ($id) => (int) $id)->unique();
        $studentsInClass = Student::where('school_class_id', $schoolClass->id)->whereIn('id', $studentIds)->count();
        if ($studentsInClass !== $studentIds->count()) {
            throw ValidationException::withMessages(['records' => ['Every attendance student must belong to the selected class.']]);
        }

        $subjectId = $validated['subject_id'] ?? null;
        if ($subjectId !== null && ! $schoolClass->subjects()->where('subjects.id', $subjectId)->exists()) {
            throw ValidationException::withMessages(['subject_id' => ['This subject is not assigned to the selected class.']]);
        }

        // NOTE: this used to be a single DB::table()->upsert() call
        // matched on ['student_id','school_class_id','subject_id','date'].
        // That silently broke for whole-day attendance (subject_id
        // NULL): both SQLite and Postgres treat NULL as distinct from
        // NULL for uniqueness purposes, so ON CONFLICT never matched
        // an existing whole-day row — every re-save of the same day
        // INSERTed a brand new duplicate row instead of updating the
        // existing one, silently inflating attendance totals over
        // time. updateOrCreate() below matches explicitly (via
        // whereNull when subject_id is null) so re-marking a day
        // always updates in place, for both whole-day and period
        // attendance.
        foreach ($validated['records'] as $record) {
            Attendance::updateOrCreate(
                [
                    'student_id' => $record['student_id'],
                    'school_class_id' => $validated['school_class_id'],
                    'subject_id' => $subjectId,
                    'date' => $validated['date'],
                ],
                [
                    'school_id' => $user->school_id,
                    'term_id' => $validated['term_id'],
                    'status' => $record['status'],
                    'marked_by' => $user->id,
                ]
            );
        }

        $saved = Attendance::where('school_class_id', $validated['school_class_id'])
            ->where('date', $validated['date'])
            ->where('subject_id', $validated['subject_id'] ?? null)
            ->with('student')
            ->get();

        return response()->json([
            'message' => 'Attendance saved.',
            'attendance' => $saved,
        ], 201);
    }

    /**
     * View attendance for a class on a given date (or date range),
     * optionally filtered by subject.
     *
     * Query params: school_class_id (required), date (single day) OR
     * from + to (range), subject_id (optional).
     */
    public function index()
    {
        request()->validate([
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'date' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'subject_id' => ['nullable', 'integer'],
        ]);

        $schoolClassId = (int) request()->input('school_class_id');
        $user = Auth::user();

        if ($user->hasRole('teacher')) {
            $assigned = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->exists();
            if (! $assigned) {
                abort(403, 'You are not assigned to this class.');
            }
        } elseif ($user->hasRole('student')) {
            if ($user->student?->school_class_id !== $schoolClassId) abort(403);
        } elseif ($user->hasRole('parent')) {
            $hasChild = $user->children()->where('students.school_class_id', $schoolClassId)->exists();
            if (! $hasChild) abort(403);
        }

        $query = Attendance::with('student')
            ->where('school_class_id', $schoolClassId);

        if (request()->filled('date')) {
            $query->where('date', request()->input('date'));
        } elseif (request()->filled('from') && request()->filled('to')) {
            $query->whereBetween('date', [request()->input('from'), request()->input('to')]);
        }

        if (request()->filled('subject_id')) {
            $query->where('subject_id', request()->input('subject_id'));
        } else {
            $query->whereNull('subject_id');
        }

        return $query->orderBy('date')->get();
    }

    /**
     * A single student's attendance summary for a term —
     * total days marked, present/absent/late/excused counts, and a
     * percentage. This is what feeds the report card later.
     */
    public function studentSummary(int $studentId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $user = Auth::user();

        // A student may only ever pull their own summary; a parent
        // only one of their own linked children's — both scoped via
        // their account link rather than trusting the URL's ID.
        if ($user->hasRole('student') && $user->student?->id !== $studentId) {
            abort(403, 'You can only view your own attendance.');
        }

        if ($user->hasRole('parent') && ! $user->children()->where('students.id', $studentId)->exists()) {
            abort(403, "You can only view your own children's attendance.");
        }

        if ($user->hasRole('teacher') && ! TeacherAssignment::where('user_id', $user->id)->where('school_class_id', Student::findOrFail($studentId)->school_class_id)->exists()) {
            abort(403, 'You are not assigned to this student\'s class.');
        }

        $termId = request()->input('term_id');

        return response()->json(array_merge(
            ['student_id' => $studentId, 'term_id' => $termId],
            $this->attendanceService->summaryFor($studentId, $termId)
        ));
    }
}
