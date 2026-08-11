<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Term;
use App\Models\TermResultApproval;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Builds the actual PDF binary for one student's report card. Used
 * both for a single download (ReportCardController::show) and inside
 * a class-wide ZIP (ReportCardController::classBulk) — the caller
 * decides what to do with the returned Pdf object (stream, save to a
 * temp path for zipping, etc.), this service only builds it.
 */
class ReportCardPdfService
{
    public function __construct(
        protected ResultService $resultService,
        protected AttendanceService $attendanceService,
    ) {}

    public function build(int $studentId, int $termId)
    {
        $student = Student::with('schoolClass', 'school')->findOrFail($studentId);
        $term = Term::with('academicSession')->findOrFail($termId);

        $result = $this->resultService->computeStudentTermResult(
            $studentId,
            $student->school_class_id,
            $termId
        );

        $attendance = $this->attendanceService->summaryFor($studentId, $termId);

        $isApproved = TermResultApproval::where('school_class_id', $student->school_class_id)
            ->where('term_id', $termId)
            ->exists();

        return Pdf::loadView('reports.report-card', [
            'student' => $student,
            'school' => $student->school,
            'term' => $term,
            'result' => $result,
            'attendance' => $attendance,
            'isApproved' => $isApproved,
        ])->setPaper('a4', 'portrait');
    }
}
