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
        Schema::table('booking_cancellations', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_cancellations', 'turf_fee_gst_amount')) {
                $table->decimal('turf_fee_gst_amount', 10, 2)->default(0.00)->after('turf_cancellation_fee');
            }
            if (!Schema::hasColumn('booking_cancellations', 'turf_fee_cgst_amount')) {
                $table->decimal('turf_fee_cgst_amount', 10, 2)->default(0.00)->after('turf_fee_gst_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'turf_fee_sgst_amount')) {
                $table->decimal('turf_fee_sgst_amount', 10, 2)->default(0.00)->after('turf_fee_cgst_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'saas_fee_gst_amount')) {
                $table->decimal('saas_fee_gst_amount', 10, 2)->default(0.00)->after('saas_cancellation_fee');
            }
            if (!Schema::hasColumn('booking_cancellations', 'saas_fee_cgst_amount')) {
                $table->decimal('saas_fee_cgst_amount', 10, 2)->default(0.00)->after('saas_fee_gst_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'saas_fee_sgst_amount')) {
                $table->decimal('saas_fee_sgst_amount', 10, 2)->default(0.00)->after('saas_fee_cgst_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'saas_fee_igst_amount')) {
                $table->decimal('saas_fee_igst_amount', 10, 2)->default(0.00)->after('saas_fee_sgst_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'refund_taxable_amount')) {
                $table->decimal('refund_taxable_amount', 10, 2)->default(0.00)->after('refund_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'refund_gst_amount')) {
                $table->decimal('refund_gst_amount', 10, 2)->default(0.00)->after('refund_taxable_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'refund_cgst_amount')) {
                $table->decimal('refund_cgst_amount', 10, 2)->default(0.00)->after('refund_gst_amount');
            }
            if (!Schema::hasColumn('booking_cancellations', 'refund_sgst_amount')) {
                $table->decimal('refund_sgst_amount', 10, 2)->default(0.00)->after('refund_cgst_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $columnsToDrop = [];
            $checkColumns = [
                'turf_fee_gst_amount',
                'turf_fee_cgst_amount',
                'turf_fee_sgst_amount',
                'saas_fee_gst_amount',
                'saas_fee_cgst_amount',
                'saas_fee_sgst_amount',
                'saas_fee_igst_amount',
                'refund_taxable_amount',
                'refund_gst_amount',
                'refund_cgst_amount',
                'refund_sgst_amount',
            ];
            foreach ($checkColumns as $col) {
                if (Schema::hasColumn('booking_cancellations', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
