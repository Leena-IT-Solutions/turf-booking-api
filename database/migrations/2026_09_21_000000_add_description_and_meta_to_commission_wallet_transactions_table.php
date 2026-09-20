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
        Schema::table('commission_wallet_transactions', function (Blueprint $table) {
            $table->string('description')->nullable()->after('type');
            $table->json('meta')->nullable()->after('balance_after');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_wallet_transactions', function (Blueprint $table) {
            $table->dropColumn(['description', 'meta']);
        });
    }
};
