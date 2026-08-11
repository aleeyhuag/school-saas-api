<?php

use App\Http\Controllers\Api\School\SchoolHealthController;
use App\Http\Controllers\Api\School\SchoolProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — School Profile (self-service, not super_admin)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::get('school-profile', [SchoolProfileController::class, 'show']);
    Route::post('school-profile', [SchoolProfileController::class, 'update']);
    Route::get('school-health', [SchoolHealthController::class, 'show']);
});
