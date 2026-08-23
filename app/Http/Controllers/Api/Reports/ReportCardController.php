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
use Illuminate\Support\Facades\URL;
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

        $this->authorizeSingleDownload($student, $termId, $user);

        $filename = str($student->full_name)->slug().'-report-card.pdf';

        return $this->pdfService->build($studentId, $termId)->download($filename);
    }

    /**
     * Authenticated step for student/parent report-card downloads.
     * Returns a short-lived signed URL so the actual PDF can be opened by
     * normal browser navigation rather than an authenticated blob fetch.
     * This mirrors the proven ID-card/export download architecture.
     */
    public function requestDownloadUrl(int $studentId)
    {
        request()->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
        ]);

        $student = Student::findOrFail($studentId);
        $termId = (int) request()->input('term_id');
        $this->authorizeSingleDownload($student, $termId, Auth::user());

        $url = URL::temporarySignedRoute(
            'report-card.download',
            now()->addMinutes(10),
            ['studentId' => $studentId, 'termId' => $termId]
        );

        return response()->json(['download_url' => $url]);
    }

    /**
     * Signed, unauthenticated browser-navigation endpoint. The signature
     * is only issued after the authenticated authorization check above.
     */
    public function showSigned(int $studentId, int $termId)
    {
        abort_unless(request()->hasValidSignature(), 403, 'This download link is invalid or has expired.');

        $student = Student::findOrFail($studentId);
        $filename = str($student->full_name)->slug().'-report-card.pdf';

        return $this->pdfService->build($studentId, $termId)->download($filename);
    }

    protected function authorizeSingleDownload(Student $student, int $termId, $user): void
    {
        if ((int) $student->school_id !== (int) $user->school_id) {
            abort(403, 'This student does not belong to your school.');
        }

        if ($user->hasRole('student') && $user->student?->id !== $student->id) {
            abort(403, 'You can only download your own report card.');
        }

        if ($user->hasRole('parent') && ! $user->children()->where('students.id', $student->id)->exists()) {
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
                abort(403, 'This term\'s result has not yet been published by the class teacher.');
            }
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
