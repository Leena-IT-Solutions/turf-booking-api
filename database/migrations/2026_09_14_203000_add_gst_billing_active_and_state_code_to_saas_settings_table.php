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
            $table->string('state_code', 10)->nullable()->after('state');
            $table->boolean('is_gst_billing_active')->default(false)->after('gst_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            $table->dropColumn(['state_code', 'is_gst_billing_active']);
        });
    }
};
