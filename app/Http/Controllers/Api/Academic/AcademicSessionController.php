<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\AcademicSessionRequest;
use App\Models\AcademicSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function index()
    {
        return AcademicSession::with('terms')->orderBy('start_date', 'desc')->get();
    }

    public function store(AcademicSessionRequest $request)
    {
        $validated = $request->validated();

        $session = DB::transaction(function () use ($validated) {
            // Only one session can be "current" per school at a time
            if (! empty($validated['is_current'])) {
                AcademicSession::where('school_id', Auth::user()->school_id)
                    ->update(['is_current' => false]);
            }

            return AcademicSession::create($validated);
        });

        return response()->json($session, 201);
    }

    public function show(AcademicSession $academicSession)
    {
        return $academicSession->load('terms');
    }

    public function update(AcademicSessionRequest $request, AcademicSession $academicSession)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $academicSession) {
            if (! empty($validated['is_current'])) {
                AcademicSession::where('school_id', Auth::user()->school_id)
                    ->where('id', '!=', $academicSession->id)
                    ->update(['is_current' => false]);
            }

            $academicSession->update($validated);
        });

        return $academicSession;
    }

    public function destroy(AcademicSession $academicSession)
    {
        $academicSession->delete();

        return response()->json(['message' => 'Academic session deleted.']);
    }
}
