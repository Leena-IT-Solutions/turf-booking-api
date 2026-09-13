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
        Schema::table('subscription_packages', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_packages', 'commission_percentage')) {
                $table->dropColumn('commission_percentage');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_packages', 'commission_percentage')) {
                $table->decimal('commission_percentage', 5, 2)->default(0.00)->after('yearly_amount');
            }
        });
    }
};
