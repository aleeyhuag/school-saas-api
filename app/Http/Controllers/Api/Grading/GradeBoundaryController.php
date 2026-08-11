<?php

namespace App\Http\Controllers\Api\Grading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\GradeBoundaryRequest;
use App\Models\GradeBoundary;

class GradeBoundaryController extends Controller
{
    public function index()
    {
        return GradeBoundary::orderByDesc('min_score')->get();
    }

    public function store(GradeBoundaryRequest $request)
    {
        $boundary = GradeBoundary::create($request->validated());

        return response()->json($boundary, 201);
    }

    public function update(GradeBoundaryRequest $request, GradeBoundary $gradeBoundary)
    {
        $gradeBoundary->update($request->validated());

        return $gradeBoundary;
    }

    public function destroy(GradeBoundary $gradeBoundary)
    {
        $gradeBoundary->delete();

        return response()->json(['message' => 'Grade boundary deleted.']);
    }
}
