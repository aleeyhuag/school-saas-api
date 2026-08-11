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
            'ca_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'assignment_weight' => ['required', 'integer', 'min:0', 'max:100'],
            'exam_weight' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Extra validation that can't be expressed as simple field rules:
     * the three weights must sum to exactly 100.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $sum = (int) $this->input('ca_weight') + (int) $this->input('assignment_weight') + (int) $this->input('exam_weight');

            if ($sum !== 100) {
                $validator->errors()->add('ca_weight', "Weights must sum to 100 (currently {$sum}).");
            }
        });
    }
}
