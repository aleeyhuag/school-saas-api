<?php

namespace App\Services;

use App\Models\Attendance;

/**
 * Single source of truth for "what's a student's attendance summary
 * for a term" — used by the live API (AttendanceController), the
 * individual report card PDF, and the whole-school results
 * spreadsheet. Whole-day attendance only (subject_id null); period
 * attendance is supplementary monitoring, not part of the official
 * record.
 */
class AttendanceService
{
    public function summaryFor(int $studentId, int $termId): array
    {
        $counts = Attendance::where('student_id', $studentId)
            ->where('term_id', $termId)
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalDays = $counts->sum();
        $present = $counts->get('present', 0) + $counts->get('late', 0);

        return [
            'total_days_marked' => $totalDays,
            'breakdown' => $counts,
            'attendance_percentage' => $totalDays > 0
                ? round(($present / $totalDays) * 100, 1)
                : null,
        ];
    }

    /**
     * The same present/late-vs-total calculation, but aggregated
     * across every student in the school for a term — used by the
     * Health Dashboard, not tied to any one student.
     */
    public function schoolWideSummary(int $termId): array
    {
        $counts = Attendance::where('term_id', $termId)
            ->whereNull('subject_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalMarks = $counts->sum();
        $present = $counts->get('present', 0) + $counts->get('late', 0);

        return [
            'total_marks' => $totalMarks,
            'attendance_percentage' => $totalMarks > 0
                ? round(($present / $totalMarks) * 100, 1)
                : null,
        ];
    }
}
