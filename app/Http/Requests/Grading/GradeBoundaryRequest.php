<?php

namespace App\Http\Requests\Grading;

use Illuminate\Foundation\Http\FormRequest;

class GradeBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grade' => ['required', 'string', 'max:5'],
            'remark' => ['nullable', 'string', 'max:255'],
            'min_score' => ['required', 'integer', 'min:0', 'max:100'],
            'max_score' => ['required', 'integer', 'min:0', 'max:100', 'gte:min_score'],
        ];
    }
}
