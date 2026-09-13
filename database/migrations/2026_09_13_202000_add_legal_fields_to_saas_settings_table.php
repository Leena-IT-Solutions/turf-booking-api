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
            $table->string('company_name')->nullable()->after('address');
            $table->string('company_email')->nullable()->after('company_name');
            $table->string('company_phone')->nullable()->after('company_email');
            $table->text('company_address')->nullable()->after('company_phone');
            $table->string('pincode', 20)->nullable()->after('company_address');
            $table->string('city', 100)->nullable()->after('pincode');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('country', 100)->default('India')->after('state');
            $table->string('gst_number', 50)->nullable()->after('country');
            $table->string('udyam_registration_number', 100)->nullable()->after('gst_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            $table->dropColumn([
                'company_name',
                'company_email',
                'company_phone',
                'company_address',
                'pincode',
                'city',
                'state',
                'country',
                'gst_number',
                'udyam_registration_number',
            ]);
        });
    }
};
