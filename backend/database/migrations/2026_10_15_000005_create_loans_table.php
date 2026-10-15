<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->string('loan_name');
            $table->decimal('loan_amount', 19, 4);
            $table->unsignedInteger('months_to_pay');
            $table->decimal('monthly_amortization', 19, 4);
            $table->date('loan_date');
            $table->date('start_date');
            $table->decimal('down_payment', 19, 4);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('downpayment_rate_id')->constrained('downpayment_rates')->restrictOnDelete();
            $table->foreignId('loan_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('loan_interest_rate_id')->constrained()->restrictOnDelete();
            $table->foreignId('penalty_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index(['loan_date', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
