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
        Schema::create('payment_gateway_charges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 50)->nullable()->unique();
            $table->decimal('charge_percentage', 5, 2)->default(0.00);
            $table->decimal('tax_percentage', 5, 2)->default(18.00);
            $table->decimal('total_percentage', 6, 4)->default(0.0000);
            $table->decimal('flat_fee', 8, 2)->default(0.00);
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_charges');
    }
};
