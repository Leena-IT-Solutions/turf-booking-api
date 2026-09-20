<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 2-phase refund resolution columns to booking_cancellations table.
     * Phase 1 (Cancellation) happens immediately; Phase 2 (Refund Resolution)
     * is deferred for admin review.
     */
    public function up(): void
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            // Resolution mode: standard_policy, full_compensation, custom, no_refund
            $table->string('resolution_mode', 30)->nullable()->after('refund_status');
            // Disbursement: online_gateway, offline
            $table->string('disbursement_channel', 30)->nullable()->after('resolution_mode');
            // Reference for offline UPI/cash settlement
            $table->string('offline_reference', 255)->nullable()->after('disbursement_channel');
            // Who resolved the refund
            $table->foreignId('resolved_by_user_id')->nullable()->after('offline_reference')->constrained('users')->nullOnDelete();
            // When the refund was resolved
            $table->timestamp('resolved_at')->nullable()->after('resolved_by_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $table->dropForeign(['resolved_by_user_id']);
            $table->dropColumn([
                'resolution_mode',
                'disbursement_channel',
                'offline_reference',
                'resolved_by_user_id',
                'resolved_at',
            ]);
        });
    }
};
