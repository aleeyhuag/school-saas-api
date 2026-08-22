<?php

namespace App\Http\Controllers\Api\IdCards;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Services\IdCardPdfService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

/**
 * Single, on-demand ID card download — no queue involved, unlike bulk
 * generation (ExportController::requestIdCards). One CR80 PDF is fast
 * enough to build inside a normal request; queueing it would just add
 * latency for no benefit.
 *
 * Split into two steps — request a signed URL, then navigate to it —
 * rather than one Bearer-token-authenticated endpoint the frontend
 * fetches as a blob. Blob-URL downloads have been unreliable
 * specifically on mobile browsers (iOS Safari in particular). A
 * signed URL lets the frontend do a plain page navigation instead,
 * which every browser handles for a PDF the same reliable way it
 * always has. Same pattern already used for exports and payment
 * proofs elsewhere in this app.
 */
class IdCardController extends Controller
{
    public function requestDownloadUrl(int $studentId)
    {
        $this->authorizeAccess($studentId);

        $url = URL::temporarySignedRoute(
            'id-card.download',
            now()->addMinutes(10),
            ['studentId' => $studentId]
        );

        return response()->json(['download_url' => $url]);
    }

    /**
     * Reached only via the signed URL above — the signature itself is
     * the authorization (only ever generated for an already-authorized
     * viewer by requestDownloadUrl()), same pattern as
     * MediaController's payment-proof/student-photo routes. Not behind
     * auth:sanctum, so a plain browser navigation works.
     */
    public function show(int $studentId, IdCardPdfService $idCardService)
    {
        if (! request()->hasValidSignature()) {
            abort(403, 'This download link has expired — go back and try again.');
        }

        $student = Student::findOrFail($studentId);
        $pdf = $idCardService->buildSingle($studentId);

        return $pdf->download($student->admission_number.'-id-card.pdf');
    }

    protected function authorizeAccess(int $studentId): void
    {
        $student = Student::findOrFail($studentId);
        $user = Auth::user();

        if ((int) $student->school_id !== (int) $user->school_id) {
            abort(403, 'This student does not belong to your school.');
        }

        if (! $user->hasRole(['proprietor', 'principal'])) {
            $isAssigned = TeacherAssignment::where('user_id', $user->id)
                ->where('school_class_id', $student->school_class_id)
                ->exists();

            if (! $isAssigned) {
                abort(403, 'You are not assigned to this student\'s class.');
            }
        }
    }
}
