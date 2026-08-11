<?php

use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterSchoolController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Auth
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    // Public routes — no token required. Throttled per IP: these are
    // the endpoints an attacker would actually hit for credential
    // stuffing (login), account enumeration + inbox-bombing (forgot-
    // password), or spamming new school signups (register-school).
    // Nothing anywhere else in the app had ANY rate limiting before
    // this — these three were the ones worth closing first.
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/login', LoginController::class);
        Route::post('/forgot-password', ForgotPasswordController::class);
        Route::post('/reset-password', ResetPasswordController::class);
    });
    Route::middleware('throttle:5,1')->post('/register-school', RegisterSchoolController::class);

    // Protected routes — require a valid Sanctum token AND an active school
    Route::middleware(['auth:sanctum', 'school.active'])->group(function () {
        Route::get('/me', CurrentUserController::class);
        Route::post('/logout', LogoutController::class);
        Route::put('/change-password', ChangePasswordController::class);
    });
});
