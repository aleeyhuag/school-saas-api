<?php

use App\Http\Controllers\Api\Academic\TeacherAssignmentController;
use App\Http\Controllers\Api\Attendance\AttendanceController;
use App\Http\Middleware\EnsureCurrentAcademicContext;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::get('teacher-assignments', [TeacherAssignmentController::class, 'index']);
    Route::post('teacher-assignments', [TeacherAssignmentController::class, 'store']);
    Route::delete('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:teacher'])->group(function () {
    Route::get('my-teacher-assignments', [TeacherAssignmentController::class, 'mine']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance', [AttendanceController::class, 'store']);
});

Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher|parent|bursar|exam_officer|student', EnsureCurrentAcademicContext::class])->group(function () {
    Route::get('students/{studentId}/attendance-summary', [AttendanceController::class, 'studentSummary']);
});
