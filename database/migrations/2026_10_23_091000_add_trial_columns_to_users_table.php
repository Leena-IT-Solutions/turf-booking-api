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
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'trial_started_at')) {
                    $table->timestamp('trial_started_at')->nullable()->after('remember_token');
                }
                if (!Schema::hasColumn('users', 'trial_ends_at')) {
                    $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
                }
                if (!Schema::hasColumn('users', 'trial_reminder_3d_sent_at')) {
                    $table->timestamp('trial_reminder_3d_sent_at')->nullable()->after('trial_ends_at');
                }
                if (!Schema::hasColumn('users', 'trial_reminder_0d_sent_at')) {
                    $table->timestamp('trial_reminder_0d_sent_at')->nullable()->after('trial_reminder_3d_sent_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('users', 'trial_reminder_0d_sent_at')) {
                    $columns[] = 'trial_reminder_0d_sent_at';
                }
                if (Schema::hasColumn('users', 'trial_reminder_3d_sent_at')) {
                    $columns[] = 'trial_reminder_3d_sent_at';
                }
                if (Schema::hasColumn('users', 'trial_ends_at')) {
                    $columns[] = 'trial_ends_at';
                }
                if (Schema::hasColumn('users', 'trial_started_at')) {
                    $columns[] = 'trial_started_at';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
