<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $studentId = $this->route('student')?->id;
        $autoGenerate = (bool) $this->user()->school->auto_generate_admission_numbers;
        return [
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where('school_id', $this->user()->school_id)],
            'admission_number' => $autoGenerate ? ['prohibited'] : ['required', 'string', 'max:50', Rule::unique('students', 'admission_number')->ignore($studentId)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'login_email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->where(fn ($q) => $q->where('school_id', $this->user()->school_id))],
        ];
    }
}
