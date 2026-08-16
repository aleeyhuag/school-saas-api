<?php

namespace App\Services;

use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Computes what a student owes vs. has paid. Like ResultService, this
 * never stores a "balance" column — it's always derived fresh from
 * fee_structures (what's owed) and fee_payments (what's been paid),
 * so recording a late payment or editing a fee structure is always
 * immediately reflected everywhere without a separate "recalculate"
 * step.
 */
class FeeService
{
    /**
     * One student's fee status for a term: every applicable fee
     * structure (class-specific + school-wide), how much has been
     * paid toward each, and the overall balance.
     */
    public function studentFeeStatus(Student $student, int $termId): array
    {
        $structures = FeeStructure::where('term_id', $termId)
            ->where(function ($q) use ($student) {
                $q->where('school_class_id', $student->school_class_id)
                    ->orWhereNull('school_class_id'); // school-wide fees
            })
            ->get();

        $payments = FeePayment::where('student_id', $student->id)
            ->where('status', 'completed')
            ->whereIn('fee_structure_id', $structures->pluck('id'))
            ->get()
            ->groupBy('fee_structure_id');

        return $this->buildStatus($student, $termId, $structures, $payments);
    }

    /**
     * Shared assembly logic for one student's fee status, given
     * fee structures and that student's payments (already grouped by
     * fee_structure_id) — pure in-memory work, no queries. Used by
     * studentFeeStatus() (single student, its own small queries above)
     * and classDefaulters() (whole class, bulk-fetched once — see there).
     */
    protected function buildStatus(Student $student, int $termId, Collection $structures, Collection $paymentsByStructure): array
    {
        $breakdown = $structures->map(function ($structure) use ($paymentsByStructure) {
            $paid = $paymentsByStructure->get($structure->id, collect())->sum('amount_paid');

            return [
                'fee_structure_id' => $structure->id,
                'name' => $structure->name,
                'amount_due' => $structure->amount,
                'amount_paid' => round($paid, 2),
                'balance' => round($structure->amount - $paid, 2),
                'due_date' => $structure->due_date,
            ];
        });

        return [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'term_id' => $termId,
            'fees' => $breakdown->values(),
            'total_due' => round($breakdown->sum('amount_due'), 2),
            'total_paid' => round($breakdown->sum('amount_paid'), 2),
            'total_balance' => round($breakdown->sum('balance'), 2),
        ];
    }

    /**
     * List every student in a class with an outstanding balance for
     * the term — this is the bursar's "defaulters" screen.
     *
     * Previously called studentFeeStatus() once per student, which ran
     * 2 fresh queries (fee_structures + fee_payments) for every single
     * student in the class — for a class of 50 students that's ~100
     * queries computing overlapping data (the same fee structures for
     * every student). Now fetches the class's fee structures and every
     * relevant payment ONCE, then assembles each student's status
     * purely in-memory.
     */
    public function classDefaulters(int $schoolClassId, int $termId): Collection
    {
        $students = Student::where('school_class_id', $schoolClassId)->get();

        $structures = FeeStructure::where('term_id', $termId)
            ->where(function ($q) use ($schoolClassId) {
                $q->where('school_class_id', $schoolClassId)
                    ->orWhereNull('school_class_id');
            })
            ->get();

        $structureIds = $structures->pluck('id');
        $studentIds = $students->pluck('id');

        $paymentsByStudent = FeePayment::where('status', 'completed')
            ->whereIn('student_id', $studentIds)
            ->whereIn('fee_structure_id', $structureIds)
            ->get()
            ->groupBy('student_id');

        return $students
            ->map(function ($student) use ($structures, $paymentsByStudent, $termId) {
                $paymentsByStructure = ($paymentsByStudent->get($student->id) ?? collect())
                    ->groupBy('fee_structure_id');

                return $this->buildStatus($student, $termId, $structures, $paymentsByStructure);
            })
            ->filter(fn ($status) => $status['total_balance'] > 0)
            ->values();
    }

    /**
     * A whole-school total for the term — what the Overview dashboard
     * shows instead of a blank placeholder. Expected total accounts
     * for how many students each fee actually applies to (a
     * class-scoped fee only counts that class's students; a
     * whole-school fee counts everyone).
     */
    public function schoolFeeSummary(int $schoolId, int $termId): array
    {
        $structures = FeeStructure::where('school_id', $schoolId)->where('term_id', $termId)->get();
        $totalStudents = Student::where('school_id', $schoolId)->count();

        $totalDue = $structures->sum(function ($structure) use ($totalStudents) {
            $applicableStudents = $structure->school_class_id
                ? Student::where('school_class_id', $structure->school_class_id)->count()
                : $totalStudents;

            return $structure->amount * $applicableStudents;
        });

        $totalPaid = FeePayment::where('school_id', $schoolId)
            ->whereIn('fee_structure_id', $structures->pluck('id'))
            ->where('status', 'completed')
            ->sum('amount_paid');

        return [
            'term_id' => $termId,
            'total_due' => round($totalDue, 2),
            'total_paid' => round($totalPaid, 2),
            'total_balance' => round($totalDue - $totalPaid, 2),
        ];
    }
}
