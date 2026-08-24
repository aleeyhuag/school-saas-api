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

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::get('id-cards/{student}/preview', [IdCardController::class, 'preview']);
});
