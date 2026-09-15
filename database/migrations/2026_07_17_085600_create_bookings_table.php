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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_number', 30)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turf_id')->constrained()->cascadeOnDelete();
            $table->timestamp('date_of_booking');
            $table->string('booking_type')->default('day'); // day, long, scattered
            $table->string('status')->default('Confirmed'); // Confirmed, Cancelled
            
            // Cancellation & Refund
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('cancellation_fee_applied', 10, 2)->default(0.00);
            $table->decimal('refund_amount', 10, 2)->default(0.00);
            $table->string('refund_status')->default('None');
            $table->timestamp('refunded_at')->nullable();

            $table->string('payment_status')->default('Paid'); // Paid, Unpaid
            $table->decimal('coupon_discount', 10, 2)->default(0.00);
            $table->decimal('additional_discount', 10, 2)->default(0.00);

            // Financial Breakdown & GST
            $table->decimal('taxable_amount', 10, 2)->default(0.00);
            $table->decimal('turf_gst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_cgst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_sgst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_gst_rate', 5, 2)->default(0.00);
            $table->string('turf_gst_type', 20)->default('exempt');

            // Platform Fee
            $table->decimal('platform_fee', 10, 2)->default(0.00);
            $table->decimal('platform_fee_gst', 10, 2)->default(0.00);
            $table->decimal('platform_fee_cgst', 10, 2)->default(0.00);
            $table->decimal('platform_fee_sgst', 10, 2)->default(0.00);
            $table->decimal('platform_fee_igst', 10, 2)->default(0.00);

            // Totals & Part Payment
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('payable_now', 10, 2)->default(0.00);
            $table->decimal('balance_amount', 10, 2)->default(0.00);
            $table->boolean('is_part_payment')->default(false);

            // B2B Details
            $table->string('customer_gstin', 15)->nullable();
            $table->string('customer_company_name', 150)->nullable();

            // Payment Gateway Charges
            $table->decimal('gateway_charge_amount', 10, 2)->default(0.00);
            $table->decimal('gateway_tax_amount', 10, 2)->default(0.00);

            // Commission & Payout
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->decimal('commission_gst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_cgst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_sgst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_igst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_payout_amount', 10, 2)->default(0.00);

            // Cancellation Policy Snapshot
            $table->boolean('is_cancellation_active')->default(false);
            $table->integer('cancellation_hours')->default(0);
            $table->decimal('cancellation_turf_fee', 10, 2)->default(0.00);
            $table->decimal('cancellation_platform_fee_pct', 5, 2)->default(0.00);
            $table->decimal('estimated_refund_amount', 10, 2)->default(0.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
