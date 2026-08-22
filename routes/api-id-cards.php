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
    Route::get('id-cards/{studentId}/download-url', [IdCardController::class, 'requestDownloadUrl']);
});

// Reached only via the signed URL the route above returns — see
// IdCardController's docblock for why this isn't behind auth:sanctum.
Route::get('id-cards/{studentId}/download', [IdCardController::class, 'show'])
    ->name('id-card.download');
