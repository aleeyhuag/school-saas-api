<?php

namespace App\Http\Middleware;

use App\Models\CbtExam;
use App\Models\Term;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentCbtExamContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $currentTerm = Term::where('school_id', $user->school_id)
            ->whereHas('academicSession', fn ($q) => $q->where('is_current', true))
            ->first();

        if (! $currentTerm) {
            return response()->json([
                'message' => 'No current academic term is configured for this school.',
                'code' => 'current_term_missing',
            ], 409);
        }

        if ($request->has('term_id') && (int) $request->input('term_id') !== (int) $currentTerm->id) {
            return response()->json([
                'message' => 'CBT management is limited to the current academic term.',
                'code' => 'current_term_required',
                'current_term_id' => $currentTerm->id,
            ], 422);
        }

        $exam = $request->route('cbtExam');
        if ($exam instanceof CbtExam && (int) $exam->term_id !== (int) $currentTerm->id) {
            return response()->json([
                'message' => 'This CBT exam belongs to a historical academic term and is not available in the current CBT workspace.',
                'code' => 'historical_term',
                'current_term_id' => $currentTerm->id,
            ], 422);
        }

        $request->attributes->set('current_term_id', $currentTerm->id);
        return $next($request);
    }
}
