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
        return [
            'from_academic_session_id' => ['required','integer','exists:academic_sessions,id'],
            'to_academic_session_id' => ['required','integer','exists:academic_sessions,id','different:from_academic_session_id'],
            'changes' => ['required','array','min:1'],
            'changes.*.student_ids' => ['required','array','min:1'],
            'changes.*.student_ids.*' => ['integer','distinct','exists:students,id'],
            'changes.*.from_school_class_id' => ['nullable','integer','exists:school_classes,id'],
            'changes.*.to_school_class_id' => ['nullable','integer','exists:school_classes,id'],
            'changes.*.action' => ['required', Rule::in(StudentPromotionService::ACTIONS)],
            'changes.*.note' => ['nullable','string','max:1000'],
        ];
    }
}
