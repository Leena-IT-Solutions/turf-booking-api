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
            if (!Schema::hasColumn('saas_settings', 'commission_percentage')) {
                $table->decimal('commission_percentage', 5, 2)->default(7.00)->after('min_slots_booking');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            if (Schema::hasColumn('saas_settings', 'commission_percentage')) {
                $table->dropColumn('commission_percentage');
            }
        });
    }
};
