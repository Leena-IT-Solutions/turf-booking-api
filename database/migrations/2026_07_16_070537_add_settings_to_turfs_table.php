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
        Schema::table('turfs', function (Blueprint $table) {
            // Payment settings
            $table->boolean('is_online_payment_active')->default(true);
            $table->boolean('is_part_payment_active')->default(false);
            $table->boolean('is_pay_at_location_active')->default(false);
            $table->string('part_payment_type')->default('percentage');
            $table->decimal('part_payment_value', 10, 2)->default(50.00);

            // Booking & Window settings
            $table->boolean('is_booking_open')->default(true);
            $table->integer('booking_open_days')->default(90);
            $table->boolean('is_manager_booking_active')->default(true);

            // Cancellation settings
            $table->boolean('is_cancellation_active')->default(false);
            $table->integer('cancellation_hours')->default(48);
            $table->decimal('cancellation_fee', 10, 2)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turfs', function (Blueprint $table) {
            $table->dropColumn([
                'is_online_payment_active',
                'is_part_payment_active',
                'is_pay_at_location_active',
                'part_payment_type',
                'part_payment_value',
                'is_booking_open',
                'booking_open_days',
                'is_manager_booking_active',
                'is_cancellation_active',
                'cancellation_hours',
                'cancellation_fee',
            ]);
        });
    }
};
