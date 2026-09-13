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
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->boolean('is_offer_active')->default(false)->after('is_active');
            $table->string('offer_badge', 150)->nullable()->after('is_offer_active');
            $table->decimal('offer_monthly_amount', 10, 2)->nullable()->after('offer_badge');
            $table->decimal('offer_yearly_amount', 10, 2)->nullable()->after('offer_monthly_amount');
            $table->unsignedInteger('offer_max_claims')->nullable()->default(100)->after('offer_yearly_amount');
            $table->unsignedInteger('offer_claimed_count')->default(0)->after('offer_max_claims');
            $table->date('offer_expires_at')->nullable()->after('offer_claimed_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn([
                'is_offer_active',
                'offer_badge',
                'offer_monthly_amount',
                'offer_yearly_amount',
                'offer_max_claims',
                'offer_claimed_count',
                'offer_expires_at',
            ]);
        });
    }
};
