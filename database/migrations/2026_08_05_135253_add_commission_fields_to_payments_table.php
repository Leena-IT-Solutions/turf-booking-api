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
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('commission_percentage', 5, 2)->nullable()->after('amount');
            $table->decimal('commission_amount', 10, 2)->nullable()->after('commission_percentage');
            $table->decimal('cash_held_amount', 10, 2)->nullable()->after('commission_amount');
            $table->decimal('turf_payout_amount', 10, 2)->nullable()->after('cash_held_amount');
            $table->timestamp('wallet_cleared_at')->nullable()->after('turf_payout_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'commission_percentage',
                'commission_amount',
                'cash_held_amount',
                'turf_payout_amount',
                'wallet_cleared_at',
            ]);
        });
    }
};
