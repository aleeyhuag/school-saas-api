<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TermRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;

        return [
            'academic_session_id' => [
                'required', 'integer',
                Rule::exists('academic_sessions', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'name' => ['required', Rule::in(['First', 'Second', 'Third'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['boolean'],
        ];
    }
}
