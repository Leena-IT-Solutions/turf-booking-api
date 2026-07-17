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
        Schema::table('turfs', function (Blueprint $table) {
            $table->boolean('is_location_verified')->default(false)->after('status');
            $table->boolean('is_details_verified')->default(false)->after('is_location_verified');
            $table->boolean('is_photos_verified')->default(false)->after('is_details_verified');
            $table->boolean('is_facilities_verified')->default(false)->after('is_photos_verified');
            $table->boolean('is_equipments_verified')->default(false)->after('is_facilities_verified');
            $table->boolean('is_sports_verified')->default(false)->after('is_equipments_verified');
            $table->boolean('is_slots_verified')->default(false)->after('is_sports_verified');
            $table->boolean('is_pricing_verified')->default(false)->after('is_slots_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turfs', function (Blueprint $table) {
            $table->dropColumn([
                'is_location_verified',
                'is_details_verified',
                'is_photos_verified',
                'is_facilities_verified',
                'is_equipments_verified',
                'is_sports_verified',
                'is_slots_verified',
                'is_pricing_verified'
            ]);
        });
    }
};
