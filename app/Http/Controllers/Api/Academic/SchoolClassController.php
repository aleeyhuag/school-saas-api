<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SchoolClassRequest;
use App\Models\SchoolClass;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SchoolClassController extends Controller
{
    /**
     * List classes. Teachers only receive classes where they have an
     * assignment; management roles retain the full school list.
     */
    public function index()
    {
        $query = SchoolClass::with('subjects')->orderBy('level');

        if (Auth::user()->hasRole('teacher')) {
            $classIds = TeacherAssignment::where('school_id', Auth::user()->school_id)
                ->where('user_id', Auth::user()->id)
                ->pluck('school_class_id')
                ->unique();

            $query->whereIn('id', $classIds);
        }

        return $query->get();
    }

    public function store(SchoolClassRequest $request)
    {
        $class = SchoolClass::create($request->validated());

        return response()->json($class, 201);
    }

    public function show(SchoolClass $schoolClass)
    {
        if (Auth::user()->hasRole('teacher')) {
            abort_unless(
                TeacherAssignment::where('school_id', Auth::user()->school_id)
                    ->where('user_id', Auth::user()->id)
                    ->where('school_class_id', $schoolClass->id)
                    ->exists(),
                403,
                'You are not assigned to this class.'
            );
        }

        return $schoolClass->load('subjects', 'students');
    }

    public function update(SchoolClassRequest $request, SchoolClass $schoolClass)
    {
        $schoolClass->update($request->validated());

        return $schoolClass;
    }

    public function destroy(SchoolClass $schoolClass)
    {
        $schoolClass->delete();

        return response()->json(['message' => 'Class deleted.']);
    }

    public function syncSubjects(Request $request, SchoolClass $schoolClass)
    {
        $request->validate([
            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['integer', Rule::exists('subjects', 'id')->where('school_id', $schoolClass->school_id)],
        ]);

        $schoolClass->subjects()->sync($request->input('subject_ids'));

        return $schoolClass->load('subjects');
    }
}
