<?php

use App\Http\Controllers\Api\School\AuditLogController;
use App\Http\Controllers\Api\School\SchoolBackupController;
use Illuminate\Support\Facades\Route;

// School governance: only senior school administrators can inspect
// the audit trail; only the Proprietor can download a school backup.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/meta', [AuditLogController::class, 'meta']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor'])->group(function () {
    Route::get('school-backup/download', [SchoolBackupController::class, 'download']);
});
