<?php

namespace App\Http\Middleware;

use App\Models\TeacherAssignment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherStudentScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$user->hasRole('teacher')) {
            return $next($request);
        }

        $allowedClassIds = TeacherAssignment::where('school_id', $user->school_id)
            ->where('user_id', $user->id)
            ->pluck('school_class_id')
            ->unique()
            ->values();

        // The existing student endpoint accepts one optional class filter.
        // For teachers, require that filter so a teacher can never request
        // an unbounded school-wide student list.
        $classId = $request->input('school_class_id');
        if ($classId === null || !$allowedClassIds->contains((int) $classId)) {
            return response()->json([
                'message' => 'Teachers must request students for a class they are assigned to.',
            ], 403);
        }

        return $next($request);
    }
}
