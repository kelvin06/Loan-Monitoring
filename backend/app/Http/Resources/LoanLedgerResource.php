<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'amount' => $this->amount,
            'payment_date' => $this->payment_date?->format('Y-m-d'),
            'payment_reference' => $this->payment_reference,
            'due_date' => $this->due_date->format('Y-m-d'),
            'loan_name' => $this->whenLoaded('loan', fn () => $this->loan->loan_name),
        ];
    }
}
