<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanLedger extends Model
{
    protected $fillable = ['loan_id', 'amount', 'payment_date', 'payment_reference', 'due_date'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'payment_date' => 'date:Y-m-d', 'due_date' => 'date:Y-m-d'];
    }

    public function loan(): BelongsTo { return $this->belongsTo(Loan::class); }
}
