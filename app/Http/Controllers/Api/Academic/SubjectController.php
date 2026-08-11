<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\SubjectRequest;
use App\Models\Subject;

class SubjectController extends Controller
{
    public function index()
    {
        return Subject::orderBy('name')->get();
    }

    public function store(SubjectRequest $request)
    {
        $subject = Subject::create($request->validated());

        return response()->json($subject, 201);
    }

    public function show(Subject $subject)
    {
        return $subject->load('schoolClasses');
    }

    public function update(SubjectRequest $request, Subject $subject)
    {
        $subject->update($request->validated());

        return $subject;
    }

    public function destroy(Subject $subject)
    {
        $subject->delete();

        return response()->json(['message' => 'Subject deleted.']);
    }
}
