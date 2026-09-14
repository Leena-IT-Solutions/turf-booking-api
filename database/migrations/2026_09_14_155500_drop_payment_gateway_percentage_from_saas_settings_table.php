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
            if (Schema::hasColumn('saas_settings', 'payment_gateway_percentage')) {
                $table->dropColumn('payment_gateway_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('saas_settings', 'payment_gateway_percentage')) {
                $table->decimal('payment_gateway_percentage', 5, 2)->default(2.00)->after('commission_percentage');
            }
        });
    }
};
