<?php

namespace App\Http\Middleware;

use App\Models\Term;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentAcademicContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->school_id) {
            return $next($request);
        }

        $currentTerm = Term::with('academicSession')
            ->where('school_id', $user->school_id)
            ->where('is_current', true)
            ->first();

        // If a school has not configured a current term yet, do not
        // silently expose historical academic data through a route that
        // is explicitly current-context scoped.
        if (!$currentTerm) {
            return response()->json([
                'message' => 'No current term has been set for this school. Please set the current term first.',
            ], 409);
        }

        $termId = $request->input('term_id');
        if ($termId !== null && (int) $termId !== (int) $currentTerm->id) {
            return response()->json([
                'message' => 'This operation is limited to the school\'s current term.',
                'current_term_id' => $currentTerm->id,
            ], 422);
        }

        $sessionId = $request->input('academic_session_id');
        $currentSessionId = $currentTerm->academic_session_id;
        if ($sessionId !== null && (int) $sessionId !== (int) $currentSessionId) {
            return response()->json([
                'message' => 'This operation is limited to the school\'s current academic session.',
                'current_academic_session_id' => $currentSessionId,
            ], 422);
        }

        return $next($request);
    }
}
