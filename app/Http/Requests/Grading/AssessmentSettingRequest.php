<?php

namespace App\Http\Requests\Grading;

use Illuminate\Foundation\Http\FormRequest;

class AssessmentSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // How many continuous assessments this school runs (1, 2 or 3).
            'ca_count' => ['required', 'integer', 'min:1', 'max:3'],
            // CA 1 uses ca_weight (kept from before); CA 2 / CA 3 are new.
            'ca_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'ca2_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'ca3_weight' => ['nullable', 'integer', 'min:0', 'max:100'],
            'assignment_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'exam_weight' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * The weights of the CAs this school uses, plus assignment and exam,
     * must add up to exactly 100. CAs beyond ca_count are ignored (saved as 0).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $caCount = (int) $this->input('ca_count');
            $sum = (int) $this->input('ca_weight')
                + ($caCount >= 2 ? (int) $this->input('ca2_weight') : 0)
                + ($caCount >= 3 ? (int) $this->input('ca3_weight') : 0)
                + (int) $this->input('assignment_weight')
                + (int) $this->input('exam_weight');

            if ($sum !== 100) {
                $validator->errors()->add('ca_weight', "Weights must add up to 100 (currently {$sum}).");
            }

            if ((int) $this->input('exam_weight') < 1) {
                $validator->errors()->add('exam_weight', 'The exam must carry some weight.');
            }
        });
    }

    /** Weights for CAs the school does not use are stored as 0. */
    public function validated($key = null, $default = null)
    {
        $data = parent::validated();
        $caCount = (int) $data['ca_count'];
        $data['ca2_weight'] = $caCount >= 2 ? (int) ($data['ca2_weight'] ?? 0) : 0;
        $data['ca3_weight'] = $caCount >= 3 ? (int) ($data['ca3_weight'] ?? 0) : 0;

        return $key ? data_get($data, $key, $default) : $data;
    }
}
