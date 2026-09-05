<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentEnrollmentService;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountActionTokenService;
use App\Notifications\AccountSetupNotification;

/**
 * Imports many students at once from a CSV file — the single-add
 * form doesn't scale once a school has hundreds of existing students
 * to bring onto the platform. Reuses StudentEnrollmentService so
 * every imported student gets the exact same auto-login treatment as
 * one added individually.
 *
 * Expected CSV columns (header row required, order doesn't matter):
 *   admission_number, first_name, last_name, class_name, class_arm,
 *   date_of_birth, gender, guardian_name, guardian_phone, login_email
 *
 * Only admission_number, first_name, last_name, class_name are
 * required; the rest are optional and may be left blank.
 */
class BulkImportStudentsController extends Controller
{
    public function __invoke(StudentEnrollmentService $enrollmentService)
    {
        request()->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $school = Auth::user()->school;
        $handle = fopen(request()->file('file')->getRealPath(), 'r');
        $header = array_map(fn ($h) => strtolower(trim($h)), fgetcsv($handle));

        $required = ['admission_number', 'first_name', 'last_name', 'class_name'];
        $missingColumns = array_diff($required, $header);
        if ($missingColumns) {
            fclose($handle);
            return response()->json([
                'message' => 'CSV is missing required column(s): ' . implode(', ', $missingColumns),
            ], 422);
        }

        // Cache classes for this school once — matched case-
        // insensitively by name + arm, since a spreadsheet won't
        // reliably match your exact stored casing.
        $classes = SchoolClass::where('school_id', $school->id)->get();

        $created = [];
        $skipped = [];
        $rowNumber = 1; // header was row 1

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            $data = array_combine($header, $row);

            $admissionNumber = trim($data['admission_number'] ?? '');
            $firstName = trim($data['first_name'] ?? '');
            $lastName = trim($data['last_name'] ?? '');
            $className = trim($data['class_name'] ?? '');
            $classArm = trim($data['class_arm'] ?? '');

            if (! $admissionNumber || ! $firstName || ! $lastName || ! $className) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Missing a required field.'];
                continue;
            }

            $matchedClass = $classes->first(
                fn ($c) => strcasecmp(trim($c->name), $className) === 0
                    && strcasecmp(trim($c->arm ?? ''), $classArm) === 0
            );

            if (! $matchedClass) {
                $skipped[] = [
                    'row' => $rowNumber,
                    'reason' => "No class matching \"{$className} {$classArm}\" was found.",
                ];
                continue;
            }

            if (Student::where('school_id', $school->id)
                ->where('admission_number', $admissionNumber)->exists()) {
                $skipped[] = ['row' => $rowNumber, 'reason' => "Admission number {$admissionNumber} already exists."];
                continue;
            }

            try {
                [$student, $temporaryPassword, $providedEmail] = $enrollmentService->enroll([
                    'school_class_id' => $matchedClass->id,
                    'admission_number' => $admissionNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => $data['date_of_birth'] ?: null,
                    'gender' => $data['gender'] ?: null,
                    'guardian_name' => $data['guardian_name'] ?: null,
                    'guardian_phone' => $data['guardian_phone'] ?: null,
                    'login_email' => $data['login_email'] ?: null,
                ], $school);

                if ($providedEmail) {
                    $token = app(AccountActionTokenService::class)->issue($student->user, 'account_setup');
                    $student->user->notify(new AccountSetupNotification($token, $school->name));
                }

                $created[] = [
                    'admission_number' => $student->admission_number,
                    'name' => "{$firstName} {$lastName}",
                    'login_email' => $student->user->email,
                    'temporary_password' => $providedEmail ? null : $temporaryPassword,
                    'setup_link_sent' => $providedEmail,
                    'system_generated_email' => ! $providedEmail,
                ];
            } catch (\Throwable $e) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Could not create this student — ' . $e->getMessage()];
            }
        }

        fclose($handle);

        return response()->json([
            'message' => count($created) . ' student(s) created, ' . count($skipped) . ' skipped.',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }
}
