<?php

use App\Http\Controllers\Api\Timetable\ClassTimetableController;
use App\Http\Controllers\Api\Timetable\ExamTimetableController;
use App\Http\Controllers\Api\Timetable\PeriodDefinitionController;
use App\Http\Middleware\EnsureCurrentAcademicContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('timetable/class', [ClassTimetableController::class, 'index']);
    Route::get('timetable/periods', [PeriodDefinitionController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::post('timetable/periods', [PeriodDefinitionController::class, 'store']);
    Route::delete('timetable/periods/{periodDefinition}', [PeriodDefinitionController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:teacher', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('timetable/my-schedule', [ClassTimetableController::class, 'mySchedule']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal', EnsureCurrentAcademicContext::class])->group(function () {
    Route::post('timetable/class', [ClassTimetableController::class, 'store']);
    Route::delete('timetable/class/{classTimetableEntry}', [ClassTimetableController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('timetable/exam', [ExamTimetableController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:exam_officer', EnsureCurrentAcademicContext::class])->group(function () {
    Route::post('timetable/exam', [ExamTimetableController::class, 'store']);
    Route::put('timetable/exam/{examTimetableEntry}', [ExamTimetableController::class, 'update']);
    Route::delete('timetable/exam/{examTimetableEntry}', [ExamTimetableController::class, 'destroy']);
});
