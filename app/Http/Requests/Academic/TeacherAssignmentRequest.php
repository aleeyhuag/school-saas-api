<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $schoolId = (int) $this->user()->school_id;

        return [
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'school_class_id' => [
                'required', 'integer',
                Rule::exists('school_classes', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'subject_id' => [
                'nullable', 'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'is_class_teacher' => ['boolean'],
        ];
    }
}
