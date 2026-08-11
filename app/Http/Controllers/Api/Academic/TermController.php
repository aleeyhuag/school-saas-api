<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\TermRequest;
use App\Models\Term;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TermController extends Controller
{
    /**
     * List terms. Supports optional ?academic_session_id= filter.
     */
    public function index()
    {
        $query = Term::query();

        if (request()->filled('academic_session_id')) {
            $query->where('academic_session_id', request()->input('academic_session_id'));
        }

        return $query->orderBy('start_date')->get();
    }

    public function store(TermRequest $request)
    {
        $validated = $request->validated();

        $term = DB::transaction(function () use ($validated) {
            if (! empty($validated['is_current'])) {
                Term::where('school_id', Auth::user()->school_id)
                    ->update(['is_current' => false]);
            }

            return Term::create($validated);
        });

        return response()->json($term, 201);
    }

    public function show(Term $term)
    {
        return $term;
    }

    public function update(TermRequest $request, Term $term)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $term) {
            if (! empty($validated['is_current'])) {
                Term::where('school_id', Auth::user()->school_id)
                    ->where('id', '!=', $term->id)
                    ->update(['is_current' => false]);
            }

            $term->update($validated);
        });

        return $term;
    }

    public function destroy(Term $term)
    {
        $term->delete();

        return response()->json(['message' => 'Term deleted.']);
    }

    /**
     * Convenience endpoint: get the school's single "current" term
     * directly, so clients don't have to fetch all terms and filter
     * client-side. Every dashboard/attendance/grading screen will
     * likely call this on load.
     */
    public function current()
    {
        $term = Term::where('school_id', Auth::user()->school_id)
            ->where('is_current', true)
            ->with('academicSession')
            ->first();

        if (! $term) {
            return response()->json(['message' => 'No current term has been set.'], 404);
        }

        return $term;
    }
}
