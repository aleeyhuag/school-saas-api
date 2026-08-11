<?php

namespace App\Http\Requests\Grading;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // exact teacher/resource authorization happens in the controller.
    }

    public function rules(): array
    {
        $schoolId = (int) $this->user()->school_id;

        return [
            'term_id' => [
                'required', 'integer',
                Rule::exists('terms', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'school_class_id' => [
                'required', 'integer',
                Rule::exists('school_classes', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'subject_id' => [
                'required', 'integer',
                Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],

            'ca_score' => ['nullable', 'numeric', 'min:0'],
            'ca_max' => ['nullable', 'numeric', 'min:1'],
            'assignment_score' => ['nullable', 'numeric', 'min:0'],
            'assignment_max' => ['nullable', 'numeric', 'min:1'],
            'exam_score' => ['nullable', 'numeric', 'min:0'],
            'exam_max' => ['nullable', 'numeric', 'min:1'],
            'teacher_comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
