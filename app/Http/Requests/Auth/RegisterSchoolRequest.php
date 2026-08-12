<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // open endpoint — anyone can register a new school
    }

    public function rules(): array
    {
        return [
            // School details
            'school_name' => ['required', 'string', 'max:255'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:30'],
            'school_address' => ['nullable', 'string', 'max:255'],

            // Proprietor/admin account details (the first user for this school)
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],

            // Legal acceptance is required before a public school account is created.
            'terms_accepted' => ['required', 'accepted'],
            'privacy_acknowledged' => ['required', 'accepted'],
        ];
    }
}
