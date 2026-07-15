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
        Schema::table('slot_turf', function (Blueprint $table) {
            $table->decimal('mon', 10, 2)->nullable()->after('is_active');
            $table->decimal('tue', 10, 2)->nullable()->after('mon');
            $table->decimal('wed', 10, 2)->nullable()->after('tue');
            $table->decimal('thu', 10, 2)->nullable()->after('wed');
            $table->decimal('fri', 10, 2)->nullable()->after('thu');
            $table->decimal('sat', 10, 2)->nullable()->after('fri');
            $table->decimal('sun', 10, 2)->nullable()->after('sat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('slot_turf', function (Blueprint $table) {
            $table->dropColumn(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']);
        });
    }
};
