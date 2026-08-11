<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;

class FeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'], // omit = applies to whole school
            'name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
