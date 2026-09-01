<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Ignore the current student's own admission number when updating
        $studentId = $this->route('student')?->id;

        return [
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where('school_id', $this->user()->school_id)],
            'admission_number' => [
                'required', 'string', 'max:50',
                Rule::unique('students', 'admission_number')->ignore($studentId),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            // Only used on create — a real email for the student's
            // auto-created login. If omitted, a placeholder is
            // generated from the admission number instead.
            'login_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
        ];
    }
}
