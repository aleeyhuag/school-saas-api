<?php

use App\Http\Controllers\Api\Exports\ExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Exports (Stage 54: queued backups & report card bundles)
|--------------------------------------------------------------------------
|
| These sit ALONGSIDE the original synchronous endpoints in
| api-school.php / api-platform.php / api-reports.php, which are left
| untouched — a small class or a small school can still get an instant
| download there. These are for the cases likely to be slow enough to
| risk a request timeout at real scale (a 5,000-student school's full
| backup, in particular).
|
| download() is intentionally NOT behind auth:sanctum — same reasoning
| as media.payment-proof in api-media.php: it's a signed, time-limited
| URL, and the signature itself (only ever generated for an already-
| authorized viewer via show()/index()) is the access control.
|
*/

Route::middleware(['auth:sanctum', 'school.active'])->group(function () {
    Route::get('exports', [ExportController::class, 'index']);
    Route::get('exports/{export}', [ExportController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor'])->group(function () {
    Route::post('exports/school-backup', [ExportController::class, 'requestSchoolBackup']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::post('exports/id-cards', [ExportController::class, 'requestIdCards']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher'])->group(function () {
    Route::post('classes/{schoolClassId}/report-cards/export-async', [ExportController::class, 'requestReportCardBulk']);
});

Route::get('exports/{export}/download', [ExportController::class, 'download'])->name('exports.download');
