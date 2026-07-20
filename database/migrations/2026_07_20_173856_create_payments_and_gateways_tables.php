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
            $table->string('payment_status')->default('Unpaid')->after('additional_discount');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_date_id')->constrained('booking_dates')->cascadeOnDelete();
            $table->string('payment_method'); // App, UPI, Cash, Other
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('Pending'); // Pending, Success, Failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_name'); // razorpay
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('gateway_signature')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('payments');
        
        Schema::table('booking_dates', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
    }
};
