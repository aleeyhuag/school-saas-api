<?php

namespace App\Http\Requests\Fees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordFeePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $schoolId = $this->user()?->school_id;
        return [
            'student_id' => [
                'required', 'integer',
                Rule::exists('students', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'fee_structure_id' => [
                'required', 'integer',
                Rule::exists('fee_structures', 'id')->where(fn ($q) => $q->where('school_id', $schoolId)),
            ],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(['cash', 'bank_transfer', 'paystack', 'flutterwave', 'other'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['required', 'date'],
        ];
    }
}
