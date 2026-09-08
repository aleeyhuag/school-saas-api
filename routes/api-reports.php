<?php

use App\Http\Controllers\Api\Reports\ReportCardController;
use App\Http\Controllers\Api\Reports\ResultExportController;
use App\Http\Middleware\EnsureCurrentAcademicContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('report-cards/{studentId}/download-url', [ReportCardController::class, 'requestDownloadUrl']);
    Route::get('report-cards/{studentId}', [ReportCardController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('classes/{schoolClassId}/report-cards/export', [ReportCardController::class, 'classBulk']);
});

Route::get('report-cards/{studentId}/download/{termId}', [ReportCardController::class, 'showSigned'])
    ->name('report-card.download');

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('results/export', [ResultExportController::class, 'export']);
});
