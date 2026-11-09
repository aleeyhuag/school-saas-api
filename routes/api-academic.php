<?php

use App\Http\Controllers\Api\Academic\BulkImportStudentsController;
use App\Http\Controllers\Api\Academic\DashboardStatsController;
use App\Http\Controllers\Api\Academic\InviteUserController;
use App\Http\Controllers\Api\Academic\SchoolClassController;
use App\Http\Controllers\Api\Academic\StaffController;
use App\Http\Controllers\Api\Academic\StudentLoginCredentialsController;
use App\Http\Controllers\Api\Academic\StudentController;
use App\Http\Controllers\Api\Academic\StudentPromotionController;
use App\Http\Controllers\Api\Academic\SubjectController;
use App\Http\Middleware\EnsureTeacherStudentScope;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::apiResource('classes', SchoolClassController::class)->except(['index', 'show']);
    Route::post('classes/{schoolClass}/subjects', [SchoolClassController::class, 'syncSubjects']);

    Route::apiResource('subjects', SubjectController::class)->except(['index', 'show']);

    Route::apiResource('students', StudentController::class)->except(['index']);
    Route::post('students/{student}/guardians', [StudentController::class, 'syncGuardians']);
    Route::post('students/{student}/create-login', [StudentController::class, 'createLogin']);
    Route::post('students/{student}/photo', [StudentController::class, 'uploadPhoto']);
    Route::post('students/bulk-import', BulkImportStudentsController::class);
    Route::post('students/login-credentials', StudentLoginCredentialsController::class)->middleware('throttle:60,1');

    Route::post('invite-user', InviteUserController::class);
    // DashboardStatsController is an invokable controller; calling a
    // nonexistent show() method caused the dashboard request to fail and
    // the frontend to fall back to zero students/staff.
    Route::get('dashboard-stats', DashboardStatsController::class);

    Route::get('staff', [StaffController::class, 'index']);
    Route::post('staff/{user}/toggle-status', [StaffController::class, 'toggleStatus']);
    Route::post('staff/{user}/reset-password', [StaffController::class, 'resetPassword']);
    Route::post('staff/{user}/add-role', [StaffController::class, 'addRole']);
    Route::post('staff/{user}/remove-role', [StaffController::class, 'removeRole']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:principal'])->group(function () {
    Route::get('promotions/options', [StudentPromotionController::class, 'options']);
    Route::get('promotions/students', [StudentPromotionController::class, 'students']);
    Route::post('promotions', [StudentPromotionController::class, 'execute']);
    Route::get('promotions/history', [StudentPromotionController::class, 'history']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher|bursar|exam_officer'])->group(function () {
    Route::get('classes', [SchoolClassController::class, 'index']);
    Route::get('classes/{schoolClass}', [SchoolClassController::class, 'show']);
    Route::get('subjects', [SubjectController::class, 'index']);
    Route::get('subjects/{subject}', [SubjectController::class, 'show']);
});

// ONE route for everyone allowed to list students. (It used to be declared twice —
// once for teachers, once for management. Laravel keeps only the LAST declaration of
// the same URL, so teachers were silently sent to the management-only version and got
// "User does not have the right roles.")
// EnsureTeacherStudentScope limits teachers who hold no management role to the
// classes they are assigned to and requires a class filter; everyone else passes through.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar|exam_officer|teacher', EnsureTeacherStudentScope::class])->group(function () {
    Route::get('students', [StudentController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:teacher'])->group(function () {
    Route::get('my-class/students', [StudentController::class, 'myClass']);
    Route::post('my-class/students/{student}/photo', [StudentController::class, 'uploadMyClassPhoto']);
});
