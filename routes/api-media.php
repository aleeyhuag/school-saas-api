<?php

use App\Http\Controllers\Api\Media\MediaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Media (logos, payment proofs)
|--------------------------------------------------------------------------
|
| See MediaController's class docblock for why these exist instead of
| just letting the webserver serve public/storage directly.
|
*/

Route::get('media/logos/{path}', [MediaController::class, 'logo'])
    ->where('path', '.*');

Route::get('media/payment-proofs/{payment}', [MediaController::class, 'paymentProof'])
    ->name('media.payment-proof');

Route::get('media/student-photos/{student}', [MediaController::class, 'studentPhoto'])
    ->name('media.student-photo');
