<?php

use App\Http\Controllers\Api\Platform\PlatformSchoolController;
use App\Http\Controllers\Api\Platform\PlatformStatsController;
use App\Http\Controllers\Api\Platform\PlatformBackupController;
use App\Http\Controllers\Diagnostic\StorageHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Platform Admin (super_admin only)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('platform')->group(function () {
    Route::get('schools', [PlatformSchoolController::class, 'index']);
    Route::post('schools', [PlatformSchoolController::class, 'store']);
    Route::get('schools/{school}', [PlatformSchoolController::class, 'show']);
    Route::post('schools/{school}/toggle-active', [PlatformSchoolController::class, 'toggleActive']);
    Route::delete('schools/{school}', [PlatformSchoolController::class, 'destroy']);
    Route::get('stats', [PlatformStatsController::class, 'show']);
    Route::get('backup/download', [PlatformBackupController::class, 'download']);
    Route::get('schools/{school}/backup/download', [PlatformBackupController::class, 'downloadSchool']);
    Route::get('schools/{school}/export/{module}', [PlatformBackupController::class, 'downloadSchoolModule']);

    // Stage 55 hotfix — see StorageHealthController's docblock. Reports
    // hard evidence on the "images disappear after a few hours" report
    // instead of guessing further.
    Route::get('diagnostics/storage-health', StorageHealthController::class);
});
