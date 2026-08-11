<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\TeacherAssignmentRequest;
use App\Models\TeacherAssignment;
use Illuminate\Validation\ValidationException;

class TeacherAssignmentController extends Controller
{
    /**
     * List all teacher-class-subject assignments for the school.
     * Supports optional ?school_class_id= or ?user_id= filters.
     */
    public function index()
    {
        $query = TeacherAssignment::with(['teacher', 'schoolClass', 'subject']);

        if (request()->filled('school_class_id')) {
            $query->where('school_class_id', request()->input('school_class_id'));
        }

        if (request()->filled('user_id')) {
            $query->where('user_id', request()->input('user_id'));
        }

        return $query->get();
    }

    /**
     * Assign a teacher to a class (as class teacher) and/or a
     * class+subject combination (as subject teacher).
     */
    public function store(TeacherAssignmentRequest $request)
    {
        $validated = $request->validated();

        // Friendly check before hitting the database's unique
        // constraint — avoids a raw SQL error reaching the client.
        $alreadyExists = TeacherAssignment::where('user_id', $validated['user_id'])
            ->where('school_class_id', $validated['school_class_id'])
            ->where('subject_id', $validated['subject_id'] ?? null)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'user_id' => ['This teacher is already assigned to this class/subject.'],
            ]);
        }

        $assignment = TeacherAssignment::create($validated);

        return response()->json($assignment->load(['teacher', 'schoolClass', 'subject']), 201);
    }

    public function destroy(TeacherAssignment $teacherAssignment)
    {
        $teacherAssignment->delete();

        return response()->json(['message' => 'Assignment removed.']);
    }

    /**
     * A teacher's OWN assignments — unlike index() above (admin-only),
     * this is scoped to the logged-in user regardless of their role,
     * so a Class Teacher or Subject Teacher can find out which
     * class(es)/subject(s) they're actually assigned to without
     * needing admin-level access to the full assignments list.
     */
    public function mine()
    {
        return TeacherAssignment::where('user_id', request()->user()->id)
            ->with(['schoolClass', 'subject'])
            ->get();
    }
}
