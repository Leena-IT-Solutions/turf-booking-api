<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type'); // 'payment_settlement', 'payout_debit', 'payout_reversal', 'commission_due_settlement', 'refund_adjustment'
            $table->decimal('amount', 10, 2); // signed: +credit / -debit
            $table->decimal('balance_after', 12, 2);
            $table->nullableMorphs('reference'); // reference_type & reference_id
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_wallet_transactions');
    }
};
