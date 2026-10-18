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
        if (Schema::hasTable('booking_cancellations')) {
            Schema::table('booking_cancellations', function (Blueprint $table) {
                if (!Schema::hasColumn('booking_cancellations', 'gateway_fee_reversed_amount')) {
                    $table->decimal('gateway_fee_reversed_amount', 10, 2)->default(0.00)->after('commission_reversed_amount');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('booking_cancellations')) {
            Schema::table('booking_cancellations', function (Blueprint $table) {
                if (Schema::hasColumn('booking_cancellations', 'gateway_fee_reversed_amount')) {
                    $table->dropColumn('gateway_fee_reversed_amount');
                }
            });
        }
    }
};
