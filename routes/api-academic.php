<?php

use App\Http\Controllers\Api\Academic\BulkImportStudentsController;
use App\Http\Controllers\Api\Academic\DashboardStatsController;
use App\Http\Controllers\Api\Academic\InviteUserController;
use App\Http\Controllers\Api\Academic\SchoolClassController;
use App\Http\Controllers\Api\Academic\StaffController;
use App\Http\Controllers\Api\Academic\StudentController;
use App\Http\Controllers\Api\Academic\StudentPromotionController;
use App\Http\Controllers\Api\Academic\SubjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Academic Structure
|--------------------------------------------------------------------------
|
| This requires Spatie's role middleware alias to be registered. In
| bootstrap/app.php:
|
|   $middleware->alias([
|       'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
|       'school.active' => \App\Http\Middleware\EnsureSchoolIsActive::class,
|   ]);
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::apiResource('classes', SchoolClassController::class)->except(['index', 'show']);
    Route::post('classes/{schoolClass}/subjects', [SchoolClassController::class, 'syncSubjects']);

    Route::apiResource('subjects', SubjectController::class)->except(['index', 'show']);

    Route::apiResource('students', StudentController::class)->except(['index']);
    Route::post('students/{student}/guardians', [StudentController::class, 'syncGuardians']);
    Route::post('students/{student}/create-login', [StudentController::class, 'createLogin']);
    Route::post('students/bulk-import', BulkImportStudentsController::class);

    Route::get('promotions/options', [StudentPromotionController::class, 'options']);
    Route::get('promotions/students', [StudentPromotionController::class, 'students']);
    Route::post('promotions', [StudentPromotionController::class, 'execute']);
    Route::get('promotions/history', [StudentPromotionController::class, 'history']);

    Route::post('invite-user', InviteUserController::class);
    Route::get('dashboard-stats', DashboardStatsController::class);

    Route::get('staff', [StaffController::class, 'index']);
    Route::post('staff/{user}/toggle-status', [StaffController::class, 'toggleStatus']);
    Route::post('staff/{user}/reset-password', [StaffController::class, 'resetPassword']);
    Route::post('staff/{user}/add-role', [StaffController::class, 'addRole']);
    Route::post('staff/{user}/remove-role', [StaffController::class, 'removeRole']);
});

// VIEWING classes/subjects/students — needed by Teacher (attendance
// roster, marksheet's subject list, score entry roster), Bursar (fee
// structures' class picker, fee payment lookup), and Exam Officer,
// not just management.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher|bursar|exam_officer'])->group(function () {
    Route::get('classes', [SchoolClassController::class, 'index']);
    Route::get('classes/{schoolClass}', [SchoolClassController::class, 'show']);
    Route::get('subjects', [SubjectController::class, 'index']);
    Route::get('subjects/{subject}', [SubjectController::class, 'show']);
    Route::get('students', [StudentController::class, 'index']);
});

// Class Teacher roster — intentionally isolated from the broader
// academic read group above. The controller also checks the exact
// teacher_assignment, so changing a URL cannot expose another class.
Route::middleware(['auth:sanctum', 'school.active', 'role:teacher'])->group(function () {
    Route::get('my-class/students', [StudentController::class, 'myClass']);
    Route::put('my-class/students/{student}', [StudentController::class, 'updateMyClassStudent']);
});
