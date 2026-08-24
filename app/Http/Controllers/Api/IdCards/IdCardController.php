<?php

namespace App\Http\Controllers\Api\IdCards;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\IdCardPreviewService;
use Illuminate\Support\Facades\Auth;

/**
 * Supplies data for the browser-rendered student ID card.
 *
 * There is intentionally no PDF endpoint here anymore. The browser page is
 * the source of truth for the design and window.print() is the print path.
 */
class IdCardController extends Controller
{
    public function preview(Student $student, IdCardPreviewService $previewService)
    {
        abort_unless((int) $student->school_id === (int) Auth::user()->school_id, 403, 'This student does not belong to your school.');

        return response()->json($previewService->forStudent($student->load('schoolClass', 'school')));
    }
}
