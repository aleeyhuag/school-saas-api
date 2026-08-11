<?php

namespace App\Exports;

use App\Services\AttendanceService;
use App\Services\ResultService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SchoolResultsExport implements WithMultipleSheets
{
    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\SchoolClass>  $includedClasses
     * @param  string[]  $skippedClassNames
     */
    public function __construct(
        protected $includedClasses,
        protected int $termId,
        protected array $skippedClassNames,
        protected ResultService $resultService,
        protected AttendanceService $attendanceService,
    ) {}

    public function sheets(): array
    {
        $sheets = $this->includedClasses
            ->map(fn ($class) => new ClassResultSheetExport(
                $class, $this->termId, $this->resultService, $this->attendanceService
            ))
            ->all();

        if (! empty($this->skippedClassNames)) {
            $sheets[] = new SkippedClassesSheetExport($this->skippedClassNames);
        }

        return $sheets;
    }
}
