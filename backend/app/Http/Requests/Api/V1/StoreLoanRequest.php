<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'loan_name' => ['required', 'string', 'max:255'],
            'loan_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'months_to_pay' => ['required', 'integer', 'between:1,600'],
            'loan_date' => ['required', 'date'],
            'start_date' => ['required', 'date'],
            'user_id' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'user')->where('is_active', true))],
            'downpayment_rate_id' => ['required', 'exists:downpayment_rates,id'],
            'loan_category_id' => ['required', 'exists:loan_categories,id'],
            'loan_interest_rate_id' => ['required', 'exists:loan_interest_rates,id'],
            'penalty_id' => ['required', 'exists:penalties,id'],
        ];
    }
}
