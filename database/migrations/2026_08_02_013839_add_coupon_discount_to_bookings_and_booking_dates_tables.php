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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('coupon_discount', 10, 2)->default(0.00)->after('payment_status');
        });

        Schema::table('booking_dates', function (Blueprint $table) {
            $table->decimal('coupon_discount', 10, 2)->default(0.00)->after('amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('coupon_discount');
        });

        Schema::table('booking_dates', function (Blueprint $table) {
            $table->dropColumn('coupon_discount');
        });
    }
};
