<?php

namespace App\Http\Controllers\Api\IdCards;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\IdCardPreviewService;
use Illuminate\Http\Request;
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

    /**
     * Bulk variant — one class (or any multi-select) printed as a single
     * browser print job instead of visiting each student's preview one
     * at a time. Capped at 100 per request purely as a sanity guard
     * against an accidental "select everything" request; the frontend
     * chunks larger selections (e.g. a whole-school print) into
     * multiple calls of this size rather than the cap forcing staff to
     * do that manually.
     *
     * Tenant scoping happens in the query itself (whereIn school_id)
     * rather than per-row abort checks — any requested id outside the
     * caller's own school is silently dropped rather than erroring, so
     * a mixed valid/invalid selection still prints the valid students.
     */
    public function bulk(Request $request, IdCardPreviewService $previewService)
    {
        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_ids.*' => ['integer'],
        ]);

        $students = Student::with(['schoolClass', 'school'])
            ->where('school_id', Auth::user()->school_id)
            ->whereIn('id', $validated['student_ids'])
            ->get();

        abort_if($students->isEmpty(), 404, 'No matching students found for your school.');

        return response()->json(['cards' => $previewService->forStudents($students)]);
    }
}
