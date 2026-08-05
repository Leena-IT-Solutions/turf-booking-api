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
            $table->text('whatsapp_token')->nullable()->after('gemini_api_key');
            $table->string('whatsapp_phone_number_id')->nullable()->after('whatsapp_token');
            $table->string('whatsapp_business_account_id')->nullable()->after('whatsapp_phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            $table->dropColumn([
                'whatsapp_token',
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
            ]);
        });
    }
};
