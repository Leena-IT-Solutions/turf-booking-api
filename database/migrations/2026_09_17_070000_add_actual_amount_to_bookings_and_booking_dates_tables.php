<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'actual_amount')) {
                $table->decimal('actual_amount', 10, 2)->default(0.00)->after('payment_status');
            }
        });

        Schema::table('booking_dates', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_dates', 'actual_amount')) {
                $table->decimal('actual_amount', 10, 2)->default(0.00)->after('refunded_at');
            }
        });

        // Backfill existing records if any
        DB::statement("UPDATE bookings SET actual_amount = ROUND(taxable_amount + turf_gst_amount + coupon_discount + additional_discount, 2) WHERE actual_amount = 0");
        DB::statement("UPDATE booking_dates SET actual_amount = ROUND(taxable_amount + turf_gst_amount + coupon_discount + additional_discount, 2) WHERE actual_amount = 0");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'actual_amount')) {
                $table->dropColumn('actual_amount');
            }
        });

        Schema::table('booking_dates', function (Blueprint $table) {
            if (Schema::hasColumn('booking_dates', 'actual_amount')) {
                $table->dropColumn('actual_amount');
            }
        });
    }
};
