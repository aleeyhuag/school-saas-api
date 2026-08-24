<?php

namespace App\Http\Controllers\Api\School;

use App\Http\Controllers\Controller;
use App\Http\Requests\School\UpdateSchoolProfileRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Lets a Proprietor/Principal edit their OWN school's identity —
 * name, contact info, and logo. Previously only super_admin could
 * even view this data; there was no way for a school to manage its
 * own profile at all.
 */
class SchoolProfileController extends Controller
{
    public function show()
    {
        return Auth::user()->school;
    }

    public function update(UpdateSchoolProfileRequest $request)
    {
        $school = Auth::user()->school;
        $validated = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            // Replace the old logo file, if any, so uploads don't
            // accumulate unused files on disk indefinitely.
            if ($school->logo_path) {
                Storage::disk('public')->delete($school->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('school-logos', 'public');
        }

        $school->update($validated);

        return $school->fresh();
    }

    /**
     * A signature image printed on every ID card generated for this
     * school — proprietor or principal, either
     * can upload/replace it; whichever of them actually holds the pen
     * varies by school and isn't something worth restricting here.
     */
    public function uploadPrincipalSignature()
    {
        request()->validate([
            'signature' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:1024'], // 1MB — a signature scan doesn't need to be large
        ]);

        $school = Auth::user()->school;

        if ($school->principal_signature_path) {
            Storage::disk('public')->delete($school->principal_signature_path);
        }

        $path = request()->file('signature')->store('school-signatures', 'public');
        $school->update(['principal_signature_path' => $path]);

        return $school->fresh();
    }
}
