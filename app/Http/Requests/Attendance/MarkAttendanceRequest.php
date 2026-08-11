<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAttendanceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;
        return [
            'term_id' => ['required', 'integer', Rule::exists('terms', 'id')->where(fn ($q) => $q->where('school_id', $schoolId))],
            'school_class_id' => ['required', 'integer', Rule::exists('school_classes', 'id')->where(fn ($q) => $q->where('school_id', $schoolId))],
            'subject_id' => ['nullable', 'integer', Rule::exists('subjects', 'id')->where(fn ($q) => $q->where('school_id', $schoolId))],
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'integer', Rule::exists('students', 'id')->where(fn ($q) => $q->where('school_id', $schoolId))],
            'records.*.status' => ['required', 'in:present,absent,late,excused'],
        ];
    }
}
