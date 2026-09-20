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
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $table->decimal('platform_fee_retained', 10, 2)->default(0.00)->after('turf_cancellation_fee');
            $table->decimal('saas_cancellation_fee', 10, 2)->default(0.00)->after('platform_fee_retained');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $table->dropColumn(['platform_fee_retained', 'saas_cancellation_fee']);
        });
    }
};
