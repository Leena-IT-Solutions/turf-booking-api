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
        // 1. Add fields to bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('booking_number', 30)->nullable()->unique()->after('id');
            $table->decimal('taxable_amount', 10, 2)->default(0.00)->after('additional_discount');
            $table->decimal('turf_gst_amount', 10, 2)->default(0.00)->after('taxable_amount');
            $table->decimal('turf_cgst_amount', 10, 2)->default(0.00)->after('turf_gst_amount');
            $table->decimal('turf_sgst_amount', 10, 2)->default(0.00)->after('turf_cgst_amount');
            $table->decimal('turf_gst_rate', 5, 2)->default(0.00)->after('turf_sgst_amount');
            $table->string('turf_gst_type', 20)->default('exempt')->after('turf_gst_rate');
            $table->decimal('platform_fee', 10, 2)->default(0.00)->after('turf_gst_type');
            $table->decimal('platform_fee_gst', 10, 2)->default(0.00)->after('platform_fee');
            $table->decimal('platform_fee_cgst', 10, 2)->default(0.00)->after('platform_fee_gst');
            $table->decimal('platform_fee_sgst', 10, 2)->default(0.00)->after('platform_fee_cgst');
            $table->decimal('platform_fee_igst', 10, 2)->default(0.00)->after('platform_fee_sgst');
            $table->decimal('total_amount', 10, 2)->default(0.00)->after('platform_fee_igst');
            $table->decimal('payable_now', 10, 2)->default(0.00)->after('total_amount');
            $table->decimal('balance_amount', 10, 2)->default(0.00)->after('payable_now');
            $table->boolean('is_part_payment')->default(false)->after('balance_amount');
            $table->string('customer_gstin', 15)->nullable()->after('is_part_payment');
            $table->string('customer_company_name', 150)->nullable()->after('customer_gstin');
            $table->decimal('gateway_charge_amount', 10, 2)->default(0.00)->after('customer_company_name');
            $table->decimal('gateway_tax_amount', 10, 2)->default(0.00)->after('gateway_charge_amount');
            $table->decimal('commission_rate', 5, 2)->default(0.00)->after('gateway_tax_amount');
            $table->decimal('commission_amount', 10, 2)->default(0.00)->after('commission_rate');
            $table->decimal('commission_gst_amount', 10, 2)->default(0.00)->after('commission_amount');
            $table->decimal('commission_cgst_amount', 10, 2)->default(0.00)->after('commission_gst_amount');
            $table->decimal('commission_sgst_amount', 10, 2)->default(0.00)->after('commission_cgst_amount');
            $table->decimal('commission_igst_amount', 10, 2)->default(0.00)->after('commission_sgst_amount');
            $table->decimal('turf_payout_amount', 10, 2)->default(0.00)->after('commission_igst_amount');
            $table->boolean('is_cancellation_active')->default(false)->after('turf_payout_amount');
            $table->integer('cancellation_hours')->default(0)->after('is_cancellation_active');
            $table->decimal('cancellation_turf_fee', 10, 2)->default(0.00)->after('cancellation_hours');
            $table->decimal('cancellation_platform_fee_pct', 5, 2)->default(0.00)->after('cancellation_turf_fee');
            $table->decimal('estimated_refund_amount', 10, 2)->default(0.00)->after('cancellation_platform_fee_pct');
        });

        // 2. Add fields to booking_dates
        Schema::table('booking_dates', function (Blueprint $table) {
            $table->decimal('taxable_amount', 10, 2)->default(0.00)->after('amount');
            $table->decimal('turf_gst_amount', 10, 2)->default(0.00)->after('taxable_amount');
            $table->decimal('turf_cgst_amount', 10, 2)->default(0.00)->after('turf_gst_amount');
            $table->decimal('turf_sgst_amount', 10, 2)->default(0.00)->after('turf_cgst_amount');
            $table->decimal('paid_amount', 10, 2)->default(0.00)->after('turf_sgst_amount');
            $table->decimal('balance_amount', 10, 2)->default(0.00)->after('paid_amount');
            $table->decimal('commission_rate', 5, 2)->default(0.00)->after('balance_amount');
            $table->decimal('commission_amount', 10, 2)->default(0.00)->after('commission_rate');
            $table->decimal('commission_gst_amount', 10, 2)->default(0.00)->after('commission_amount');
            $table->decimal('commission_cgst_amount', 10, 2)->default(0.00)->after('commission_gst_amount');
            $table->decimal('commission_sgst_amount', 10, 2)->default(0.00)->after('commission_cgst_amount');
            $table->decimal('commission_igst_amount', 10, 2)->default(0.00)->after('commission_sgst_amount');
            $table->decimal('turf_payout_amount', 10, 2)->default(0.00)->after('commission_igst_amount');
            $table->decimal('cash_held_amount', 10, 2)->default(0.00)->after('turf_payout_amount');
            $table->decimal('cancellation_turf_fee', 10, 2)->default(0.00)->after('cash_held_amount');
            $table->decimal('cancellation_platform_fee', 10, 2)->default(0.00)->after('cancellation_turf_fee');
            $table->decimal('estimated_refund_amount', 10, 2)->default(0.00)->after('cancellation_platform_fee');
        });

        // 3. Add status to booking_slots
        Schema::table('booking_slots', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('slot_id');
        });

        // 4. Add fields to payments
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('commission_gst_amount', 10, 2)->default(0.00)->after('commission_amount');
            $table->decimal('commission_cgst_amount', 10, 2)->default(0.00)->after('commission_gst_amount');
            $table->decimal('commission_sgst_amount', 10, 2)->default(0.00)->after('commission_cgst_amount');
            $table->decimal('commission_igst_amount', 10, 2)->default(0.00)->after('commission_sgst_amount');
            $table->decimal('gateway_charge_amount', 10, 2)->default(0.00)->after('turf_payout_amount');
            $table->decimal('gateway_tax_amount', 10, 2)->default(0.00)->after('gateway_charge_amount');
        });

        // 5. Create booking_cancellations table
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('booking_date_id')->nullable()->constrained('booking_dates')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('canceller_role', 30)->default('customer');
            $table->string('cancellation_scope', 30)->default('full_booking'); // full_booking, date
            $table->text('reason')->nullable();
            $table->decimal('gross_cancelled_amount', 10, 2)->default(0.00);
            $table->decimal('turf_cancellation_fee', 10, 2)->default(0.00);
            $table->decimal('platform_cancellation_fee', 10, 2)->default(0.00);
            $table->decimal('total_cancellation_fee', 10, 2)->default(0.00);
            $table->decimal('refund_amount', 10, 2)->default(0.00);
            $table->string('refund_status', 30)->default('Pending'); // Pending, Refunded, Cash / Offline Refund, Failed, Not Applicable
            $table->string('razorpay_refund_id', 100)->nullable();
            $table->decimal('commission_reversed_amount', 10, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_cancellations');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'commission_gst_amount',
                'commission_cgst_amount',
                'commission_sgst_amount',
                'commission_igst_amount',
                'gateway_charge_amount',
                'gateway_tax_amount',
            ]);
        });

        Schema::table('booking_slots', function (Blueprint $table) {
            $table->dropColumn(['status']);
        });

        Schema::table('booking_dates', function (Blueprint $table) {
            $table->dropColumn([
                'taxable_amount',
                'turf_gst_amount',
                'turf_cgst_amount',
                'turf_sgst_amount',
                'paid_amount',
                'balance_amount',
                'commission_rate',
                'commission_amount',
                'commission_gst_amount',
                'commission_cgst_amount',
                'commission_sgst_amount',
                'commission_igst_amount',
                'turf_payout_amount',
                'cash_held_amount',
                'cancellation_turf_fee',
                'cancellation_platform_fee',
                'estimated_refund_amount',
            ]);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'booking_number',
                'taxable_amount',
                'turf_gst_amount',
                'turf_cgst_amount',
                'turf_sgst_amount',
                'turf_gst_rate',
                'turf_gst_type',
                'platform_fee',
                'platform_fee_gst',
                'platform_fee_cgst',
                'platform_fee_sgst',
                'platform_fee_igst',
                'total_amount',
                'payable_now',
                'balance_amount',
                'is_part_payment',
                'customer_gstin',
                'customer_company_name',
                'gateway_charge_amount',
                'gateway_tax_amount',
                'commission_rate',
                'commission_amount',
                'commission_gst_amount',
                'commission_cgst_amount',
                'commission_sgst_amount',
                'commission_igst_amount',
                'turf_payout_amount',
                'is_cancellation_active',
                'cancellation_hours',
                'cancellation_turf_fee',
                'cancellation_platform_fee_pct',
                'estimated_refund_amount',
            ]);
        });
    }
};
