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
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('status');
            $table->decimal('cancellation_fee_applied', 10, 2)->default(0.00)->after('cancelled_at');
            $table->decimal('refund_amount', 10, 2)->default(0.00)->after('cancellation_fee_applied');
            $table->string('refund_status')->default('None')->after('refund_amount'); // None, Pending, Refunded, Failed, Not Applicable
            $table->timestamp('refunded_at')->nullable()->after('refund_status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 10, 2)->default(0.00)->after('status');
            $table->string('refund_status')->default('None')->after('refunded_amount'); // None, Partial, Refunded, Failed
            $table->timestamp('refunded_at')->nullable()->after('refund_status');
        });

        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->string('gateway_refund_id')->nullable()->after('gateway_signature');
            $table->json('refund_response_payload')->nullable()->after('gateway_refund_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table) {
            $table->dropColumn(['gateway_refund_id', 'refund_response_payload']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['refunded_amount', 'refund_status', 'refunded_at']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_fee_applied', 'refund_amount', 'refund_status', 'refunded_at']);
        });
    }
};
