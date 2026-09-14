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
        Schema::table('saas_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('saas_settings', 'cancellation_fee_percentage')) {
                $table->decimal('cancellation_fee_percentage', 5, 2)->default(5.00)->after('payment_gateway_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            if (Schema::hasColumn('saas_settings', 'cancellation_fee_percentage')) {
                $table->dropColumn('cancellation_fee_percentage');
            }
        });
    }
};
