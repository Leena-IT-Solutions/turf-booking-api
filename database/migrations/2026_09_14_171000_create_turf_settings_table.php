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
        Schema::create('turf_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained('turfs')->cascadeOnDelete()->unique();
            $table->string('company_name')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('country')->default('India');
            $table->string('pincode')->nullable();
            $table->string('gst_number')->nullable();
            $table->boolean('is_gst_billing_active')->default(false);
            $table->string('gst_pricing_type', 20)->default('included');
            $table->decimal('gst_percentage', 5, 2)->default(18.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turf_settings');
    }
};
