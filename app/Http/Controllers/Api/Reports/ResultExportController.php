<?php

namespace App\Http\Controllers\Api\Reports;

use App\Exports\SchoolResultsExport;
use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\TermResultApproval;
use App\Services\AttendanceService;
use App\Services\ResultService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The admin-facing results export (Proprietor, Principal, Exam
 * Officer) — a single .xlsx workbook, one sheet per class, full
 * CA/Assignment/Exam breakdown per subject for audit review. This is
 * deliberately a spreadsheet, not a bundle of PDFs — those are for
 * handing to individual students/parents (see ReportCardController);
 * this is for reviewing results data across many students at once.
 */
class ResultExportController extends Controller
{
    public function __construct(
        protected ResultService $resultService,
        protected AttendanceService $attendanceService,
    ) {}

    public function export()
    {
        request()->validate([
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', Auth::user()->school_id)],
            'scope' => ['required', 'in:school,class'],
            'school_class_id' => ['required_if:scope,class', 'nullable', 'integer', Rule::exists('school_classes', 'id')->where('school_id', Auth::user()->school_id)],
        ]);

        $termId = (int) request()->input('term_id');
        $scope = request()->input('scope');

        if ($scope === 'class') {
            $schoolClass = SchoolClass::findOrFail(request()->input('school_class_id'));

            $isApproved = TermResultApproval::where('school_class_id', $schoolClass->id)
                ->where('term_id', $termId)
                ->exists();

            if (! $isApproved) {
                throw ValidationException::withMessages([
                    'school_class_id' => ['This class\'s results haven\'t been approved yet.'],
                ]);
            }

            $includedClasses = collect([$schoolClass]);
            $skipped = [];
            $filename = str($schoolClass->full_name)->slug().'-results.xlsx';
        } else {
            $allClasses = SchoolClass::orderBy('name')->get();

            $approvedClassIds = TermResultApproval::where('term_id', $termId)
                ->pluck('school_class_id')
                ->all();

            $includedClasses = $allClasses->whereIn('id', $approvedClassIds)->values();
            $skipped = $allClasses->whereNotIn('id', $approvedClassIds)->pluck('full_name')->all();

            if ($includedClasses->isEmpty()) {
                throw ValidationException::withMessages([
                    'term_id' => ['No class has approved results for this term yet.'],
                ]);
            }

            $filename = 'school-results.xlsx';
        }

        return Excel::download(
            new SchoolResultsExport($includedClasses, $termId, $skipped, $this->resultService, $this->attendanceService),
            $filename
        );
    }
}
