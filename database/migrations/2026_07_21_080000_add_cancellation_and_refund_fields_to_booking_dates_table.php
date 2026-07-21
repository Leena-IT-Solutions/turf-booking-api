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
        Schema::table('booking_dates', function (Blueprint $table) {
            $table->string('status')->default('Confirmed')->after('booking_date');
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->decimal('cancellation_fee_applied', 10, 2)->default(0.00)->after('cancelled_at');
            $table->decimal('refund_amount', 10, 2)->default(0.00)->after('cancellation_fee_applied');
            $table->string('refund_status')->default('None')->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_dates', function (Blueprint $table) {
            $table->dropColumn(['status', 'cancelled_at', 'cancellation_fee_applied', 'refund_amount', 'refund_status', 'refunded_at']);
        });
    }
};
