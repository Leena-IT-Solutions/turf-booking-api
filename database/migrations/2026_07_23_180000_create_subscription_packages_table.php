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
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->integer('days')->default(30);
            $table->decimal('total_percentage', 5, 2)->default(0.00);
            $table->decimal('payment_gateway_percentage', 5, 2)->default(0.00);
            $table->decimal('commission_percentage', 5, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            
            // Suggested fields
            $table->string('badge')->nullable();
            $table->integer('max_turfs')->nullable()->default(1);
            $table->integer('max_managers')->nullable()->default(3);
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
