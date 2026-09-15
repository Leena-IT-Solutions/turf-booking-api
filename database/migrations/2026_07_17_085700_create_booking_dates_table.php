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
        Schema::create('booking_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->date('booking_date');
            $table->string('status')->default('Confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->decimal('cancellation_fee_applied', 10, 2)->default(0.00);
            $table->decimal('refund_amount', 10, 2)->default(0.00);
            $table->string('refund_status')->default('None');
            $table->timestamp('refunded_at')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('taxable_amount', 10, 2)->default(0.00);
            $table->decimal('turf_gst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_cgst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_sgst_amount', 10, 2)->default(0.00);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->decimal('balance_amount', 10, 2)->default(0.00);
            $table->decimal('commission_rate', 5, 2)->default(0.00);
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->decimal('commission_gst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_cgst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_sgst_amount', 10, 2)->default(0.00);
            $table->decimal('commission_igst_amount', 10, 2)->default(0.00);
            $table->decimal('turf_payout_amount', 10, 2)->default(0.00);
            $table->decimal('cash_held_amount', 10, 2)->default(0.00);
            $table->decimal('cancellation_turf_fee', 10, 2)->default(0.00);
            $table->decimal('cancellation_platform_fee', 10, 2)->default(0.00);
            $table->decimal('estimated_refund_amount', 10, 2)->default(0.00);
            $table->decimal('coupon_discount', 10, 2)->default(0.00);
            $table->decimal('additional_discount', 10, 2)->default(0.00);
            $table->string('payment_status')->default('Unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_dates');
    }
};
