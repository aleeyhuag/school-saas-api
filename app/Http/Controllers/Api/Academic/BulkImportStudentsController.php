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
        // One query up front instead of one "does this number exist?" query per row.
        $existingAdmissionNumbers = Student::where('school_id', $school->id)
            ->whereNotNull('admission_number')
            ->pluck('admission_number')
            ->flip();
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
            if ($admissionNumber && isset($existingAdmissionNumbers[$admissionNumber])) {
                $skipped[] = ['row' => $rowNumber, 'reason' => "Admission number {$admissionNumber} already exists."];
                continue;
            }

            // Dates in the file are read as DAY/MONTH/YEAR (e.g. 27/02/2016), the
            // format used in Nigeria. Passing the raw text to the database made it
            // read 05/03/2014 as 3 May instead of 5 March, and reject any date
            // whose day is above 12.
            try {
                $dateOfBirth = $this->normalizeDate($data['date_of_birth'] ?? null);
            } catch (\InvalidArgumentException $e) {
                $skipped[] = ['row' => $rowNumber, 'reason' => $e->getMessage()];
                continue;
            }

            try {
                [$student, $temporaryPassword, $providedEmail] = $enrollmentService->enroll([
                    'school_class_id' => $matchedClass->id,
                    'admission_number' => $autoGenerate ? null : $admissionNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'date_of_birth' => $dateOfBirth,
                    'gender' => !empty($data['gender']) ? $data['gender'] : null,
                    'guardian_name' => !empty($data['guardian_name']) ? $data['guardian_name'] : null,
                    'guardian_phone' => !empty($data['guardian_phone']) ? $data['guardian_phone'] : null,
                    'login_email' => !empty($data['login_email']) ? $data['login_email'] : null,
                ], $school);
                if ($student->admission_number) {
                    $existingAdmissionNumbers[$student->admission_number] = true;
                }
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

    /**
     * Accepts DD/MM/YYYY (also DD-MM-YYYY and DD.MM.YYYY) and YYYY-MM-DD.
     * Returns Y-m-d, or null for an empty cell. Throws for anything else so the
     * row is skipped with a clear reason instead of being saved with a wrong date.
     */
    private function normalizeDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $value, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            throw new \InvalidArgumentException("Date of birth \"{$value}\" is not in a recognised format. Use DD/MM/YYYY, e.g. 27/02/2016.");
        }

        if (! checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException("Date of birth \"{$value}\" is not a real calendar date. Use DD/MM/YYYY, e.g. 27/02/2016.");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
