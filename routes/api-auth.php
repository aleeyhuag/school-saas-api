<?php

use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\CurrentUserController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\RegisterSchoolController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\UpdateProfileController;
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
        // Stage 55 hotfix (patch 2) — named so EnsureSchoolIsActive can
        // carve these two out specifically for a billing-locked
        // Proprietor/Principal. Both are load-bearing for that carve-out
        // to work at all: the frontend calls /me unconditionally right
        // after every login to populate who's signed in before it can
        // route them anywhere (including to Billing), and they still
        // need a working /logout regardless of lock state.
        Route::get('/me', CurrentUserController::class)->name('auth.me');
        Route::post('/logout', LogoutController::class)->name('auth.logout');
        Route::put('/change-password', ChangePasswordController::class);
        Route::put('/profile', UpdateProfileController::class);
    });
});
