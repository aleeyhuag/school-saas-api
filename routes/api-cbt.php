<?php

use App\Http\Controllers\Api\Cbt\CbtExamController;
use App\Http\Controllers\Api\Cbt\CbtQuestionBankController;
use App\Http\Controllers\Api\Cbt\CbtStudentController;
use Illuminate\Support\Facades\Route;

// CBT management belongs to Exam Officers and Teachers. Proprietors and
// Principals intentionally have no CBT API access.
Route::middleware(['auth:sanctum', 'school.active', 'role:exam_officer|teacher'])->prefix('cbt')->group(function () {
    Route::get('/exams', [CbtExamController::class, 'index']);
    Route::post('/exams', [CbtExamController::class, 'store']);
    Route::get('/exams/{cbtExam}', [CbtExamController::class, 'show']);
    Route::put('/exams/{cbtExam}', [CbtExamController::class, 'update']);
    Route::delete('/exams/{cbtExam}', [CbtExamController::class, 'destroy']);
    Route::post('/exams/{cbtExam}/publish', [CbtExamController::class, 'publish']);
    Route::get('/exams/{cbtExam}/results', [CbtExamController::class, 'results']);
    Route::post('/exams/{cbtExam}/questions', [CbtExamController::class, 'addQuestion']);
    Route::put('/questions/{cbtQuestion}', [CbtExamController::class, 'updateQuestion']);
    Route::delete('/questions/{cbtQuestion}', [CbtExamController::class, 'deleteQuestion']);

    Route::get('/question-bank', [CbtQuestionBankController::class, 'index']);
    Route::post('/question-bank', [CbtQuestionBankController::class, 'store']);
    Route::put('/question-bank/{cbtQuestionBank}', [CbtQuestionBankController::class, 'update']);
    Route::delete('/question-bank/{cbtQuestionBank}', [CbtQuestionBankController::class, 'destroy']);
    Route::post('/question-bank/import', [CbtQuestionBankController::class, 'import']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:student'])->prefix('student/cbt')->group(function () {
    Route::get('/available', [CbtStudentController::class, 'available']);
    Route::get('/results', [CbtStudentController::class, 'results']);
    Route::post('/exams/{cbtExam}/start', [CbtStudentController::class, 'start']);
    Route::get('/attempts/{cbtAttempt}', [CbtStudentController::class, 'show']);
    Route::post('/attempts/{cbtAttempt}/answers', [CbtStudentController::class, 'saveAnswer']);
    Route::post('/attempts/{cbtAttempt}/submit', [CbtStudentController::class, 'submit']);
});
