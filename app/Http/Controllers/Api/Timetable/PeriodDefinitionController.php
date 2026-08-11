<?php

namespace App\Http\Controllers\Api\Timetable;

use App\Http\Controllers\Controller;
use App\Models\ClassTimetableEntry;
use App\Models\PeriodDefinition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Defines what a "period" actually MEANS for this school — its start
 * and end time. Every school runs a different bell schedule, so
 * "Period 1" isn't a fixed slot platform-wide; the Principal sets it
 * once here, and every timetable view (the editable class timetable
 * grid, a teacher's My Schedule, the read-only parent/student view)
 * pulls the same times instead of each cell needing its own.
 *
 * Capped at MAX_PERIODS per school. Removing a period is blocked if
 * the class timetable already has entries filed against it, so an
 * in-use slot can't silently disappear out from under a live
 * schedule.
 */
class PeriodDefinitionController extends Controller
{
    public const MAX_PERIODS = 10;

    public function index()
    {
        return PeriodDefinition::orderBy('period_number')->get();
    }

    /**
     * Adds the next period, or — if period_number already exists —
     * updates its times instead, so the Principal can correct a
     * period without deleting and re-adding it.
     */
    public function store()
    {
        $validated = request()->validate([
            'period_number' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PERIODS],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $alreadyExists = PeriodDefinition::where('period_number', $validated['period_number'])->exists();

        if (! $alreadyExists && PeriodDefinition::count() >= self::MAX_PERIODS) {
            throw ValidationException::withMessages([
                'period_number' => ['A school can have at most '.self::MAX_PERIODS.' periods.'],
            ]);
        }

        $period = PeriodDefinition::updateOrCreate(
            [
                'school_id' => Auth::user()->school_id,
                'period_number' => $validated['period_number'],
            ],
            [
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
            ]
        );

        return response()->json($period, 201);
    }

    public function destroy(PeriodDefinition $periodDefinition)
    {
        $inUse = ClassTimetableEntry::where('period', $periodDefinition->period_number)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'period_number' => ['This period still has timetable slots filled in — clear them first.'],
            ]);
        }

        $periodDefinition->delete();

        return response()->json(['message' => 'Period removed.']);
    }
}
