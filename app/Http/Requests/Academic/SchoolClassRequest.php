<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;

class SchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route-level middleware already restricts by role
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],   // e.g. "JSS 1", "Primary 5"
            'arm' => ['nullable', 'string', 'max:50'],       // e.g. "A", "B"
            'level' => ['nullable', 'integer', 'min:0'],     // numeric ordering
        ];
    }
}
