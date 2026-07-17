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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turf_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->text('description')->nullable();
            $table->string('discount_type'); // 'fixed' or 'percentage'
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable();
            $table->integer('minimum_slots_to_be_ordered')->default(1);
            $table->integer('usage_limit')->nullable();
            $table->integer('usage_limit_per_user')->default(1);
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('mon')->default(true);
            $table->boolean('tue')->default(true);
            $table->boolean('wed')->default(true);
            $table->boolean('thu')->default(true);
            $table->boolean('fri')->default(true);
            $table->boolean('sat')->default(true);
            $table->boolean('sun')->default(true);
            $table->date('starts_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
