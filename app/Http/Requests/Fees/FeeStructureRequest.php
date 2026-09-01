<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where('school_id', $schoolId)],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)], // omit = applies to whole school
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
