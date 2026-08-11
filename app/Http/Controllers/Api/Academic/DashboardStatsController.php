<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\FeeService;
use Illuminate\Support\Facades\Auth;

/**
 * Powers the Proprietor/Principal Overview page's stat cards —
 * previously hardcoded "—" placeholders. One combined endpoint rather
 * than three separate calls, since these numbers are always shown
 * together.
 */
class DashboardStatsController extends Controller
{
    public function __invoke(FeeService $feeService)
    {
        $schoolId = Auth::user()->school_id;

        $studentsCount = Student::count(); // BelongsToSchool already scopes this
        $staffCount = User::where('school_id', $schoolId)->count();

        $currentTermId = Term::where('school_id', $schoolId)->where('is_current', true)->value('id');
        $feeSummary = $currentTermId ? $feeService->schoolFeeSummary($schoolId, $currentTermId) : null;

        return response()->json([
            'students_count' => $studentsCount,
            'staff_count' => $staffCount,
            'fee_collected_this_term' => $feeSummary['total_paid'] ?? null,
            'fee_balance_this_term' => $feeSummary['total_balance'] ?? null,
            'has_current_term' => $currentTermId !== null,
        ]);
    }
}
