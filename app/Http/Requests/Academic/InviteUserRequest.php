<?php

namespace App\Http\Requests\Academic;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->where(fn ($q) => $q->where('school_id', $this->user()->school_id))],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => [
                'required',
                Rule::in([
                    'proprietor', 'principal', 'bursar', 'exam_officer',
                    'teacher', 'parent',
                    // 'class_teacher' and 'subject_teacher' were
                    // unified into 'teacher' — see the
                    // unify_teacher_roles migration. Which classes/
                    // subjects a teacher actually handles, and
                    // whether that includes class-teacher duties, is
                    // decided entirely on the Teacher Assignments
                    // page, not at invite time.
                    // 'student' deliberately removed — students get a
                    // login automatically when added on the Students
                    // page, never through the general invite flow.
                ]),
            ],
        ];
    }
}
