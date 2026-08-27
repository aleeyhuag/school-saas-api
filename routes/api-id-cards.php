<?php

use App\Http\Controllers\Api\IdCards\IdCardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Browser ID Cards
|--------------------------------------------------------------------------
|
| The ID card is rendered and printed by the browser. No PDF download or
| queued ID-card export exists anymore.
|
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:principal'])->group(function () {
    Route::get('id-cards/{student}/preview', [IdCardController::class, 'preview']);
    // POST, not GET — a whole-class selection can be 30-100 student ids,
    // too large to reliably fit as a query string.
    Route::post('id-cards/bulk-preview', [IdCardController::class, 'bulk']);
});
