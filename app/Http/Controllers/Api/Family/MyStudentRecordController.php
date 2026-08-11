<?php

namespace App\Http\Controllers\Api\Family;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * Self-service — a Student's OWN record, found via the Student.user_id
 * link set when their account was created. Distinct from
 * StudentController::show (admin-only).
 */
class MyStudentRecordController extends Controller
{
    public function __invoke()
    {
        $student = Auth::user()->student()->with('schoolClass')->first();

        if (! $student) {
            return response()->json([
                'message' => 'No student record is linked to your account yet — ask your school admin to link it.',
            ], 404);
        }

        return $student;
    }
}
