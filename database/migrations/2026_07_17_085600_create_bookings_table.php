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
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->date('booking_date');
            $table->string('booking_type')->default('day'); // day, long, scattered
            $table->string('status')->default('Confirmed'); // Confirmed, Cancelled
            $table->string('payment_status')->default('Paid'); // Paid, Unpaid
            $table->decimal('price', 10, 2)->default(0.00);
            $table->timestamps();

            // A turf slot can only be booked once per date
            $table->unique(['turf_id', 'slot_id', 'booking_date']);
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
