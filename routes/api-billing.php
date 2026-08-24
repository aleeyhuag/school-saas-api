<?php

use App\Http\Controllers\Api\Billing\BillingController;
use App\Http\Controllers\Api\Billing\PaystackWebhookController;
use App\Http\Controllers\Api\Billing\PlatformBillingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Billing
|--------------------------------------------------------------------------
|
| Proprietor-facing billing routes deliberately do NOT include the
| 'school.active' middleware other proprietor routes use — a
| Proprietor locked out for a billing reason (trial/subscription
| lapsed) still needs to reach these to actually pay and fix it.
| LoginController carves out the matching narrow exception to let
| them log in at all in that state. Every other route in the app
| stays fully blocked via 'school.active' as normal.
|
*/

Route::middleware(['auth:sanctum', 'role:proprietor|principal'])->prefix('billing')->group(function () {
    Route::get('status', [BillingController::class, 'status']);
    Route::get('plans', [BillingController::class, 'plans']);
    Route::post('submit-payment', [BillingController::class, 'submitPayment']);
    Route::post('paystack/initiate', [BillingController::class, 'initiatePaystack']);
});

Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('platform/billing')->group(function () {
    Route::get('plans', [PlatformBillingController::class, 'plans']);
    Route::put('plans/{plan}', [PlatformBillingController::class, 'updatePlan']);
    Route::get('overview', [PlatformBillingController::class, 'overview']);
    Route::get('payments/pending', [PlatformBillingController::class, 'pendingPayments']);
    Route::post('payments/{payment}/confirm', [PlatformBillingController::class, 'confirmPayment']);
    Route::post('payments/{payment}/reject', [PlatformBillingController::class, 'rejectPayment']);
});

// Public — Paystack calls this directly, verified via signature
// header instead of Sanctum auth. Not usable until Paystack is
// enabled, but the endpoint itself is harmless to leave registered.
Route::post('webhooks/paystack', [PaystackWebhookController::class, 'handle']);
