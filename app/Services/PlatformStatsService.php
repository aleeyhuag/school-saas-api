<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cross-tenant platform stats for Super Admin — deliberately uses
 * DB::table() throughout rather than Eloquent models. That's the
 * OPPOSITE rule from SchoolHealthService (Stage 35), which had to
 * avoid raw queries specifically to stay scoped to one school. Here
 * the whole point is seeing across every school at once, so raw
 * aggregate SQL is the correct tool, not a scoping bug.
 */
class PlatformStatsService
{
    public function build(): array
    {
        return [
            'metrics' => $this->metrics(),
            'audit_issues' => $this->auditIssues(),
        ];
    }

    protected function metrics(): array
    {
        $totalSchools = DB::table('schools')->count();
        $activeSchools = DB::table('schools')->where('is_active', true)->count();

        return [
            'total_schools' => $totalSchools,
            'active_schools' => $activeSchools,
            'disabled_schools' => $totalSchools - $activeSchools,
            'total_students' => DB::table('students')->count(),
            'total_staff' => DB::table('users')
                ->join('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', ['proprietor', 'principal', 'bursar', 'exam_officer', 'teacher'])
                ->distinct()
                ->count('users.id'),
            'new_schools_last_7_days' => DB::table('schools')->where('created_at', '>=', now()->subDays(7))->count(),
            'new_schools_last_30_days' => DB::table('schools')->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Platform-level concerns — deliberately broader-strokes than the
     * per-school Health Dashboard (Stage 35), which is detailed and
     * actionable for THAT school's own admins. This is about spotting
     * which schools might need an onboarding nudge, not fixing any
     * one school's configuration.
     */
    protected function auditIssues(): array
    {
        $issues = [];

        $schoolsWithNoStudents = DB::table('schools')
            ->leftJoin('students', 'students.school_id', '=', 'schools.id')
            ->select('schools.id')
            ->groupBy('schools.id')
            ->havingRaw('COUNT(students.id) = 0')
            ->get()
            ->count();

        if ($schoolsWithNoStudents > 0) {
            $issues[] = $this->issue('schools_no_students', 'Schools registered with zero students added yet', $schoolsWithNoStudents);
        }

        $schoolsOnlyProprietor = DB::table('schools')
            ->leftJoin('users', 'users.school_id', '=', 'schools.id')
            ->select('schools.id')
            ->groupBy('schools.id')
            ->havingRaw('COUNT(users.id) <= 1')
            ->get()
            ->count();

        if ($schoolsOnlyProprietor > 0) {
            $issues[] = $this->issue('schools_only_proprietor', 'Schools with only the Proprietor account — no staff invited yet', $schoolsOnlyProprietor);
        }

        $schoolsWithNoCurrentTerm = DB::table('schools')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('terms')
                    ->whereColumn('terms.school_id', 'schools.id')
                    ->where('terms.is_current', true);
            })
            ->count();

        if ($schoolsWithNoCurrentTerm > 0) {
            $issues[] = $this->issue('schools_no_current_term', 'Schools with no current academic term configured', $schoolsWithNoCurrentTerm);
        }

        $disabledSchools = DB::table('schools')->where('is_active', false)->count();
        if ($disabledSchools > 0) {
            $issues[] = $this->issue('schools_disabled', 'Schools currently disabled', $disabledSchools);
        }

        return $issues;
    }

    protected function issue(string $key, string $label, int $count): array
    {
        return ['key' => $key, 'label' => $label, 'count' => $count];
    }
}
