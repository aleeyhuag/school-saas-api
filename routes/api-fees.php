<?php

use App\Http\Controllers\Api\Fees\FeePaymentController;
use App\Http\Controllers\Api\Fees\FeeStructureController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Fees & Payments
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar'])->group(function () {
    Route::apiResource('fee-structures', FeeStructureController::class)->except(['show']);

    Route::post('fee-payments', [FeePaymentController::class, 'store']);
    Route::get('students/{studentId}/fee-payments', [FeePaymentController::class, 'forStudent']);
    Route::get('classes/{schoolClassId}/fee-defaulters', [FeePaymentController::class, 'classDefaulters']);
});

// A student's own fee status is also needed by the parent and the
// student themselves — broader role list, same as report cards.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar|parent|student'])->group(function () {
    Route::get('students/{studentId}/fee-status', [FeePaymentController::class, 'studentStatus']);
});
