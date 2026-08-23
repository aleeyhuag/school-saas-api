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
            'schoolLogoDataUri' => $this->schoolLogoDataUri($student->school),
            'term' => $term,
            'result' => $result,
            'attendance' => $attendance,
            'isApproved' => $isApproved,
        ])->setPaper('a4', 'portrait');
    }

    /**
     * DomPDF can embed JPEG directly, but PNG/WebP rendering requires GD.
     * Keep report-card generation independent of a developer's local GD
     * setup when the logo itself is the only image: use the original JPEG
     * when possible, otherwise convert to JPEG when GD is available, and
     * omit the logo rather than making an otherwise valid report fail.
     * Production still verifies GD at image/PDF level via the Docker build.
     */
    protected function schoolLogoDataUri($school): ?string
    {
        $path = $school->logo_path ?? null;
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $bytes = $disk->get($path);
            if ($bytes === '' || $bytes === null) {
                return null;
            }

            $mime = null;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_buffer($finfo, $bytes) : false;
                if ($finfo) {
                    finfo_close($finfo);
                }
            }

            if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
                return 'data:image/jpeg;base64,'.base64_encode($bytes);
            }

            if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
                return null;
            }

            $source = @imagecreatefromstring($bytes);
            if ($source === false) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);
            $max = 900;
            $scale = min(1, $max / max($width, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($canvas, null, 88);
            $jpeg = ob_get_clean();
            imagedestroy($source);
            imagedestroy($canvas);

            return $jpeg ? 'data:image/jpeg;base64,'.base64_encode($jpeg) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
