<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Referral\PartnerAuthController;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('partner/register', [PartnerAuthController::class, 'register']);
    Route::post('partner/login', [PartnerAuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('partner')->group(function () {
    Route::get('me', [PartnerAuthController::class, 'me']);
    Route::put('profile', [PartnerAuthController::class, 'updateProfile']);
    Route::post('logout', [PartnerAuthController::class, 'logout']);
});
