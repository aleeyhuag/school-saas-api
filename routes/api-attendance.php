<?php

use App\Http\Controllers\Api\Academic\TeacherAssignmentController;
use App\Http\Controllers\Api\Attendance\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Teacher Assignments & Attendance
|--------------------------------------------------------------------------
*/

// Only management roles can assign teachers to classes/subjects
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal'])->group(function () {
    Route::get('teacher-assignments', [TeacherAssignmentController::class, 'index']);
    Route::post('teacher-assignments', [TeacherAssignmentController::class, 'store']);
    Route::delete('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy']);
});

// A teacher checking their OWN assignments — any teacher needs this
// to know what they're assigned to (which class(es) they're the
// class teacher for, which class+subject combos they teach). This is
// what determines what a single unified Teacher dashboard shows —
// there's no separate "class_teacher" vs "subject_teacher" role
// anymore, just real assignment records.
Route::middleware(['auth:sanctum', 'school.active', 'role:teacher'])->group(function () {
    Route::get('my-teacher-assignments', [TeacherAssignmentController::class, 'mine']);
});

// Attendance can be marked/viewed by management roles AND teachers
// (the controller itself checks a teacher is actually assigned to the
// class/subject before letting them mark it, and separately enforces
// that only the class teacher can mark whole-day attendance — see
// AttendanceController::store)
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher'])->group(function () {
    Route::get('attendance', [AttendanceController::class, 'index']);
    Route::post('attendance', [AttendanceController::class, 'store']);
});

// Viewing a single student's attendance summary is useful to more
// roles too (e.g. a parent checking their own child, or a bursar
// cross-referencing before a report) — kept in its own group with a
// broader role list.
Route::middleware(['auth:sanctum', 'school.active', 'role:proprietor|principal|teacher|parent|bursar|exam_officer|student'])->group(function () {
    Route::get('students/{studentId}/attendance-summary', [AttendanceController::class, 'studentSummary']);
});
