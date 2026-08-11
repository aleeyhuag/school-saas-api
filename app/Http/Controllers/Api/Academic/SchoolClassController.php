<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SchoolClassRequest;
use App\Models\SchoolClass;
use Illuminate\Http\Request;

class SchoolClassController extends Controller
{
    /**
     * List all classes for the logged-in user's school.
     * (BelongsToSchool trait auto-scopes this — no manual filtering needed.)
     */
    public function index()
    {
        return SchoolClass::with('subjects')->orderBy('level')->get();
    }

    public function store(SchoolClassRequest $request)
    {
        $class = SchoolClass::create($request->validated());

        return response()->json($class, 201);
    }

    public function show(SchoolClass $schoolClass)
    {
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

    /**
     * Attach one or more subjects to this class (replaces the current list).
     * Body: { "subject_ids": [1, 2, 3] }
     */
    public function syncSubjects(Request $request, SchoolClass $schoolClass)
    {
        $request->validate([
            'subject_ids' => ['required', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
        ]);

        $schoolClass->subjects()->sync($request->input('subject_ids'));

        return $schoolClass->load('subjects');
    }
}
