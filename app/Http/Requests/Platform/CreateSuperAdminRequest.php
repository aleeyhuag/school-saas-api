<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class CreateSuperAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level role:super_admin middleware already restricts this
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'max:255', function ($attribute, $value, $fail) { if (\App\Models\User::where('email', strtolower(trim($value)))->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->exists()) $fail('A Super Admin with this email already exists.'); }],
            // No password field — a temporary one is generated and shown
            // once, same pattern as onboarding a school's proprietor —
            // see PlatformSchoolController::store().
        ];
    }
}
