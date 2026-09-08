<?php

use App\Http\Controllers\Api\Fees\FeePaymentController;
use App\Http\Controllers\Api\Fees\FeeStructureController;
use App\Http\Middleware\EnsureCurrentAcademicContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar', EnsureCurrentAcademicContext::class])->group(function () {
    Route::apiResource('fee-structures', FeeStructureController::class)->except(['show']);
    Route::post('fee-payments', [FeePaymentController::class, 'store']);
    Route::get('students/{studentId}/fee-payments', [FeePaymentController::class, 'forStudent']);
    Route::get('classes/{schoolClassId}/fee-defaulters', [FeePaymentController::class, 'classDefaulters']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|bursar|parent|student', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('students/{studentId}/fee-status', [FeePaymentController::class, 'studentStatus']);
});
