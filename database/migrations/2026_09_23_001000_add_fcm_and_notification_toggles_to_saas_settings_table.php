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
        if (!Schema::hasColumn('saas_settings', 'notify_booking_created')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->boolean('notify_booking_created')->default(true);
            });
        }

        if (!Schema::hasColumn('saas_settings', 'notify_booking_cancelled')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->boolean('notify_booking_cancelled')->default(true);
            });
        }

        if (!Schema::hasColumn('saas_settings', 'notify_payment_received')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->boolean('notify_payment_received')->default(true);
            });
        }

        if (!Schema::hasColumn('saas_settings', 'fcm_project_id')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->string('fcm_project_id')->nullable();
            });
        }

        if (!Schema::hasColumn('saas_settings', 'fcm_service_account_json')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                $table->longText('fcm_service_account_json')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            $columns = [
                'notify_booking_created',
                'notify_booking_cancelled',
                'notify_payment_received',
                'fcm_project_id',
                'fcm_service_account_json',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('saas_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
