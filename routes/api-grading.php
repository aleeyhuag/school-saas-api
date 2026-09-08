<?php

use App\Http\Controllers\Api\Grading\AssessmentSettingController;
use App\Http\Controllers\Api\Grading\GradeBoundaryController;
use App\Http\Controllers\Api\Grading\ResultController;
use App\Http\Controllers\Api\Grading\SubjectScoreController;
use App\Http\Middleware\EnsureCurrentAcademicContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer'])->group(function () {
    Route::get('assessment-settings', [AssessmentSettingController::class, 'show']);
    Route::get('grade-boundaries', [GradeBoundaryController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:exam_officer'])->group(function () {
    Route::put('assessment-settings', [AssessmentSettingController::class, 'update']);
    Route::post('grade-boundaries', [GradeBoundaryController::class, 'store']);
    Route::put('grade-boundaries/{gradeBoundary}', [GradeBoundaryController::class, 'update']);
    Route::delete('grade-boundaries/{gradeBoundary}', [GradeBoundaryController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher', EnsureCurrentAcademicContext::class])->group(function () {
    Route::post('subject-scores', [SubjectScoreController::class, 'store']);
    Route::get('subject-scores', [SubjectScoreController::class, 'index']);
    Route::get('classes/{schoolClassId}/marksheet', [SubjectScoreController::class, 'classMarksheet']);
    Route::get('classes/{schoolClassId}/term-result', [ResultController::class, 'classTermResult']);
    Route::get('classes/{schoolClassId}/term-result/approval-status', [ResultController::class, 'approvalStatus']);
    Route::post('classes/{schoolClassId}/term-result/approve', [ResultController::class, 'approve']);
    Route::post('classes/{schoolClassId}/term-result/revoke', [ResultController::class, 'revokeApproval']);
    Route::post('classes/{schoolClassId}/term-result/publish-permanently', [ResultController::class, 'publishPermanently']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('students/{studentId}/term-result', [ResultController::class, 'studentTermResult']);
});
