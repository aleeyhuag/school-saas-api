<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Gives a school admin a student's sign-in details.
 *
 * Passwords are stored hashed, so an existing password can never be read back.
 * What the school can do is set a NEW one and hand it over, which is what this does:
 * it sets a fresh readable password for each student, signs out their old sessions,
 * and returns the username (the admission number) + the new password ONCE.
 * Students who have no login account yet get one created.
 *
 * Proprietor/Principal only (route group). Small batches, because every password
 * hash takes a moment — the web app sends 10 at a time.
 */
class StudentLoginCredentialsController extends Controller
{
    public function __invoke(Request $request, AuditLogService $auditLogs)
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1', 'max:20'],
            'student_ids.*' => ['integer', 'distinct'],
        ]);

        // Student is school-scoped (BelongsToSchool), so ids from another
        // school simply don't come back.
        $students = Student::with(['user', 'schoolClass'])
            ->whereIn('id', $data['student_ids'])
            ->get();

        $credentials = [];
        $skipped = [];

        foreach ($students as $student) {
            try {
                $password = $this->readablePassword();

                if ($student->user) {
                    $student->user->update(['password' => Hash::make($password)]);
                    $student->user->tokens()->delete();
                } else {
                    $loginUser = User::create([
                        'school_id' => $student->school_id,
                        'name' => trim($student->first_name . ' ' . $student->last_name),
                        'email' => strtolower($student->admission_number) . '@students.local',
                        'password' => Hash::make($password),
                        'status' => 'approved',
                    ]);
                    $loginUser->assignRole('student');
                    $student->update(['user_id' => $loginUser->id]);
                }

                $credentials[] = [
                    'student_id' => $student->id,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'class' => trim(($student->schoolClass?->name ?? '') . ' ' . ($student->schoolClass?->arm ?? '')),
                    'username' => $student->admission_number,
                    'temporary_password' => $password,
                ];
            } catch (\Throwable $e) {
                $skipped[] = [
                    'student_id' => $student->id,
                    'name' => trim($student->first_name . ' ' . $student->last_name),
                    'reason' => 'Could not set a login for this student.',
                ];
            }
        }

        $foundIds = $students->pluck('id')->all();
        foreach (array_diff($data['student_ids'], $foundIds) as $missingId) {
            $skipped[] = ['student_id' => $missingId, 'name' => null, 'reason' => 'Student not found in your school.'];
        }

        if ($credentials) {
            $auditLogs->record(
                $request,
                'student_login_reset',
                count($credentials) . ' student login password(s) were reset.',
                ['student_ids' => array_column($credentials, 'student_id')]
            );
        }

        return response()->json(['credentials' => $credentials, 'skipped' => $skipped]);
    }

    /** 8 characters, no look-alikes (0/O, 1/l/I) so it is easy to read off a printed slip. */
    private function readablePassword(int $length = 8): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzACDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }
}
