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
            $table->string('subscription_gst_sac', 20)->default('998314')->after('udyam_registration_number');
            $table->decimal('subscription_gst_percentage', 5, 2)->default(18.00)->after('subscription_gst_sac');
            $table->string('commission_gst_sac', 20)->default('998599')->after('subscription_gst_percentage');
            $table->decimal('commission_gst_percentage', 5, 2)->default(18.00)->after('commission_gst_sac');
            $table->string('booking_gst_sac', 20)->default('999652')->after('commission_gst_percentage');
            $table->decimal('booking_gst_percentage', 5, 2)->default(18.00)->after('booking_gst_sac');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_gst_sac',
                'subscription_gst_percentage',
                'commission_gst_sac',
                'commission_gst_percentage',
                'booking_gst_sac',
                'booking_gst_percentage',
            ]);
        });
    }
};
