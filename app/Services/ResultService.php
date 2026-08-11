<?php

namespace App\Services;

use App\Models\AssessmentSetting;
use App\Models\GradeBoundary;
use App\Models\SchoolClass;
use App\Models\SubjectScore;
use Illuminate\Support\Collection;

/**
 * The grading engine. This is the ONLY place total scores, grades,
 * and positions get computed — every controller/endpoint that needs
 * a result calls into this service rather than re-implementing the
 * math. That way a fix or change here (e.g. adjusting how missing
 * scores are handled) automatically applies everywhere results are
 * shown: report cards, class result sheets, dashboards.
 */
class ResultService
{
    /**
     * Compute one student's result for one subject in one term.
     * Returns raw scores, the weighted total (0-100), and the grade.
     */
    public function computeSubjectResult(SubjectScore $score): array
    {
        $settings = $this->settingsFor($score->school_id);

        $caWeighted = $this->weightedComponent($score->ca_score, $score->ca_max, $settings->ca_weight);
        $assignmentWeighted = $this->weightedComponent($score->assignment_score, $score->assignment_max, $settings->assignment_weight);
        $examWeighted = $this->weightedComponent($score->exam_score, $score->exam_max, $settings->exam_weight);

        $total = round($caWeighted + $assignmentWeighted + $examWeighted, 1);

        return [
            'subject_id' => $score->subject_id,
            'ca' => [
                'score' => $score->ca_score,
                'max' => $score->ca_max,
                'weighted' => round($caWeighted, 1),
            ],
            'assignment' => [
                'score' => $score->assignment_score,
                'max' => $score->assignment_max,
                'weighted' => round($assignmentWeighted, 1),
            ],
            'exam' => [
                'score' => $score->exam_score,
                'max' => $score->exam_max,
                'weighted' => round($examWeighted, 1),
            ],
            'total_score' => $total,
            'grade' => $this->gradeFor($score->school_id, $total),
        ];
    }

    /**
     * Compute the class position for a single subject: ranks every
     * student in the class by their total score for that subject/term.
     * Returns a map of student_id => position (1 = highest).
     */
    public function computeSubjectPositions(int $schoolClassId, int $subjectId, int $termId): Collection
    {
        $scores = SubjectScore::where('school_class_id', $schoolClassId)
            ->where('subject_id', $subjectId)
            ->where('term_id', $termId)
            ->get();

        $totals = $scores->map(function ($score) {
            return [
                'student_id' => $score->student_id,
                'total_score' => $this->computeSubjectResult($score)['total_score'],
            ];
        })->sortByDesc('total_score')->values();

        $position = 0;
        $lastScore = null;
        $rank = 0;

        return $totals->map(function ($row) use (&$position, &$lastScore, &$rank) {
            $position++;
            if ($row['total_score'] !== $lastScore) {
                $rank = $position;
                $lastScore = $row['total_score'];
            }
            return [
                'student_id' => $row['student_id'],
                'total_score' => $row['total_score'],
                'position' => $rank, // ties share the same position (standard competition ranking)
            ];
        })->keyBy('student_id');
    }

    /**
     * Full report card for one student: every subject's result for the
     * term, the overall average, and the overall class position
     * (ranked by average total score across all subjects).
     */
    public function computeStudentTermResult(int $studentId, int $schoolClassId, int $termId): array
    {
        $scores = SubjectScore::where('student_id', $studentId)
            ->where('term_id', $termId)
            ->with('subject')
            ->get();

        $subjects = $scores->map(function ($score) use ($schoolClassId, $termId) {
            $result = $this->computeSubjectResult($score);
            $positions = $this->computeSubjectPositions($schoolClassId, $score->subject_id, $termId);

            return array_merge($result, [
                'subject_name' => $score->subject->name,
                'position_in_subject' => $positions->get($score->student_id)['position'] ?? null,
                'class_size' => $positions->count(),
                'teacher_comment' => $score->teacher_comment,
            ]);
        });

        $overall = $this->computeOverallPosition($schoolClassId, $termId);
        $own = $overall->get($studentId);

        return [
            'student_id' => $studentId,
            'term_id' => $termId,
            'subjects' => $subjects->values(),
            'grand_total' => $own['grand_total'] ?? round($subjects->sum('total_score'), 1),
            'overall_average' => $own['average'] ?? null,
            'overall_position' => $own['position'] ?? null,
            'class_size' => $overall->count(),
        ];
    }

    /**
     * Ranks every student in a class by their GRAND TOTAL (sum, not
     * average) of subject scores for the term.
     *
     * Averaging only the subjects a student happens to have scores
     * for is unfair mid-term: a student with one subject entered at
     * 66 would outrank a student with two subjects entered averaging
     * 60 (67.5 + 52.5), even though the second student has genuinely
     * scored more. Ranking by the sum fixes that directly. We also
     * report an "average" alongside it, but divided by the class's
     * FULL subject count (not just the subjects entered so far) so
     * it stays consistent with the total's ordering rather than
     * reintroducing the same bias in the displayed figure.
     */
    public function computeOverallPosition(int $schoolClassId, int $termId): Collection
    {
        $classSubjectCount = SchoolClass::find($schoolClassId)?->subjects()->count() ?? 0;

        $scores = SubjectScore::where('school_class_id', $schoolClassId)
            ->where('term_id', $termId)
            ->get()
            ->groupBy('student_id');

        $totals = $scores->map(function ($studentScores, $studentId) use ($classSubjectCount) {
            $grandTotal = round(
                $studentScores->sum(fn ($s) => $this->computeSubjectResult($s)['total_score']),
                1
            );
            $denominator = $classSubjectCount > 0 ? $classSubjectCount : $studentScores->count();

            return [
                'student_id' => (int) $studentId,
                'grand_total' => $grandTotal,
                'average' => $denominator > 0 ? round($grandTotal / $denominator, 1) : null,
            ];
        })->sortByDesc('grand_total')->values();

        $position = 0;
        $lastTotal = null;
        $rank = 0;

        return $totals->map(function ($row) use (&$position, &$lastTotal, &$rank) {
            $position++;
            if ($row['grand_total'] !== $lastTotal) {
                $rank = $position;
                $lastTotal = $row['grand_total'];
            }
            return [
                'student_id' => $row['student_id'],
                'grand_total' => $row['grand_total'],
                'average' => $row['average'],
                'position' => $rank,
            ];
        })->keyBy('student_id');
    }

    // ---- internals ----

    protected function weightedComponent(?float $score, ?float $max, int $weight): float
    {
        if ($score === null || $max === null || $max <= 0) {
            return 0;
        }

        return ($score / $max) * $weight;
    }

    protected function settingsFor(int $schoolId): AssessmentSetting
    {
        return AssessmentSetting::firstOrCreate(
            ['school_id' => $schoolId],
            ['ca_weight' => 30, 'assignment_weight' => 10, 'exam_weight' => 60]
        );
    }

    protected function gradeFor(int $schoolId, float $totalScore): ?string
    {
        $boundary = GradeBoundary::where('school_id', $schoolId)
            ->where('min_score', '<=', $totalScore)
            ->where('max_score', '>=', $totalScore)
            ->first();

        return $boundary?->grade;
    }
}
