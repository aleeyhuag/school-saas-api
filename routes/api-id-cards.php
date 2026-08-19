<?php

use App\Http\Controllers\Api\IdCards\IdCardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — ID Cards (Stage 53)
|--------------------------------------------------------------------------
|
| Single on-demand download lives here. Bulk generation (ZIP / print
| sheet) goes through the generic queued-export flow in
| api-exports.php (ExportController::requestIdCards) instead.
|
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher'])->group(function () {
    Route::get('id-cards/{studentId}', [IdCardController::class, 'show']);
});
