<?php

namespace App\Http\Controllers\Api\Enrollment;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentApplication;
use App\Models\School;
use Illuminate\Validation\Rule;

/**
 * Public, unauthenticated — this is what a parent hits from a
 * school's own enrollment link (skulag.com.ng/enroll/{slug}). No
 * auth:sanctum on these routes; identified by the school's slug, and
 * rate-limited at the route level (see routes/api-enrollment.php) the
 * same way register-school is, since this is another public form that
 * writes to the database.
 */
class PublicEnrollmentController extends Controller
{
    /**
     * What the public enrollment page needs to render itself: the
     * school's name/logo and its list of classes to choose from.
     * 404s (not 403) if the school doesn't exist OR hasn't turned
     * self-enrollment on — a disabled link should look like it was
     * never there, not like a locked door.
     */
    public function show(string $slug)
    {
        $school = School::where('slug', $slug)->where('self_enrollment_enabled', true)->firstOrFail();

        return response()->json([
            'school' => $school->only(['id', 'name', 'logo_path']),
            'classes' => $school->schoolClasses()->orderBy('name')->get(['id', 'name', 'arm']),
        ]);
    }

    public function store(string $slug)
    {
        $school = School::where('slug', $slug)->where('self_enrollment_enabled', true)->firstOrFail();

        $validated = request()->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where('school_id', $school->id)],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_email' => ['required', 'email', 'max:255'],
            'guardian_phone' => ['required', 'string', 'max:30'],
            // Same trust/validation level as a subscription payment
            // proof — private disk, image only, 5MB cap.
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $proofPath = request()->file('payment_proof')->store('enrollment-proofs', 'private');

        $application = EnrollmentApplication::create([
            ...collect($validated)->except('payment_proof')->toArray(),
            'school_id' => $school->id,
            'payment_proof_path' => $proofPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Application submitted. The school will review it and get in touch once approved.',
            'application_id' => $application->id,
        ], 201);
    }
}
