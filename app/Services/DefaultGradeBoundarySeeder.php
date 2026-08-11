<?php

namespace App\Services;

use App\Models\GradeBoundary;

/**
 * Seeds the standard Nigerian WAEC-style grade boundaries for a newly
 * registered school, so a proprietor never has to configure grading
 * from a blank slate. They can edit/replace these anytime via the
 * grade-boundaries endpoints.
 *
 * Call this once, right after a school is created — e.g. from inside
 * RegisterSchoolController, right after School::create(...).
 */
class DefaultGradeBoundarySeeder
{
    public static function seedFor(int $schoolId): void
    {
        $defaults = [
            ['grade' => 'A', 'remark' => 'Excellent', 'min_score' => 70, 'max_score' => 100],
            ['grade' => 'B', 'remark' => 'Very Good', 'min_score' => 60, 'max_score' => 69],
            ['grade' => 'C', 'remark' => 'Good', 'min_score' => 50, 'max_score' => 59],
            ['grade' => 'D', 'remark' => 'Pass', 'min_score' => 40, 'max_score' => 49],
            ['grade' => 'F', 'remark' => 'Fail', 'min_score' => 0, 'max_score' => 39],
        ];

        foreach ($defaults as $boundary) {
            GradeBoundary::create(array_merge($boundary, ['school_id' => $schoolId]));
        }
    }
}
