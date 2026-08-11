<?php

namespace App\Exports;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubjectScore;
use App\Services\AttendanceService;
use App\Services\ResultService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * One sheet per class. Wide format: one row per student, with a full
 * CA/Assignment/Exam/Total/Grade block of columns for EACH subject
 * the class takes — built for audit review (a reviewer can verify
 * the underlying components, not just the final total), not just a
 * quick summary.
 */
class ClassResultSheetExport implements FromArray, WithHeadings, WithTitle
{
    protected array $subjects;

    public function __construct(
        protected SchoolClass $schoolClass,
        protected int $termId,
        protected ResultService $resultService,
        protected AttendanceService $attendanceService,
    ) {
        $this->subjects = $this->schoolClass->subjects()->orderBy('name')->get()->all();
    }

    public function title(): string
    {
        // Excel sheet titles are capped at 31 chars and can't contain
        // some symbols — keep it short and safe.
        return str($this->schoolClass->full_name)->limit(28, '')->__toString();
    }

    public function headings(): array
    {
        $headings = ['Student Name', 'Admission No.'];

        foreach ($this->subjects as $subject) {
            $headings[] = "{$subject->name} - CA";
            $headings[] = "{$subject->name} - Assignment";
            $headings[] = "{$subject->name} - Exam";
            $headings[] = "{$subject->name} - Total";
            $headings[] = "{$subject->name} - Grade";
        }

        $headings[] = 'Grand Total';
        $headings[] = 'Average';
        $headings[] = 'Position';
        $headings[] = 'Attendance %';

        return $headings;
    }

    public function array(): array
    {
        $students = Student::where('school_class_id', $this->schoolClass->id)
            ->orderBy('first_name')
            ->get();

        $overall = $this->resultService->computeOverallPosition($this->schoolClass->id, $this->termId);

        $scoresByStudentSubject = SubjectScore::where('school_class_id', $this->schoolClass->id)
            ->where('term_id', $this->termId)
            ->get()
            ->groupBy('student_id');

        $rows = [];

        foreach ($students as $student) {
            $row = [$student->full_name, $student->admission_number];

            $studentScores = $scoresByStudentSubject->get($student->id, collect())->keyBy('subject_id');

            foreach ($this->subjects as $subject) {
                $score = $studentScores->get($subject->id);

                if (! $score) {
                    array_push($row, '—', '—', '—', '—', '—');
                    continue;
                }

                $computed = $this->resultService->computeSubjectResult($score);
                $row[] = $computed['ca']['score'] ?? '—';
                $row[] = $computed['assignment']['score'] ?? '—';
                $row[] = $computed['exam']['score'] ?? '—';
                $row[] = $computed['total_score'];
                $row[] = $computed['grade'] ?? '—';
            }

            $studentOverall = $overall->get($student->id);
            $attendance = $this->attendanceService->summaryFor($student->id, $this->termId);

            $row[] = $studentOverall['grand_total'] ?? '—';
            $row[] = $studentOverall['average'] ?? '—';
            $row[] = $studentOverall['position'] ?? '—';
            $row[] = $attendance['attendance_percentage'] ?? '—';

            $rows[] = $row;
        }

        return $rows;
    }
}
