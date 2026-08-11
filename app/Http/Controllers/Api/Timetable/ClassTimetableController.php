<?php

namespace App\Http\Controllers\Api\Timetable;

use App\Http\Controllers\Controller;
use App\Models\ClassTimetableEntry;
use Illuminate\Support\Facades\Auth;

/**
 * Class timetable — owned by Principal (create/edit/delete).
 * Proprietor, teachers, students, parents can all view it, but only
 * Principal can change it.
 *
 * Permissions are split at the route level:
 *  - GET /timetable (read-only, broad roles) — index()
 *  - POST/PUT/DELETE (Principal-only)        — store/update/destroy()
 */
class ClassTimetableController extends Controller
{
    /**
     * Returns the full timetable for one class for a session —
     * grouped by day (1-5) so the frontend can render a grid without
     * client-side pivoting.
     */
    public function index()
    {
        request()->validate([
            'school_class_id' => ['required', 'integer'],
            'academic_session_id' => ['required', 'integer'],
        ]);

        $entries = ClassTimetableEntry::with(['subject', 'teacher:id,name'])
            ->where('school_class_id', request()->integer('school_class_id'))
            ->where('academic_session_id', request()->integer('academic_session_id'))
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get();

        // Group into a day→[entries] map (keyed 1-5) for easy grid
        // rendering on the frontend.
        return $entries->groupBy('day_of_week')->map->values();
    }

    /**
     * Returns all timetable entries for a teacher — so a teacher
     * logs in and immediately sees their full weekly schedule across
     * all classes they teach.
     */
    public function mySchedule()
    {
        request()->validate([
            'academic_session_id' => ['required', 'integer'],
        ]);

        return ClassTimetableEntry::with(['subject', 'schoolClass'])
            ->where('user_id', Auth::id())
            ->where('academic_session_id', request()->integer('academic_session_id'))
            ->orderBy('day_of_week')
            ->orderBy('period')
            ->get()
            ->groupBy('day_of_week')
            ->map->values();
    }

    public function store()
    {
        $validated = request()->validate([
            'school_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'day_of_week' => ['required', 'integer', 'between:1,5'],
            'period' => ['required', 'integer', 'min:1'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'room' => ['nullable', 'string', 'max:100'],
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
        ]);

        $entry = ClassTimetableEntry::updateOrCreate(
            [
                'school_id' => Auth::user()->school_id,
                'school_class_id' => $validated['school_class_id'],
                'day_of_week' => $validated['day_of_week'],
                'period' => $validated['period'],
                'academic_session_id' => $validated['academic_session_id'],
            ],
            [
                ...$validated,
                'school_id' => Auth::user()->school_id,
            ]
        );

        return response()->json($entry->load(['subject', 'teacher:id,name']), 201);
    }

    public function destroy(ClassTimetableEntry $classTimetableEntry)
    {
        $classTimetableEntry->delete();
        return response()->json(['message' => 'Entry removed.']);
    }
}
