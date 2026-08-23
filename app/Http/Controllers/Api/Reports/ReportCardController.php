<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\TermResultApproval;
use App\Services\ReportCardPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use ZipArchive;

class ReportCardController extends Controller
{
    public function __construct(protected ReportCardPdfService $pdfService) {}

    /**
     * A single student's report card PDF.
     *
     * Same visibility rule as ResultController::studentTermResult() —
     * staff can always generate it (useful for reviewing before
     * approval), a parent/student can only download it once the
     * class teacher has approved that term's results.
     */
    public function show(int $studentId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $student = Student::findOrFail($studentId);
        $termId = (int) request()->input('term_id');
        $user = Auth::user();

        if ($user->hasRole('student') && $user->student?->id !== $studentId) {
            abort(403, 'You can only download your own report card.');
        }

        if ($user->hasRole('parent') && ! $user->children()->where('students.id', $studentId)->exists()) {
            abort(403, "You can only download your own children's report cards.");
        }

        if ($user->hasRole('teacher') && ! TeacherAssignment::where('user_id', $user->id)->where('school_class_id', $student->school_class_id)->exists()) {
            abort(403, 'You are not assigned to this student\'s class.');
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

        $filename = str($student->full_name)->slug().'-report-card.pdf';

        try {
            return $this->pdfService->build($studentId, $termId)->download($filename);
        } catch (\Throwable $e) {
            Log::error('Report card PDF generation failed', [
                'student_id' => $studentId,
                'term_id' => $termId,
                'exception' => $e,
            ]);

            // Keep local development's useful exception visible, while
            // replacing the old opaque production 500 with an actionable
            // message that points directly at the PDF/image runtime.
            if (! app()->isProduction()) {
                throw $e;
            }

            $gdReady = function_exists('imagecreatetruecolor')
                && function_exists('imagecreatefrompng')
                && function_exists('imagejpeg');

            return response()->json([
                'message' => $gdReady
                    ? 'The report card PDF could not be generated on the server. The error has been logged; please try again or contact support if it continues.'
                    : 'Report card PDF generation is unavailable because the API server\'s GD image extension is not available. Please redeploy the API with the current Dockerfile, then try again.',
                'code' => $gdReady ? 'report_card_pdf_failed' : 'pdf_gd_unavailable',
            ], $gdReady ? 500 : 503);
        }
    }

    /**
     * Every student in a class, zipped up as individual PDFs — for a
     * class teacher to hand out the whole class's report cards at
     * once. Restricted to that class's own class teacher, or school
     * management. Requires the results to already be approved —
     * unlike show() above, there's no "staff preview" case here since
     * this is meant for actual distribution, not internal review.
     */
    public function classBulk(int $schoolClassId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $termId = (int) request()->input('term_id');
        $user = Auth::user();
        $schoolClass = SchoolClass::findOrFail($schoolClassId);

        if (! $user->hasRole(['proprietor', 'principal'])) {
            $isClassTeacher = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $schoolClassId)
                ->where('is_class_teacher', true)
                ->exists();

            if (! $isClassTeacher) {
                throw ValidationException::withMessages([
                    'school_class_id' => ['Only this class\'s class teacher (or school management) can export its report cards.'],
                ]);
            }
        }

        $isApproved = TermResultApproval::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->exists();

        if (! $isApproved) {
            throw ValidationException::withMessages([
                'school_class_id' => ['This class\'s results haven\'t been approved yet — approve them first, then export.'],
            ]);
        }

        $students = Student::where('school_class_id', $schoolClassId)->get();

        if ($students->isEmpty()) {
            throw ValidationException::withMessages([
                'school_class_id' => ['This class has no students yet.'],
            ]);
        }

        $zipRelativePath = 'report-cards/'.uniqid('class-'.$schoolClassId.'-', true).'.zip';
        $zipFullPath = Storage::disk('local')->path($zipRelativePath);
        Storage::disk('local')->makeDirectory('report-cards');

        $zip = new ZipArchive;
        $zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($students as $student) {
            $pdfOutput = $this->pdfService->build($student->id, $termId)->output();
            $zip->addFromString(str($student->full_name)->slug().'.pdf', $pdfOutput);
        }

        $zip->close();

        $downloadName = str($schoolClass->full_name)->slug().'-report-cards.zip';

        return response()->download($zipFullPath, $downloadName)->deleteFileAfterSend(true);
    }
}
