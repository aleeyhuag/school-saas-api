<?php

namespace App\Http\Controllers\Api\IdCards;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Services\IdCardPdfService;
use Illuminate\Support\Facades\Auth;

/**
 * Single, on-demand ID card download — no queue involved, unlike bulk
 * generation (ExportController::requestIdCards). One CR80 PDF is fast
 * enough to build inside a normal request; queueing it would just add
 * latency for no benefit.
 */
class IdCardController extends Controller
{
    public function show(int $studentId, IdCardPdfService $idCardService)
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

        $pdf = $idCardService->buildSingle($studentId);

        return $pdf->download($student->admission_number.'-id-card.pdf');
    }
}
