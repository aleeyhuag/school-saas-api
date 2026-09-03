<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CreateSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level role:super_admin middleware already restricts this
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:255'],
            'school_email' => ['nullable', 'email', 'max:255'],
            'school_phone' => ['nullable', 'string', 'max:30'],
            'school_address' => ['nullable', 'string', 'max:255'],

            'admin_role' => ['nullable', 'string', 'in:proprietor,principal'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            // No admin_password field — a temporary one is always
            // generated for admin-created schools, same as inviting
            // staff, since you're creating this on the school's
            // behalf rather than them choosing their own upfront.
        ];
    }
}
