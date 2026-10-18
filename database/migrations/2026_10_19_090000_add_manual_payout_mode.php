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
        // 1. Add payout_mode to saas_settings
        if (Schema::hasTable('saas_settings')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('saas_settings', 'payout_mode')) {
                    $table->string('payout_mode', 20)->default('manual')->after('payout_charges');
                }
            });
        }

        // 2. Add manual payout fields to turf_payouts
        if (Schema::hasTable('turf_payouts')) {
            Schema::table('turf_payouts', function (Blueprint $table) {
                if (!Schema::hasColumn('turf_payouts', 'mode')) {
                    $table->string('mode', 20)->default('razorpayx')->after('status');
                }
                if (!Schema::hasColumn('turf_payouts', 'payout_method')) {
                    $table->string('payout_method', 10)->nullable()->after('mode');
                }
                if (!Schema::hasColumn('turf_payouts', 'account_details')) {
                    $table->json('account_details')->nullable()->after('payout_method');
                }
                if (!Schema::hasColumn('turf_payouts', 'utr')) {
                    $table->string('utr', 64)->nullable()->after('razorpay_payout_id');
                }
                if (!Schema::hasColumn('turf_payouts', 'paid_at')) {
                    $table->date('paid_at')->nullable()->after('utr');
                }
                if (!Schema::hasColumn('turf_payouts', 'admin_note')) {
                    $table->text('admin_note')->nullable()->after('failure_reason');
                }
                if (!Schema::hasColumn('turf_payouts', 'processed_by_user_id')) {
                    $table->foreignId('processed_by_user_id')->nullable()->after('admin_note')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('turf_payouts')) {
            Schema::table('turf_payouts', function (Blueprint $table) {
                if (Schema::hasColumn('turf_payouts', 'processed_by_user_id')) {
                    $table->dropForeign(['processed_by_user_id']);
                    $table->dropColumn('processed_by_user_id');
                }

                $columns = [];
                foreach (['mode', 'payout_method', 'account_details', 'utr', 'paid_at', 'admin_note'] as $col) {
                    if (Schema::hasColumn('turf_payouts', $col)) {
                        $columns[] = $col;
                    }
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('saas_settings')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                if (Schema::hasColumn('saas_settings', 'payout_mode')) {
                    $table->dropColumn('payout_mode');
                }
            });
        }
    }
};
