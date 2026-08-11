<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Appended as the last sheet of a whole-school export whenever one or
 * more classes were left out because their results for the term
 * haven't been approved yet — so the reviewer knows the workbook is
 * intentionally incomplete, not broken.
 */
class SkippedClassesSheetExport implements FromArray, WithHeadings, WithTitle
{
    public function __construct(protected array $skippedClassNames) {}

    public function title(): string
    {
        return 'Not Yet Approved';
    }

    public function headings(): array
    {
        return ['Class', 'Reason'];
    }

    public function array(): array
    {
        return collect($this->skippedClassNames)
            ->map(fn ($name) => [$name, 'Results not yet approved by the class teacher'])
            ->all();
    }
}
