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
            $table->json('pricing_wizard_data')->nullable()->after('equipments');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turfs', function (Blueprint $table) {
            $table->dropColumn('pricing_wizard_data');
        });
    }
};
