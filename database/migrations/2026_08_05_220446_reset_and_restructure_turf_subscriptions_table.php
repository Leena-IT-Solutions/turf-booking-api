<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('turf_subscriptions')->truncate();
        DB::table('subscription_payments')->truncate();
        Schema::enableForeignKeyConstraints();

        Schema::table('subscription_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_payments', 'turf_ids')) {
                $table->json('turf_ids')->after('user_id');
            }
            if (!Schema::hasColumn('subscription_payments', 'turf_count')) {
                $table->unsignedInteger('turf_count')->default(1)->after('turf_ids');
            }
        });

        Schema::table('turf_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('turf_subscriptions', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            if (!Schema::hasColumn('turf_subscriptions', 'turf_id')) {
                $table->foreignId('turf_id')->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('turf_subscriptions', 'subscription_payment_id')) {
                $table->foreignId('subscription_payment_id')->after('subscription_package_id')->constrained()->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('turf_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('turf_subscriptions', 'subscription_payment_id')) {
                $table->dropForeign(['subscription_payment_id']);
                $table->dropColumn('subscription_payment_id');
            }
            if (Schema::hasColumn('turf_subscriptions', 'turf_id')) {
                $table->dropForeign(['turf_id']);
                $table->dropColumn('turf_id');
            }
            if (!Schema::hasColumn('turf_subscriptions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_payments', 'turf_count')) {
                $table->dropColumn('turf_count');
            }
            if (Schema::hasColumn('subscription_payments', 'turf_ids')) {
                $table->dropColumn('turf_ids');
            }
        });
    }
};
