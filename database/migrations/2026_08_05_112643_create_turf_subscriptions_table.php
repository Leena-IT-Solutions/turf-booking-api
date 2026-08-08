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
        Schema::create('turf_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subscription_package_id')->nullable()->constrained('subscription_packages')->onDelete('set null');
            $table->string('billing_cycle')->default('monthly'); // 'monthly' or 'yearly'
            $table->decimal('price', 10, 2);
            $table->decimal('commission_percentage', 5, 2);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // 'active', 'expired', 'cancelled'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turf_subscriptions');
    }
};
