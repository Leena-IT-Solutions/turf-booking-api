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
        Schema::table('saas_settings', function (Blueprint $table) {
            $table->decimal('payment_gateway_percentage', 5, 2)->default(2.00)->after('commission_percentage');
            $table->integer('payout_hours')->default(24)->after('payment_gateway_percentage');
            $table->decimal('payout_charges', 10, 2)->default(40.00)->after('payout_hours');
            $table->string('razorpayx_account_number')->nullable()->after('payout_charges');
            $table->string('razorpayx_webhook_secret')->nullable()->after('razorpayx_account_number');
            $table->decimal('max_commission_due', 10, 2)->default(2000.00)->after('razorpayx_webhook_secret');
            $table->integer('commission_due_grace_days')->default(7)->after('max_commission_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('saas_settings', function (Blueprint $table) {
            $table->dropColumn([
                'payment_gateway_percentage',
                'payout_hours',
                'payout_charges',
                'razorpayx_account_number',
                'razorpayx_webhook_secret',
                'max_commission_due',
                'commission_due_grace_days',
            ]);
        });
    }
};
