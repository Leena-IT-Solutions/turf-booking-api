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
        Schema::create('booking_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_date_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('canceller_role', 30)->default('customer');
            $table->string('cancellation_scope', 30)->default('full_booking');
            $table->text('reason')->nullable();
            $table->decimal('gross_cancelled_amount', 10, 2)->default(0.00);
            $table->decimal('turf_cancellation_fee', 10, 2)->default(0.00);
            $table->decimal('platform_cancellation_fee', 10, 2)->default(0.00);
            $table->decimal('total_cancellation_fee', 10, 2)->default(0.00);
            $table->decimal('refund_amount', 10, 2)->default(0.00);
            $table->string('refund_status', 30)->default('Pending');
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
    }
};
