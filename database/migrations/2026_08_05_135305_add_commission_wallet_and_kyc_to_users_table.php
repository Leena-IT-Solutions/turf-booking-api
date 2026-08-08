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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('commission_wallet_balance', 12, 2)->default(0.00)->after('remember_token');
            $table->timestamp('commission_due_since')->nullable()->after('commission_wallet_balance');
            $table->string('payout_method')->nullable()->after('commission_due_since'); // 'bank' or 'upi'
            $table->string('bank_account_name')->nullable()->after('payout_method');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_ifsc')->nullable()->after('bank_account_number');
            $table->string('upi_id')->nullable()->after('bank_ifsc');
            $table->string('razorpay_contact_id')->nullable()->after('upi_id');
            $table->string('razorpay_fund_account_id')->nullable()->after('razorpay_contact_id');
            $table->string('payout_schedule')->default('manual')->after('razorpay_fund_account_id'); // 'manual', 'daily', 'weekly'
            $table->unsignedTinyInteger('payout_schedule_day')->nullable()->after('payout_schedule'); // 0 = Sunday, ..., 6 = Saturday
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'commission_wallet_balance',
                'commission_due_since',
                'payout_method',
                'bank_account_name',
                'bank_account_number',
                'bank_ifsc',
                'upi_id',
                'razorpay_contact_id',
                'razorpay_fund_account_id',
                'payout_schedule',
                'payout_schedule_day',
            ]);
        });
    }
};
