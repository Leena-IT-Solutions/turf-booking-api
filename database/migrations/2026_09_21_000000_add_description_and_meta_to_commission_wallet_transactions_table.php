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
            if (!Schema::hasColumn('commission_wallet_transactions', 'description')) {
                $table->string('description')->nullable()->after('type');
            }
            if (!Schema::hasColumn('commission_wallet_transactions', 'meta')) {
                $table->json('meta')->nullable()->after('balance_after');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commission_wallet_transactions', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('commission_wallet_transactions', 'description')) {
                $columnsToDrop[] = 'description';
            }
            if (Schema::hasColumn('commission_wallet_transactions', 'meta')) {
                $columnsToDrop[] = 'meta';
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
