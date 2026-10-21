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
        if (Schema::hasTable('saas_settings') && Schema::hasColumn('saas_settings', 'commission_percentage')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->decimal('commission_percentage', 5, 2)->default(8.00)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('saas_settings') && Schema::hasColumn('saas_settings', 'commission_percentage')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->decimal('commission_percentage', 5, 2)->default(7.00)->change();
            });
        }
    }
};
