<?php

namespace App\Http\Controllers\Api\Grading;

use App\Http\Controllers\Controller;
use App\Http\Requests\Grading\AssessmentSettingRequest;
use App\Models\AssessmentSetting;
use App\Services\ResultService;
use Illuminate\Support\Facades\Auth;

class AssessmentSettingController extends Controller
{
    public function __construct(protected ResultService $resultService) {}

    /**
     * The school's current assessment setup: how many CAs, and each weight.
     * Read-only for everyone who can see it (teachers need the weights to
     * enter scores), so it never writes — a school that has not set anything
     * yet simply gets the defaults (1 CA: 30 / assignment 10 / exam 60).
     * `components` is the ready-to-use list of columns for score entry,
     * marksheets and report cards.
     */
    public function show()
    {
        return $this->payload(Auth::user()->school_id);
    }

    /**
     * Update the setup. One settings record per school — set it once per
     * session and every result computed afterward uses the new weights.
     */
    public function update(AssessmentSettingRequest $request)
    {
        AssessmentSetting::updateOrCreate(
            ['school_id' => Auth::user()->school_id],
            $request->validated()
        );

        return $this->payload(Auth::user()->school_id);
    }

    protected function payload(int $schoolId): array
    {
        $settings = AssessmentSetting::where('school_id', $schoolId)->first()
            ?? new AssessmentSetting([
                'school_id' => $schoolId, 'ca_count' => 1, 'ca_weight' => 30, 'ca2_weight' => 0,
                'ca3_weight' => 0, 'assignment_weight' => 10, 'exam_weight' => 60,
            ]);

        return [
            'ca_count' => (int) ($settings->ca_count ?? 1),
            'ca_weight' => (int) $settings->ca_weight,
            'ca2_weight' => (int) ($settings->ca2_weight ?? 0),
            'ca3_weight' => (int) ($settings->ca3_weight ?? 0),
            'assignment_weight' => (int) $settings->assignment_weight,
            'exam_weight' => (int) $settings->exam_weight,
            'components' => $this->resultService->componentsFor($schoolId),
        ];
    }
}
