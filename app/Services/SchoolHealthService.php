<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\AssessmentSetting;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\Term;
use App\Models\TermResultApproval;
use App\Models\User;

/**
 * Everything the Health Dashboard shows — a handful of headline
 * metrics plus a checklist of concrete, actionable data-integrity
 * gaps (a class with no subjects, a student with no guardian, etc).
 * Deliberately school-scoped only (relies on BelongsToSchool's
 * global scope on every model here) — the multi-branch "Group
 * Overview" version can call this once per branch and aggregate,
 * rather than needing its own separate logic.
 */
class SchoolHealthService
{
    public function __construct(protected AttendanceService $attendanceService) {}

    public function build(): array
    {
        $currentTerm = Term::where('is_current', true)->first();

        return [
            'metrics' => $this->metrics($currentTerm),
            'audit_issues' => $this->auditIssues($currentTerm),
        ];
    }

    protected function metrics(?Term $currentTerm): array
    {
        $totalStudents = Student::count();
        $totalStaff = User::whereHas('roles', fn ($q) => $q->whereIn('name', [
            'proprietor', 'principal', 'bursar', 'exam_officer', 'teacher',
        ]))->count();
        $totalClasses = SchoolClass::count();

        return [
            'total_students' => $totalStudents,
            'total_staff' => $totalStaff,
            'total_classes' => $totalClasses,
            'fee_collection_rate' => $currentTerm ? $this->feeCollectionRate($currentTerm->id) : null,
            'attendance_rate' => $currentTerm
                ? $this->attendanceService->schoolWideSummary($currentTerm->id)['attendance_percentage']
                : null,
            'result_publication_rate' => $currentTerm ? $this->resultPublicationRate($currentTerm->id, $totalClasses) : null,
            'current_term' => $currentTerm?->name,
        ];
    }

    protected function feeCollectionRate(int $termId): ?float
    {
        $structures = FeeStructure::where('term_id', $termId)->get();

        if ($structures->isEmpty()) {
            return null;
        }

        $totalExpected = $structures->sum(function ($structure) {
            $studentCount = $structure->school_class_id
                ? Student::where('school_class_id', $structure->school_class_id)->count()
                : Student::count();

            return $structure->amount * $studentCount;
        });

        $totalPaid = FeePayment::where('status', 'completed')
            ->whereIn('fee_structure_id', $structures->pluck('id'))
            ->sum('amount_paid');

        return $totalExpected > 0 ? round(($totalPaid / $totalExpected) * 100, 1) : null;
    }

    protected function resultPublicationRate(int $termId, int $totalClasses): ?float
    {
        if ($totalClasses === 0) {
            return null;
        }

        $approvedCount = TermResultApproval::where('term_id', $termId)
            ->distinct('school_class_id')
            ->count('school_class_id');

        return round(($approvedCount / $totalClasses) * 100, 1);
    }

    /**
     * Each issue: a machine key, a human label, how many records are
     * affected, and a `link` hint pointing at the page where it can
     * actually be fixed. Zero-count issues are omitted entirely —
     * the frontend only ever shows what's actually wrong.
     */
    protected function auditIssues(?Term $currentTerm): array
    {
        $issues = [];

        if (! AcademicSession::where('is_current', true)->exists()) {
            $issues[] = $this->issue('no_current_session', 'No academic session is marked as current', null, '/sessions-terms');
        }

        if (! $currentTerm) {
            $issues[] = $this->issue('no_current_term', 'No term is marked as current', null, '/sessions-terms');
        }

        $classesWithNoSubjects = SchoolClass::doesntHave('subjects')->count();
        if ($classesWithNoSubjects > 0) {
            $issues[] = $this->issue('classes_no_subjects', 'Classes with no subjects attached', $classesWithNoSubjects, '/classes-subjects');
        }

        $classTeacherClassIds = TeacherAssignment::where('is_class_teacher', true)->pluck('school_class_id');
        $classesWithNoClassTeacher = SchoolClass::whereNotIn('id', $classTeacherClassIds)->count();
        if ($classesWithNoClassTeacher > 0) {
            $issues[] = $this->issue('classes_no_class_teacher', 'Classes with no Class Teacher assigned', $classesWithNoClassTeacher, '/teacher-assignments');
        }

        $subjectsWithoutTeacher = $this->subjectsWithoutTeacherCount();
        if ($subjectsWithoutTeacher > 0) {
            $issues[] = $this->issue('subjects_no_teacher', 'Class-subject combinations with no teacher assigned', $subjectsWithoutTeacher, '/teacher-assignments');
        }

        $studentsWithoutGuardian = Student::doesntHave('guardians')->count();
        if ($studentsWithoutGuardian > 0) {
            $issues[] = $this->issue('students_no_guardian', 'Students with no parent/guardian linked', $studentsWithoutGuardian, '/students');
        }

        $assessmentSetting = AssessmentSetting::first();
        if (! $assessmentSetting) {
            $issues[] = $this->issue('grading_not_configured', 'Grading weights (CA/Assignment/Exam) not configured yet — ask your Exam Officer to set this up', null, null);
        } elseif (
            $assessmentSetting->ca_weight + $assessmentSetting->assignment_weight + $assessmentSetting->exam_weight !== 100
        ) {
            $issues[] = $this->issue('grading_weights_invalid', "Grading weights don't add up to 100% — ask your Exam Officer to fix this", null, null);
        }

        if ($currentTerm) {
            $classesWithNoFeeCoverage = $this->classesWithNoFeeCoverage($currentTerm->id);
            if ($classesWithNoFeeCoverage > 0) {
                $issues[] = $this->issue('classes_no_fee_structure', 'Classes with no fee structure set for the current term', $classesWithNoFeeCoverage, '/fees');
            }
        }

        return $issues;
    }

    protected function subjectsWithoutTeacherCount(): int
    {
        $assignedPairs = SchoolClass::with('subjects')->get()
            ->flatMap(fn ($class) => $class->subjects->map(fn ($s) => "{$class->id}-{$s->id}"));

        $coveredPairs = TeacherAssignment::whereNotNull('subject_id')
            ->get(['school_class_id', 'subject_id'])
            ->map(fn ($a) => "{$a->school_class_id}-{$a->subject_id}")
            ->flip();

        return $assignedPairs->reject(fn ($pair) => $coveredPairs->has($pair))->count();
    }

    protected function classesWithNoFeeCoverage(int $termId): int
    {
        $hasSchoolWideFee = FeeStructure::where('term_id', $termId)->whereNull('school_class_id')->exists();

        if ($hasSchoolWideFee) {
            return 0;
        }

        $classIdsWithOwnFee = FeeStructure::where('term_id', $termId)
            ->whereNotNull('school_class_id')
            ->pluck('school_class_id');

        return SchoolClass::whereNotIn('id', $classIdsWithOwnFee)->count();
    }

    protected function issue(string $key, string $label, ?int $count, ?string $link): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count, 'link' => $link];
    }
}
