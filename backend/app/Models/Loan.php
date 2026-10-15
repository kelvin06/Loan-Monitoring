<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    protected $fillable = [
        'loan_name', 'loan_amount', 'months_to_pay', 'monthly_amortization',
        'loan_date', 'start_date', 'down_payment', 'user_id', 'downpayment_rate_id',
        'loan_category_id', 'loan_interest_rate_id', 'penalty_id',
    ];

    protected function casts(): array
    {
        return [
            'loan_amount' => 'decimal:4',
            'monthly_amortization' => 'decimal:4',
            'down_payment' => 'decimal:4',
            'loan_date' => 'date:Y-m-d',
            'start_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(LoanCategory::class, 'loan_category_id'); }
    public function downpaymentRate(): BelongsTo { return $this->belongsTo(DownpaymentRate::class); }
    public function interestRate(): BelongsTo { return $this->belongsTo(LoanInterestRate::class, 'loan_interest_rate_id'); }
    public function penalty(): BelongsTo { return $this->belongsTo(Penalty::class); }
    public function ledgers(): HasMany { return $this->hasMany(LoanLedger::class)->orderBy('due_date'); }
}
