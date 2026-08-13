<?php

namespace App\Services;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class StudentEnrollmentService
{
    public function enroll(array $data, School $school): array
    {
        return DB::transaction(function () use ($data, $school) {
            $providedEmail = filled($data['login_email'] ?? null);
            $loginEmail = $providedEmail
                ? strtolower(trim($data['login_email']))
                : strtolower($data['admission_number']) . '@' . $school->slug . '.students.local';

            $temporaryPassword = Str::random(12);

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

            return [$student, $temporaryPassword, $providedEmail];
        });
    }
}
