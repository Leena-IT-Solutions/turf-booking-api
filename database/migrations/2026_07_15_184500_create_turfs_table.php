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
        Schema::create('turfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // 'Synthetic', 'Hard', 'Other'
            $table->text('description')->nullable();
            $table->string('area')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('Draft');
            $table->text('equipments')->nullable();
            $table->json('pricing_wizard_data')->nullable();

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

            // Verification status
            $table->boolean('is_location_verified')->default(false);
            $table->boolean('is_details_verified')->default(false);
            $table->boolean('is_photos_verified')->default(false);
            $table->boolean('is_facilities_verified')->default(false);
            $table->boolean('is_equipments_verified')->default(false);
            $table->boolean('is_sports_verified')->default(false);
            $table->boolean('is_slots_verified')->default(false);
            $table->boolean('is_pricing_verified')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turfs');
    }
};
