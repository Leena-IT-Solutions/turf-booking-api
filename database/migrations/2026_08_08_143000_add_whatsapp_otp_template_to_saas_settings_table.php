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
            if (!Schema::hasColumn('saas_settings', 'whatsapp_otp_template')) {
                $table->string('whatsapp_otp_template')->nullable()->default('turf_otp')->after('whatsapp_business_account_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            if (Schema::hasColumn('saas_settings', 'whatsapp_otp_template')) {
                $table->dropColumn('whatsapp_otp_template');
            }
        });
    }
};
