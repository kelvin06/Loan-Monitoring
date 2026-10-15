<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_name' => $this->loan_name,
            'loan_amount' => $this->loan_amount,
            'months_to_pay' => $this->months_to_pay,
            'monthly_amortization' => $this->monthly_amortization,
            'balance' => (string) ($this->balance ?? '0.0000'),
            'loan_date' => $this->loan_date->format('Y-m-d'),
            'start_date' => $this->start_date->format('Y-m-d'),
            'down_payment' => $this->down_payment,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'downpayment_rate_id' => $this->downpayment_rate_id,
            'downpayment_rate_name' => $this->whenLoaded('downpaymentRate', fn () => $this->downpaymentRate->name),
            'loan_category_id' => $this->loan_category_id,
            'loan_category_name' => $this->whenLoaded('category', fn () => $this->category->name),
            'loan_interest_rate_id' => $this->loan_interest_rate_id,
            'loan_interest_rate_name' => $this->whenLoaded('interestRate', fn () => $this->interestRate->name),
            'penalty_id' => $this->penalty_id,
            'penalty_name' => $this->whenLoaded('penalty', fn () => $this->penalty->name),
            'ledgers' => LoanLedgerResource::collection($this->whenLoaded('ledgers')),
            'created_at' => $this->created_at,
        ];
    }
}
