<?php

use App\Http\Controllers\VerifyController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Stage 53 — the destination a QR code on an ID card leads to.
// Deliberately public (see VerifyController's docblock). Rate limited
// per IP since the token is guessable-by-brute-force in principle
// (a 40-char random string isn't, in practice, but this costs nothing
// and closes off any enumeration attempt regardless).
Route::get('/verify/{token}', [VerifyController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('id-card.verify');
