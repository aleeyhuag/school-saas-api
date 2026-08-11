<?php

use App\Http\Controllers\Api\Reports\ReportCardController;
use App\Http\Controllers\Api\Reports\ResultExportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Reports
|--------------------------------------------------------------------------
|
| Two different deliverables for two different audiences:
|
|  - Report card PDFs (ReportCardController) are per-student, for
|    handing to a family — same broad read roles as an individual
|    result, plus a class-wide ZIP for the class teacher (or
|    management) to export their whole class at once.
|
|  - The results spreadsheet (ResultExportController) is for admin
|    review/audit across many students — Exam Officer, Proprietor,
|    Principal only. Not exposed to teachers, parents, or students.
|
*/

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student'])->group(function () {
    Route::get('report-cards/{studentId}', [ReportCardController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher'])->group(function () {
    Route::get('classes/{schoolClassId}/report-cards/export', [ReportCardController::class, 'classBulk']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer'])->group(function () {
    Route::get('results/export', [ResultExportController::class, 'export']);
});
