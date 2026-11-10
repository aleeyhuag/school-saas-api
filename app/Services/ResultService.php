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
     * Per-request memoization for computeSubjectPositions() and
     * computeOverallPosition(). Both are class-wide computations —
     * every student in a class shares the exact same result — but were
     * being re-queried and re-computed from scratch for every single
     * student. That's invisible for one student's report card, but
     * ReportCardController::classBulk() calls computeStudentTermResult()
     * once per student in a loop, and that method calls both of these
     * internally — so generating a class of 40 students' report cards
     * was doing ~40x more SubjectScore queries than necessary, all
     * returning identical data. Laravel resolves ResultService once per
     * request (constructor injection is reused across the whole call
     * graph), so a plain instance-level cache is safe here and needs no
     * external cache store.
     */
    protected array $subjectPositionsCache = [];
    protected array $overallPositionCache = [];
    protected array $settingsCache = [];
    protected array $gradeBoundariesCache = [];

    /**
     * Compute one student's result for one subject in one term.
     * Returns raw scores, the weighted total (0-100), and the grade.
     */
    public function computeSubjectResult(SubjectScore $score): array
    {
        $settings = $this->settingsFor($score->school_id);

        $result = ['subject_id' => $score->subject_id];
        $total = 0;

        foreach ($this->weightsFor($settings) as $key => $weight) {
            $scoreValue = $score->{$key . '_score'};
            $max = $score->{$key . '_max'};
            $weighted = $this->weightedComponent($scoreValue, $max, $weight);
            $total += $weighted;

            $result[$key] = [
                'score' => $scoreValue,
                'max' => $max ?? $weight,
                'weight' => $weight,
                'weighted' => round($weighted, 1),
            ];
        }

        $total = round($total, 1);

        return $result + [
            'total_score' => $total,
            'grade' => $this->gradeFor($score->school_id, $total),
        ];
    }

    /**
     * The score columns this school actually uses, in the order they should be
     * shown: each CA (1-3), then Assignment (if it has a weight), then Exam.
     * Used by score entry, the marksheet, report cards and exports so they all
     * agree. Returns [['key' => 'ca', 'label' => 'CA 1', 'weight' => 10], ...].
     */
    public function componentsFor(int $schoolId): array
    {
        $settings = $this->settingsFor($schoolId);
        $weights = $this->weightsFor($settings);
        $caCount = max(1, min(3, (int) ($settings->ca_count ?? 1)));
        $labelled = $caCount > 1;

        $components = [];
        foreach (['ca', 'ca2', 'ca3'] as $i => $key) {
            if ($i < $caCount && $weights[$key] > 0) {
                $components[] = ['key' => $key, 'label' => $labelled ? 'CA ' . ($i + 1) : 'CA', 'weight' => $weights[$key]];
            }
        }
        if ($weights['assignment'] > 0) {
            $components[] = ['key' => 'assignment', 'label' => 'Assignment', 'weight' => $weights['assignment']];
        }
        $components[] = ['key' => 'exam', 'label' => 'Exam', 'weight' => $weights['exam']];

        return $components;
    }

    /**
     * Checks entered scores against this school's weights and fills in each
     * component's "out of" value. Scores are entered directly out of the
     * component's weight (e.g. a CA weighted 10 is marked out of 10), so the
     * "out of" no longer comes from the teacher. A client that still sends an
     * explicit *_max (an older queued offline save) is honoured.
     *
     * Returns [normalizedInput, errors] where errors is [field => message].
     */
    public function normalizeScoreInput(int $schoolId, array $input): array
    {
        $weights = $this->weightsFor($this->settingsFor($schoolId));
        $labels = collect($this->componentsFor($schoolId))->pluck('label', 'key');
        $legacyMax = ['ca' => 20, 'assignment' => 10, 'exam' => 100];

        $out = $input;
        $errors = [];

        foreach ($weights as $key => $weight) {
            $scoreField = $key . '_score';
            $maxField = $key . '_max';

            if ($weight <= 0) {
                // This school does not use this component.
                $out[$scoreField] = null;
                if (isset($legacyMax[$key])) {
                    $out[$maxField] = $input[$maxField] ?? $legacyMax[$key];
                } else {
                    $out[$maxField] = null;
                }
                continue;
            }

            $max = isset($input[$maxField]) && $input[$maxField] !== '' ? (float) $input[$maxField] : (float) $weight;
            $out[$maxField] = $max;

            $value = $input[$scoreField] ?? null;
            if ($value !== null && $value !== '' && (float) $value > $max) {
                $label = $labels[$key] ?? strtoupper($key);
                $shown = rtrim(rtrim(number_format($max, 2, '.', ''), '0'), '.');
                $errors[$scoreField] = "{$label} cannot be more than {$shown}.";
            }
        }

        return [$out, $errors];
    }

    /** [component key => weight] for a settings row. Keys match the score columns. */
    public function weightsFor(AssessmentSetting $settings): array
    {
        $caCount = max(1, min(3, (int) ($settings->ca_count ?? 1)));

        return [
            'ca' => (int) $settings->ca_weight,
            'ca2' => $caCount >= 2 ? (int) ($settings->ca2_weight ?? 0) : 0,
            'ca3' => $caCount >= 3 ? (int) ($settings->ca3_weight ?? 0) : 0,
            'assignment' => (int) $settings->assignment_weight,
            'exam' => (int) $settings->exam_weight,
        ];
    }

    /**
     * Compute the class position for a single subject: ranks every
     * student in the class by their total score for that subject/term.
     * Returns a map of student_id => position (1 = highest).
     */
    public function computeSubjectPositions(int $schoolClassId, int $subjectId, int $termId): Collection
    {
        $cacheKey = "{$schoolClassId}:{$subjectId}:{$termId}";

        if (isset($this->subjectPositionsCache[$cacheKey])) {
            return $this->subjectPositionsCache[$cacheKey];
        }

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

        $result = $totals->map(function ($row) use (&$position, &$lastScore, &$rank) {
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

        return $this->subjectPositionsCache[$cacheKey] = $result;
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
        $cacheKey = "{$schoolClassId}:{$termId}";

        if (isset($this->overallPositionCache[$cacheKey])) {
            return $this->overallPositionCache[$cacheKey];
        }

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

        $result = $totals->map(function ($row) use (&$position, &$lastTotal, &$rank) {
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

        return $this->overallPositionCache[$cacheKey] = $result;
    }

    // ---- internals ----

    protected function weightedComponent(?float $score, ?float $max, int $weight): float
    {
        if ($score === null || $weight <= 0) {
            return 0;
        }

        // Scores are entered out of the component's weight, so a missing "out of"
        // simply means the weight itself.
        $max = ($max === null || $max <= 0) ? $weight : $max;

        return ($score / $max) * $weight;
    }

    protected function settingsFor(int $schoolId): AssessmentSetting
    {
        if (isset($this->settingsCache[$schoolId])) {
            return $this->settingsCache[$schoolId];
        }

        // Result reads must not perform a write. The old firstOrCreate()
        // here meant a marksheet/report-card GET could unexpectedly try to
        // INSERT an assessment-settings row. Apart from unnecessary DB work,
        // that can fail under a restricted production DB role and turn an
        // otherwise read-only result request into a 500.
        $settings = AssessmentSetting::where('school_id', $schoolId)->first();

        if (! $settings) {
            $settings = new AssessmentSetting([
                'school_id' => $schoolId,
                'ca_count' => 1,
                'ca_weight' => 30,
                'ca2_weight' => 0,
                'ca3_weight' => 0,
                'assignment_weight' => 10,
                'exam_weight' => 60,
            ]);
        }

        return $this->settingsCache[$schoolId] = $settings;
    }

    protected function gradeFor(int $schoolId, float $totalScore): ?string
    {
        if (! array_key_exists($schoolId, $this->gradeBoundariesCache)) {
            $this->gradeBoundariesCache[$schoolId] = GradeBoundary::where('school_id', $schoolId)
                ->orderBy('min_score')
                ->get(['grade', 'min_score', 'max_score']);
        }

        $boundary = $this->gradeBoundariesCache[$schoolId]
            ->first(fn ($row) => $totalScore >= (float) $row->min_score && $totalScore <= (float) $row->max_score);

        return $boundary?->grade;
    }
}
