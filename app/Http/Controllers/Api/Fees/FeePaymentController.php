<?php

namespace App\Http\Controllers\Api\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\RecordFeePaymentRequest;
use App\Models\FeePayment;
use App\Models\Student;
use App\Models\FeeStructure;
use App\Models\Term;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Services\FeeService;
use Illuminate\Support\Facades\Auth;

class FeePaymentController extends Controller
{
    public function __construct(protected FeeService $feeService) {}

    /**
     * Manually record a payment (cash, bank transfer, or a note about
     * a gateway payment). This is the bursar's day-to-day action.
     *
     * NOTE: this does NOT process a live payment — it records that a
     * payment happened. Wiring up Paystack/Flutterwave for actual
     * card/transfer collection is a separate integration (their SDK
     * posts to a webhook you'd add later, which would call this same
     * logic with method = paystack/flutterwave and status from their
     * webhook payload).
     */
    public function store(RecordFeePaymentRequest $request)
    {
        $validated = $request->validated();
        $student = Student::findOrFail($validated['student_id']);
        $fee = FeeStructure::findOrFail($validated['fee_structure_id']);

        if ($fee->term_id === null || $fee->term_id <= 0) {
            throw ValidationException::withMessages(['fee_structure_id' => ['The fee structure has no valid term.']]);
        }
        if ($fee->school_class_id !== null && $fee->school_class_id !== $student->school_class_id) {
            throw ValidationException::withMessages(['fee_structure_id' => ['This fee structure does not apply to the student\'s class.']]);
        }

        $payment = FeePayment::create(array_merge($validated, [
            'status' => 'completed',
            'recorded_by' => Auth::id(),
        ]));

        return response()->json($payment->load('feeStructure', 'student'), 201);
    }

    /**
     * Payment history for one student.
     */
    public function forStudent(int $studentId)
    {
        return FeePayment::where('student_id', $studentId)
            ->with('feeStructure')
            ->orderByDesc('paid_at')
            ->get();
    }

    /**
     * One student's full fee status for a term — total due, total
     * paid, balance, and a per-fee breakdown. Feeds the parent app's
     * "fees" screen and the report card's fee-status line.
     */
    public function studentStatus(int $studentId)
    {
        request()->validate(['term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)]]);

        $student = Student::findOrFail($studentId);
        $user = Auth::user();

        // This endpoint had NO ownership check at all — any parent
        // or student account in the school could read any other
        // family's fee balance just by changing $studentId, with no
        // approval gate or anything else standing in the way. Same
        // pattern as ReportCardController/AttendanceController.
        if ($user->hasRole('student') && $user->student?->id !== $studentId) {
            abort(403, 'You can only view your own fee status.');
        }

        if ($user->hasRole('parent') && ! $user->children()->where('students.id', $studentId)->exists()) {
            abort(403, "You can only view your own children's fee status.");
        }

        return $this->feeService->studentFeeStatus($student, (int) request()->input('term_id'));
    }

    /**
     * The bursar's defaulters list for a class/term — every student
     * with an outstanding balance.
     */
    public function classDefaulters(int $schoolClassId)
    {
        request()->validate(['term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)]]);

        return $this->feeService->classDefaulters($schoolClassId, (int) request()->input('term_id'));
    }
}
