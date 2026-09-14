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
        Schema::table('turf_settings', function (Blueprint $table) {
            $table->boolean('is_gst_billing_active')->default(false)->after('gst_number');
            $table->string('gst_pricing_type', 20)->default('included')->after('is_gst_billing_active'); // 'included' or 'excluded'
            $table->decimal('gst_percentage', 5, 2)->default(18.00)->after('gst_pricing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turf_settings', function (Blueprint $table) {
            $table->dropColumn([
                'is_gst_billing_active',
                'gst_pricing_type',
                'gst_percentage',
            ]);
        });
    }
};
