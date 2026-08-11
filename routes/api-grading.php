<?php

use App\Http\Controllers\Api\Grading\AssessmentSettingController;
use App\Http\Controllers\Api\Grading\GradeBoundaryController;
use App\Http\Controllers\Api\Grading\ResultController;
use App\Http\Controllers\Api\Grading\SubjectScoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Grading Engine
|--------------------------------------------------------------------------
*/

// Viewing HOW grading works — proprietor/principal need to see the
// current weights/boundaries, but cannot change them (see below).
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer'])->group(function () {
    Route::get('assessment-settings', [AssessmentSettingController::class, 'show']);
    Route::get('grade-boundaries', [GradeBoundaryController::class, 'index']);
});

// EDITING how grading works — exam_officer only.
Route::middleware(['auth:sanctum', 'school.active', 'role:exam_officer'])->group(function () {
    Route::put('assessment-settings', [AssessmentSettingController::class, 'update']);

    Route::post('grade-boundaries', [GradeBoundaryController::class, 'store']);
    Route::put('grade-boundaries/{gradeBoundary}', [GradeBoundaryController::class, 'update']);
    Route::delete('grade-boundaries/{gradeBoundary}', [GradeBoundaryController::class, 'destroy']);
});

// Entering/viewing scores — management roles + teachers (the
// controller double-checks the specific class/subject assignment for
// entry; class-term-result is a staff-only review screen).
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher'])->group(function () {
    Route::post('subject-scores', [SubjectScoreController::class, 'store']);
    Route::get('subject-scores', [SubjectScoreController::class, 'index']);
    Route::get('classes/{schoolClassId}/marksheet', [SubjectScoreController::class, 'classMarksheet']);

    Route::get('classes/{schoolClassId}/term-result', [ResultController::class, 'classTermResult']);
});

// Approving/revoking/permanently-publishing a class's results —
// restricted to management roles + teachers (the controller
// double-checks the specific person is actually the class teacher
// assigned to THIS class before letting them act).
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher'])->group(function () {
    Route::get('classes/{schoolClassId}/term-result/approval-status', [ResultController::class, 'approvalStatus']);
    Route::post('classes/{schoolClassId}/term-result/approve', [ResultController::class, 'approve']);
    Route::post('classes/{schoolClassId}/term-result/revoke', [ResultController::class, 'revokeApproval']);
    Route::post('classes/{schoolClassId}/term-result/publish-permanently', [ResultController::class, 'publishPermanently']);
});

// Viewing an individual student's report card — open to a broader set
// of roles, since parents and the student themselves need this too.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student'])->group(function () {
    Route::get('students/{studentId}/term-result', [ResultController::class, 'studentTermResult']);
});
