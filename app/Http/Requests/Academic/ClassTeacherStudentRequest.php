<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassTeacherStudentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'first_name' => ['required','string','max:255'],
            'last_name' => ['required','string','max:255'],
            'date_of_birth' => ['nullable','date'],
            'gender' => ['nullable',Rule::in(['male','female'])],
            'guardian_name' => ['nullable','string','max:255'],
            'guardian_phone' => ['nullable','string','max:30'],
        ];
    }
}
