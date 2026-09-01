<?php

namespace App\Http\Controllers\Api\Grading;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TermResultApproval;
use App\Notifications\ResultPublishedNotification;
use App\Services\ResultService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ResultController extends Controller
{
    public function __construct(protected ResultService $resultService) {}

    /**
     * Current publish state for a class/term — what the class
     * teacher's UI uses to decide which buttons to show/enable
     * (rather than the frontend guessing from whether the last
     * mutation succeeded). Same staff-only visibility as approve().
     */
    protected function authorizeClassAccess(int $schoolClassId, bool $managementCanAccess = true): void
    {
        $user = Auth::user();

        if ($managementCanAccess && $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            return;
        }

        $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
            ->where('school_class_id', $schoolClassId)
            ->where('is_class_teacher', true)
            ->exists();

        if (! $isClassTeacher) {
            abort(403, 'You are not authorized to access this class result.');
        }
    }

    public function approvalStatus(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $this->authorizeClassAccess($schoolClassId);

        $approval = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', request()->input('term_id'))
            ->with(['approvedBy:id,name', 'lockedBy:id,name'])
            ->first();

        return response()->json([
            'published' => (bool) $approval,
            'locked' => (bool) $approval?->isLocked(),
            'approved_at' => $approval?->approved_at,
            'approved_by' => $approval?->approvedBy?->name,
            'locked_at' => $approval?->locked_at,
            'locked_by' => $approval?->lockedBy?->name,
        ]);
    }

    /**
     * A single student's full report card for a term.
     *
     * Gated: a parent/student can only see this AFTER the class
     * teacher has approved the term's results (see approve() below).
     * Staff roles (proprietor, principal, exam_officer, teacher) can
     * always see it — approval only controls what FAMILIES see, not
     * what staff can review internally.
     */
    public function studentTermResult(int $studentId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $student = Student::findOrFail($studentId);
        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        // Same ownership check ReportCardController::show() and
        // AttendanceController::studentSummary() already use —
        // this endpoint was missing it entirely. Without it, ANY
        // parent or student account could read ANY other student's
        // published grades in the same school just by changing the
        // number in the URL, since the only gate below checks
        // whether results are approved, never who's asking.
        if ($user->hasRole('student') && $user->student?->id !== $studentId) {
            abort(403, 'You can only view your own results.');
        }

        if ($user->hasRole('parent') && ! $user->children()->where('students.id', $studentId)->exists()) {
            abort(403, "You can only view your own children's results.");
        }

        if ($user->hasRole(['parent', 'student']) && ! $user->hasRole([
            'proprietor', 'principal', 'exam_officer', 'teacher',
        ])) {
            $isApproved = TermResultApproval::where('school_class_id', $student->school_class_id)
                ->where('term_id', $termId)
                ->exists();

            if (! $isApproved) {
                return response()->json([
                    'message' => 'This term\'s result has not yet been published by the class teacher.',
                ], 403);
            }
        }

        return $this->resultService->computeStudentTermResult(
            $studentId,
            $student->school_class_id,
            $termId
        );
    }

    /**
     * The whole class's overall standing for a term (staff-only review
     * screen - not gated by approval, since staff use this BEFORE
     * approving).
     */
    public function classTermResult(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $this->authorizeClassAccess($schoolClassId);

        $positions = $this->resultService->computeOverallPosition(
            $schoolClassId,
            (int) request()->input('term_id')
        );

        $students = Student::whereIn('id', $positions->keys())->get()->keyBy('id');

        return $positions->map(function ($row) use ($students) {
            return array_merge($row, [
                'student_name' => $students->get($row['student_id'])?->full_name,
            ]);
        })->sortBy('position')->values();
    }

    /**
     * The class teacher reviews the class's results, then calls this
     * to approve/publish them - after this, parents and students can
     * see them via studentTermResult() above. Idempotent: calling it
     * again just updates the approved_at timestamp.
     *
     * Route-level gate allows any 'teacher' to attempt this (there's
     * no separate class_teacher role anymore) — this check is what
     * actually restricts it to the specific person who IS the class
     * teacher for THIS class, via their teacher_assignments record.
     */
    public function approve(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isClassTeacher) {
                throw ValidationException::withMessages([
                    'school_class_id' => ['Only this class\'s class teacher (or school management) can approve its results.'],
                ]);
            }
        }

        $existing = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->first();

        if ($existing?->isLocked()) {
            throw ValidationException::withMessages([
                'school_class_id' => ['Results for this class/term have been permanently published and are locked — contact school management if a correction is needed.'],
            ]);
        }

        $approval = TermResultApproval::updateOrCreate(
            ['school_class_id' => $schoolClassId, 'term_id' => $termId],
            ['approved_by' => $user->id, 'approved_at' => now()]
        );

        $this->notifyFamilies($schoolClassId, $termId, $user->school->name);

        return response()->json([
            'message' => 'Results approved and published to parents/students.',
            'approval' => $approval,
        ]);
    }

    /**
     * Once results are approved, let every affected student and
     * their guardians know — otherwise the only way to find out is
     * to happen to log back in and check.
     */
    protected function notifyFamilies(int $schoolClassId, int $termId, string $schoolName): void
    {
        $termName = Term::find($termId)?->name ?? 'This term\'s';

        Student::where('school_class_id', $schoolClassId)
            ->with(['user', 'guardians'])
            ->get()
            ->each(function ($student) use ($termName, $schoolName) {
                $notification = new ResultPublishedNotification($student->full_name, $termName, $schoolName);

                $student->user?->notify($notification);
                $student->guardians->each(fn ($guardian) => $guardian->notify($notification));
            });
    }

    /**
     * The "temporary" publish above is the reviewable, undo-able
     * stage — this is the second, deliberate step that makes it
     * permanent: after this, scores for the class/term can no longer
     * be edited (SubjectScoreController checks isLocked()) and this
     * approval can no longer be revoked. Requires results to already
     * be (temporarily) published — this is a second confirmation on
     * top of an existing publish, not a shortcut past it. Same
     * permission rule as approve()/revokeApproval().
     */
    public function publishPermanently(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isClassTeacher) {
                throw ValidationException::withMessages([
                    'school_class_id' => ['Only this class\'s class teacher (or school management) can publish its results permanently.'],
                ]);
            }
        }

        $approval = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'school_class_id' => ['Publish results first — permanent publishing locks in an existing publish, it doesn\'t replace it.'],
            ]);
        }

        if ($approval->isLocked()) {
            return response()->json([
                'message' => 'Results are already permanently published.',
                'approval' => $approval,
            ]);
        }

        $approval->update(['locked_by' => $user->id, 'locked_at' => now()]);

        return response()->json([
            'message' => 'Results are now permanently published — they can no longer be unpublished or edited.',
            'approval' => $approval,
        ]);
    }

    /**
     * Revoke a previously granted (temporary) approval - e.g. a
     * mistake was found after publishing. Same permission rules as
     * approve(). Blocked once permanently published — that's the
     * entire point of the permanent stage; if a genuine correction is
     * needed after that point, it has to go through school management
     * making the call deliberately, not a one-click unpublish.
     */
    public function revokeApproval(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        if (! $user->hasRole(['proprietor', 'principal', 'exam_officer'])) {
            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isClassTeacher) {
                throw ValidationException::withMessages([
                    'school_class_id' => ['Only this class\'s class teacher (or school management) can revoke its results.'],
                ]);
            }
        }

        $approval = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->first();

        if ($approval?->isLocked()) {
            throw ValidationException::withMessages([
                'school_class_id' => ['Results have been permanently published and can no longer be unpublished.'],
            ]);
        }

        $approval?->delete();

        return response()->json(['message' => 'Approval revoked - results are hidden from parents/students again.']);
    }
}
