<?php

namespace App\Http\Requests\School;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level role:proprietor|principal middleware already restricts this
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'], // 2MB
            'auto_generate_admission_numbers' => ['nullable', 'boolean'],
            'self_enrollment_enabled' => ['nullable', 'boolean'],
        ];
    }
}
