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
            $table->decimal('monthly_amount', 10, 2)->default(0.00);
            $table->decimal('yearly_amount', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_offer_active')->default(false);
            $table->string('offer_badge', 150)->nullable();
            $table->decimal('offer_monthly_amount', 10, 2)->nullable();
            $table->decimal('offer_yearly_amount', 10, 2)->nullable();
            $table->unsignedInteger('offer_max_claims')->nullable()->default(100);
            $table->unsignedInteger('offer_claimed_count')->default(0);
            $table->date('offer_expires_at')->nullable();
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
