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
            // Present for schools that type their own in; absent
            // entirely for schools that opted into auto-generation
            // (StudentRequest marks the field 'prohibited' in that
            // case, so it never reaches here at all). Either way, by
            // this point there's a real number to work with.
            $admissionNumber = $data['admission_number'] ?? $school->nextAdmissionNumber();

            $providedEmail = filled($data['login_email'] ?? null);
            $loginEmail = $providedEmail
                ? strtolower(trim($data['login_email']))
                : strtolower($admissionNumber) . '@' . $school->slug . '.students.local';

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
                ...collect($data)->except(['login_email', 'admission_number'])->toArray(),
                'admission_number' => $admissionNumber,
                'school_id' => $school->id,
                'user_id' => $loginUser->id,
            ]);

            return [$student, $temporaryPassword, $providedEmail];
        });
    }
}
