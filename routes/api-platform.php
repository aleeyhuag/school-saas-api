<?php

use App\Http\Controllers\Api\Platform\PlatformSchoolController;
use App\Http\Controllers\Api\Platform\PlatformStatsController;
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
});
