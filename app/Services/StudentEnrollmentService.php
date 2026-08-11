<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates a Student record AND its automatic login in one step —
 * shared by StudentController::store (single add via the form) and
 * BulkImportStudentsController (CSV import), so the enrollment logic
 * — placeholder email generation, temp password, role assignment —
 * never has to be written twice.
 */
class StudentEnrollmentService
{
    public function enroll(array $data, School $school): array
    {
        return DB::transaction(function () use ($data, $school) {
            $loginEmail = $data['login_email']
                ?? strtolower($data['admission_number']) . '@' . $school->slug . '.students.local';

            $temporaryPassword = Str::random(10);

            $loginUser = User::create([
                'school_id' => $school->id,
                'name' => trim($data['first_name'] . ' ' . $data['last_name']),
                'email' => $loginEmail,
                'password' => Hash::make($temporaryPassword),
                'status' => 'approved',
            ]);
            $loginUser->assignRole('student');

            $student = Student::create([
                ...collect($data)->except('login_email')->toArray(),
                'school_id' => $school->id,
                'user_id' => $loginUser->id,
            ]);

            return [$student, $temporaryPassword];
        });
    }
}
