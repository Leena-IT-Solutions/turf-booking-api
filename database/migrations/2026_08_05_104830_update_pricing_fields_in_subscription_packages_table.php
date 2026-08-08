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
            if (Schema::hasColumn('subscription_packages', 'days')) {
                $table->dropColumn('days');
            }
            if (Schema::hasColumn('subscription_packages', 'amount')) {
                $table->dropColumn('amount');
            }
            $table->decimal('monthly_amount', 10, 2)->default(0.00)->after('description');
            $table->decimal('yearly_amount', 10, 2)->default(0.00)->after('monthly_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            $table->dropColumn(['monthly_amount', 'yearly_amount']);
            $table->decimal('amount', 10, 2)->default(0.00);
            $table->integer('days')->default(30);
        });
    }
};
