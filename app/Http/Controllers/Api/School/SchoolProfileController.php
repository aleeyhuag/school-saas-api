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
}
