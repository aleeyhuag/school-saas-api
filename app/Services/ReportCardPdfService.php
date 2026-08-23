<?php

namespace App\Services;

use App\Models\Student;
use App\Models\Term;
use App\Models\TermResultApproval;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

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
            'logo_data_uri' => $this->logoDataUri($student->school?->logo_path),
        ])->setPaper('a4', 'portrait');
    }
    /**
     * Dompdf should not have to make an HTTP request back to the API just
     * to render the school's own logo. Read the public-disk bytes locally
     * and embed them in the PDF, matching the ID-card renderer.
     */
    protected function logoDataUri(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                return null;
            }

            $bytes = $disk->get($path);
            if ($bytes === '' || $bytes === null) {
                return null;
            }

            $mime = 'image/png';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detected = $finfo ? finfo_buffer($finfo, $bytes) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }
                if (is_string($detected) && str_starts_with($detected, 'image/')) {
                    $mime = $detected;
                }
            }

            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        } catch (\Throwable) {
            return null;
        }
    }

}
