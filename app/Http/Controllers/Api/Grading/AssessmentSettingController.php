<?php

namespace App\Http\Controllers\Api\Grading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\AssessmentSettingRequest;
use App\Models\AssessmentSetting;
use Illuminate\Support\Facades\Auth;

class AssessmentSettingController extends Controller
{
    /**
     * Get the school's current CA/Assignment/Exam weighting.
     * Auto-creates the default (30/10/60) the first time it's requested.
     */
    public function show()
    {
        return AssessmentSetting::firstOrCreate(
            ['school_id' => Auth::user()->school_id],
            ['ca_weight' => 30, 'assignment_weight' => 10, 'exam_weight' => 60]
        );
    }

    /**
     * Update the school's weighting. This is a single settings record
     * per school (not a list) — set it once per session and every
     * result computed afterward uses the new weights automatically.
     */
    public function update(AssessmentSettingRequest $request)
    {
        $settings = AssessmentSetting::updateOrCreate(
            ['school_id' => Auth::user()->school_id],
            $request->validated()
        );

        return $settings;
    }
}
