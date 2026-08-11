<?php

use App\Http\Controllers\Api\Notifications\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Notifications
|--------------------------------------------------------------------------
|
| Every logged-in user's own in-app notification inbox (the bell
| dropdown) — no role restriction, everyone reads their own.
|
| Deliberately NOT gated by 'school.active': DashboardLayout fetches
| this on every single page load (for the bell icon), including the
| Billing page a Proprietor locked out for a billing reason needs to
| reach — gating it would bounce them right back to /login via the
| axios interceptor before they could ever use that page. Read-only
| and low-risk either way.
|
*/

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
});
