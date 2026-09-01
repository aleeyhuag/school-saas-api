<?php

namespace App\Http\Requests\Academic;

use App\Services\StudentPromotionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentPromotionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $schoolId = $this->user()->school_id;

        return [
            'from_academic_session_id' => ['required','integer', Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId)],
            'to_academic_session_id' => ['required','integer', Rule::exists('academic_sessions', 'id')->where('school_id', $schoolId), 'different:from_academic_session_id'],
            'changes' => ['required','array','min:1'],
            'changes.*.student_ids' => ['required','array','min:1'],
            'changes.*.student_ids.*' => ['integer','distinct', Rule::exists('students', 'id')->where('school_id', $schoolId)],
            'changes.*.from_school_class_id' => ['nullable','integer', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'changes.*.to_school_class_id' => ['nullable','integer', Rule::exists('school_classes', 'id')->where('school_id', $schoolId)],
            'changes.*.action' => ['required', Rule::in(StudentPromotionService::ACTIONS)],
            'changes.*.note' => ['nullable','string','max:1000'],
        ];
    }
}
