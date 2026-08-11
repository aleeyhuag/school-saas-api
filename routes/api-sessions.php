<?php

use App\Http\Controllers\Api\Academic\AcademicSessionController;
use App\Http\Controllers\Api\Academic\TermController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Academic Sessions & Terms
|--------------------------------------------------------------------------
*/

// Anyone logged in can check the current term — every dashboard
// (attendance, grading, fees) needs this to know "which term am I
// looking at right now".
Route::middleware(['auth:sanctum', 'school.active'])->group(function () {
    Route::get('terms/current', [TermController::class, 'current']);
});

// VIEWING sessions/terms — needed by Bursar (Fees term selector),
// Exam Officer, Teacher (not just management), and Student/Parent
// (their Timetable/Exam Timetable pages have a session selector too).
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar|exam_officer|teacher|student|parent'])->group(function () {
    Route::get('academic-sessions', [AcademicSessionController::class, 'index']);
    Route::get('academic-sessions/{academicSession}', [AcademicSessionController::class, 'show']);
    Route::get('terms', [TermController::class, 'index']);
});

// EDITING sessions/terms — management only
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::post('academic-sessions', [AcademicSessionController::class, 'store']);
    Route::put('academic-sessions/{academicSession}', [AcademicSessionController::class, 'update']);
    Route::delete('academic-sessions/{academicSession}', [AcademicSessionController::class, 'destroy']);
    Route::apiResource('terms', TermController::class)->except(['index'])
        ->parameters(['terms' => 'term']);
});
