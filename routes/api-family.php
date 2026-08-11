<?php

use App\Http\Controllers\Api\Family\MyChildrenController;
use App\Http\Controllers\Api\Family\MyStudentRecordController;
use App\Http\Controllers\Api\Family\SchoolParentsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Family self-service (Parent / Student)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:parent'])->group(function () {
    Route::get('my-children', MyChildrenController::class);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:student'])->group(function () {
    Route::get('my-student-record', MyStudentRecordController::class);
});

// The dedicated Parents page — proprietor/principal/bursar, per the
// restructuring that pulled parents out of the general Staff list.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar'])->group(function () {
    Route::get('parents', SchoolParentsController::class);
    Route::post('parents/{parent}/send-reminder', [SchoolParentsController::class, 'sendReminder']);
});
