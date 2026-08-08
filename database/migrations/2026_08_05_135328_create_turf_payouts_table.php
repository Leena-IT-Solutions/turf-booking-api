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
        Schema::create('turf_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('requested_amount', 10, 2);
            $table->decimal('charge_applied', 10, 2)->default(0.00);
            $table->decimal('net_amount', 10, 2);
            $table->string('status')->default('pending'); // 'pending', 'processing', 'completed', 'failed', 'rejected'
            $table->string('triggered_by')->default('manual'); // 'manual', 'scheduled'
            $table->string('razorpay_contact_id')->nullable();
            $table->string('razorpay_fund_account_id')->nullable();
            $table->string('razorpay_payout_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turf_payouts');
    }
};
