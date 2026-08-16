<?php

use App\Http\Controllers\Api\Sync\SyncController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Offline Sync (Stage 52)
|--------------------------------------------------------------------------
|
| Same auth/role gating as the live attendance & score-entry routes in
| api-attendance.php / api-grading.php — these are an alternate entry
| point into equivalent writes, not a lower-trust one.
|
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher'])->group(function () {
    Route::post('sync/attendance', [SyncController::class, 'attendance']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher'])->group(function () {
    Route::post('sync/scores', [SyncController::class, 'score']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher'])->group(function () {
    Route::get('sync/operations', [SyncController::class, 'index']);
    Route::post('sync/operations/{operation}/resolve', [SyncController::class, 'resolve']);
});
