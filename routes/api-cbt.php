<?php

use App\Http\Controllers\Api\Cbt\CbtExamController;
use App\Http\Controllers\Api\Cbt\CbtStudentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer'])->prefix('cbt')->group(function () {
    Route::get('/exams', [CbtExamController::class, 'index']);
    Route::post('/exams', [CbtExamController::class, 'store']);
    Route::get('/exams/{cbtExam}', [CbtExamController::class, 'show']);
    Route::put('/exams/{cbtExam}', [CbtExamController::class, 'update']);
    Route::delete('/exams/{cbtExam}', [CbtExamController::class, 'destroy']);
    Route::post('/exams/{cbtExam}/publish', [CbtExamController::class, 'publish']);
    Route::post('/exams/{cbtExam}/questions', [CbtExamController::class, 'addQuestion']);
    Route::delete('/questions/{cbtQuestion}', [CbtExamController::class, 'deleteQuestion']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:student'])->prefix('student/cbt')->group(function () {
    Route::get('/available', [CbtStudentController::class, 'available']);
    Route::get('/results', [CbtStudentController::class, 'results']);
    Route::post('/exams/{cbtExam}/start', [CbtStudentController::class, 'start']);
    Route::get('/attempts/{cbtAttempt}', [CbtStudentController::class, 'show']);
    Route::post('/attempts/{cbtAttempt}/answers', [CbtStudentController::class, 'saveAnswer']);
    Route::post('/attempts/{cbtAttempt}/submit', [CbtStudentController::class, 'submit']);
});
