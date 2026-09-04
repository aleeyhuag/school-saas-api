<?php

namespace App\Http\Controllers\Api\Enrollment;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplication;
use App\Services\StudentEnrollmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Proprietor/Principal review queue for applications submitted
 * through the public enrollment page. Approving creates the real
 * Student (via StudentEnrollmentService — same code path as adding a
 * student by hand, so it respects the school's auto-admission-number
 * setting automatically) PLUS a 'parent' account for the guardian,
 * linked via the same guardians pivot StudentController::syncGuardians
 * uses. Nothing here touches real student data until this point.
 */
class EnrollmentApplicationController extends Controller
{
    public function index()
    {
        $applications = EnrollmentApplication::where('school_id', Auth::user()->school_id)
            ->with('schoolClass:id,name,arm')
            ->orderByRaw("status = 'pending' desc") // pending first
            ->latest()
            ->get();

        return response()->json($applications);
    }

    public function show(EnrollmentApplication $enrollmentApplication)
    {
        $this->authorizeSameSchool($enrollmentApplication);

        return response()->json([
            'application' => $enrollmentApplication->load('schoolClass:id,name,arm'),
            'payment_proof_url' => $enrollmentApplication->payment_proof_path
                ? URL::temporarySignedRoute('media.enrollment-proof', now()->addMinutes(10), ['enrollmentApplication' => $enrollmentApplication->id])
                : null,
        ]);
    }

    public function approve(EnrollmentApplication $enrollmentApplication, StudentEnrollmentService $enrollmentService)
    {
        $this->authorizeSameSchool($enrollmentApplication);
        abort_if($enrollmentApplication->status !== 'pending', 422, 'This application has already been reviewed.');

        $school = Auth::user()->school;

        [$student, $studentTempPassword] = $enrollmentService->enroll([
            'first_name' => $enrollmentApplication->first_name,
            'last_name' => $enrollmentApplication->last_name,
            'date_of_birth' => $enrollmentApplication->date_of_birth,
            'gender' => $enrollmentApplication->gender,
            'school_class_id' => $enrollmentApplication->school_class_id,
        ], $school);

        // The guardian who submitted the application gets their own
        // 'parent' login too, linked to the new student — same
        // temporary-password-shown-once pattern as every other
        // account this app creates on someone else's behalf.
        $guardianTempPassword = Str::random(12);
        $guardianUser = \App\Models\User::firstOrCreate(
            ['email' => strtolower(trim($enrollmentApplication->guardian_email))],
            [
                'school_id' => $school->id,
                'name' => $enrollmentApplication->guardian_name,
                'phone' => $enrollmentApplication->guardian_phone,
                'password' => Hash::make($guardianTempPassword),
                'status' => 'approved',
            ]
        );
        if (! $guardianUser->hasRole('parent')) {
            $guardianUser->assignRole('parent');
        }
        $student->guardians()->syncWithoutDetaching([$guardianUser->id]);

        $enrollmentApplication->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'created_student_id' => $student->id,
        ]);

        return response()->json([
            'message' => 'Application approved — student enrolled.',
            'student' => $student->only(['id', 'first_name', 'last_name', 'admission_number']),
            'student_temporary_password' => $studentTempPassword,
            'guardian_email' => $guardianUser->email,
            'guardian_temporary_password' => $guardianUser->wasRecentlyCreated ? $guardianTempPassword : null,
        ]);
    }

    public function reject(EnrollmentApplication $enrollmentApplication)
    {
        $this->authorizeSameSchool($enrollmentApplication);
        abort_if($enrollmentApplication->status !== 'pending', 422, 'This application has already been reviewed.');

        $validated = request()->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $enrollmentApplication->update([
            'status' => 'rejected',
            'rejection_reason' => $validated['reason'],
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        // The proof image served its purpose; no need to keep holding
        // someone's private payment evidence for a rejected application.
        if ($enrollmentApplication->payment_proof_path) {
            Storage::disk('private')->delete($enrollmentApplication->payment_proof_path);
        }

        return response()->json(['message' => 'Application rejected.']);
    }

    private function authorizeSameSchool(EnrollmentApplication $application): void
    {
        abort_if((int) $application->school_id !== (int) Auth::user()->school_id, 404);
    }
}
