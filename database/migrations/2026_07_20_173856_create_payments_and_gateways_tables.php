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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_date_id')->constrained('booking_dates')->cascadeOnDelete();
            $table->string('payment_method'); // App, UPI, Cash, Other
            $table->decimal('amount', 10, 2);

            // Commission & Payout Tracking
            $table->decimal('commission_percentage', 5, 2)->nullable();
            $table->decimal('commission_amount', 10, 2)->nullable();
            $table->decimal('commission_gst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_cgst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_sgst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_igst_amount', 10, 2)->default(0.00);
            $table->decimal('cash_held_amount', 10, 2)->nullable();
            $table->decimal('turf_payout_amount', 10, 2)->nullable();

            // Gateway Fees
            $table->decimal('gateway_charge_amount', 10, 2)->default(0.00);
            $table->decimal('gateway_tax_amount', 10, 2)->default(0.00);
            $table->timestamp('wallet_cleared_at')->nullable();

            $table->string('status')->default('Pending'); // Pending, Success, Failed

            // Cancellation & Refund
            $table->decimal('refunded_amount', 10, 2)->default(0.00);
            $table->string('refund_status')->default('None');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_name'); // razorpay
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('gateway_refund_id')->nullable();
            $table->json('refund_response_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('payments');
    }
};
