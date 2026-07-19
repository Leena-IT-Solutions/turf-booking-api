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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turf_id')->constrained()->cascadeOnDelete();
            $table->timestamp('date_of_booking');
            $table->string('booking_type')->default('day'); // day, long, scattered
            $table->string('status')->default('Confirmed'); // Confirmed, Cancelled
            $table->string('payment_status')->default('Paid'); // Paid, Unpaid
            $table->decimal('additional_discount', 10, 2)->default(0.00);
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
