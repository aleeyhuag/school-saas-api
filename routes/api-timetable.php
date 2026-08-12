<?php

use App\Http\Controllers\Api\Timetable\ClassTimetableController;
use App\Http\Controllers\Api\Timetable\ExamTimetableController;
use App\Http\Controllers\Api\Timetable\PeriodDefinitionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Timetable
|--------------------------------------------------------------------------
|
| Class timetable is owned by Principal (create/edit/delete); Proprietor,
| teachers, students, and parents can all view it.
|
| Exam timetable is owned by Exam Officer (create/edit/delete); Proprietor,
| Principal, teachers, students, and parents can all view it.
|
| Period definitions (what time each "Period N" actually runs) are
| owned by Principal, same as the class timetable grid itself — every
| actor who can view the class timetable can also view period times.
|
| Bursar has no access to any of the three — excluded from all groups.
|
*/

// Viewing the class timetable — broad read access, everyone except bursar.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student'])->group(function () {
    Route::get('timetable/class', [ClassTimetableController::class, 'index']);
    Route::get('timetable/periods', [PeriodDefinitionController::class, 'index']);
});

// Managing period definitions — Principal only, same as the grid.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::post('timetable/periods', [PeriodDefinitionController::class, 'store']);
    Route::delete('timetable/periods/{periodDefinition}', [PeriodDefinitionController::class, 'destroy']);
});

// Teacher's own schedule, scoped to their assignments.
Route::middleware(['auth:sanctum', 'school.active', 'role:teacher'])->group(function () {
    Route::get('timetable/my-schedule', [ClassTimetableController::class, 'mySchedule']);
});

// Editing the class timetable — Principal only.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::post('timetable/class', [ClassTimetableController::class, 'store']);
    Route::delete('timetable/class/{classTimetableEntry}', [ClassTimetableController::class, 'destroy']);
});

// Viewing the exam timetable — broad read access, everyone except bursar.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|exam_officer|teacher|parent|student'])->group(function () {
    Route::get('timetable/exam', [ExamTimetableController::class, 'index']);
});

// Editing the exam timetable — Exam Officer only.
Route::middleware(['auth:sanctum', 'school.active', 'role:exam_officer'])->group(function () {
    Route::post('timetable/exam', [ExamTimetableController::class, 'store']);
    Route::put('timetable/exam/{examTimetableEntry}', [ExamTimetableController::class, 'update']);
    Route::delete('timetable/exam/{examTimetableEntry}', [ExamTimetableController::class, 'destroy']);
});
