<?php

namespace App\Http\Controllers\Api\Academic;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentEnrollmentService;
use Illuminate\Support\Facades\Auth;
use App\Services\AccountActionTokenService;
use App\Notifications\AccountSetupNotification;

class BulkImportStudentsController extends Controller
{
    public function __invoke(StudentEnrollmentService $enrollmentService)
    {
        request()->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $school = Auth::user()->school;
        $handle = fopen(request()->file('file')->getRealPath(), 'r');
        $rawHeader = fgetcsv($handle);
        if ($rawHeader === false) return response()->json(['message' => 'The CSV file is empty.'], 422);
        $header = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), $rawHeader);
        $required = ['first_name', 'last_name', 'class_name'];
        $missingColumns = array_diff($required, $header);
        if ($missingColumns) {
            fclose($handle);
            return response()->json(['message' => 'CSV is missing required column(s): '.implode(', ', $missingColumns)], 422);
        }

        $autoGenerate = (bool) $school->auto_generate_admission_numbers;
        $classes = SchoolClass::where('school_id', $school->id)->get();
        $created = [];
        $skipped = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) < count($header)) $row = array_pad($row, count($header), null);
            if (count($row) > count($header)) $row = array_slice($row, 0, count($header));
            $data = array_combine($header, $row);
            $admissionNumber = trim($data['admission_number'] ?? '');
            $firstName = trim($data['first_name'] ?? '');
            $lastName = trim($data['last_name'] ?? '');
            $className = trim($data['class_name'] ?? '');
            $classArm = trim($data['class_arm'] ?? '');

            if (! $firstName || ! $lastName || ! $className) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Missing first name, last name, or class name.'];
                continue;
            }
            if (! $autoGenerate && ! $admissionNumber) {
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Admission number is required because this school has automatic admission numbering turned off.'];
                continue;
            }

            $matchedClass = $classes->first(fn ($c) => strcasecmp(trim($c->name), $className) === 0 && strcasecmp(trim($c->arm ?? ''), $classArm) === 0);
            if (! $matchedClass) {
                $skipped[] = ['row' => $rowNumber, 'reason' => "No class matching \"{$className} {$classArm}\" was found."];
                continue;
            }
            if ($admissionNumber && Student::where('school_id', $school->id)->where('admission_number', $admissionNumber)->exists()) {
                $skipped[] = ['row' => $rowNumber, 'reason' => "Admission number {$admissionNumber} already exists."];
                continue;
            }

            try {
                [$student, $temporaryPassword, $providedEmail] = $enrollmentService->enroll([
                    'school_class_id' => $matchedClass->id,
                    'admission_number' => $autoGenerate ? null : $admissionNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => !empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'gender' => !empty($data['gender']) ? $data['gender'] : null,
                    'guardian_name' => !empty($data['guardian_name']) ? $data['guardian_name'] : null,
                    'guardian_phone' => !empty($data['guardian_phone']) ? $data['guardian_phone'] : null,
                    'login_email' => !empty($data['login_email']) ? $data['login_email'] : null,
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
                $skipped[] = ['row' => $rowNumber, 'reason' => 'Could not create this student — '.$e->getMessage()];
            }
        }
        fclose($handle);
        return response()->json([
            'message' => count($created).' student(s) created, '.count($skipped).' skipped.',
            'created' => $created,
            'skipped' => $skipped,
            'auto_generated_admission_numbers' => $autoGenerate,
        ]);
    }
}
