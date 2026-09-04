<?php

use App\Http\Controllers\Api\Enrollment\EnrollmentApplicationController;
use App\Http\Controllers\Api\Enrollment\PublicEnrollmentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Self-Enrollment
|--------------------------------------------------------------------------
*/

// Public — no token required. Throttled the same way register-school is:
// this is another public form a stranger can hit repeatedly.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('enroll/{slug}', [PublicEnrollmentController::class, 'show']);
    Route::post('enroll/{slug}', [PublicEnrollmentController::class, 'store']);
});

// Review queue — Proprietor/Principal only, matches every other
// senior-only school-management area.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::get('enrollment-applications', [EnrollmentApplicationController::class, 'index']);
    Route::get('enrollment-applications/{enrollmentApplication}', [EnrollmentApplicationController::class, 'show']);
    Route::post('enrollment-applications/{enrollmentApplication}/approve', [EnrollmentApplicationController::class, 'approve']);
    Route::post('enrollment-applications/{enrollmentApplication}/reject', [EnrollmentApplicationController::class, 'reject']);
});
