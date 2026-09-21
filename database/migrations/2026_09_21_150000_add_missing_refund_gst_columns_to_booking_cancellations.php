<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * On at least one environment, the 11-column ALTER TABLE in
 * 2026_09_21_090000_add_gst_breakup_columns_to_booking_cancellations silently applied
 * its first 7 chained "ADD COLUMN ... AFTER <sibling column added earlier in the same
 * statement>" clauses but not the last 4 (the refund_* columns) -- with no error, so the
 * migration was recorded as run despite the table missing those columns.
 *
 * This migration is a safety-net: it adds only whichever of the 4 refund GST columns are
 * still missing, each in its OWN Schema::table() call (one ALTER TABLE statement per
 * column) instead of one chained statement, to avoid any repeat of that ordering issue.
 * Idempotent -- safe to run on environments where the columns already exist correctly.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('booking_cancellations', 'refund_taxable_amount')) {
            Schema::table('booking_cancellations', function (Blueprint $table) {
                $table->decimal('refund_taxable_amount', 10, 2)->default(0.00)->after('refund_amount');
            });
        }
        if (!Schema::hasColumn('booking_cancellations', 'refund_gst_amount')) {
            Schema::table('booking_cancellations', function (Blueprint $table) {
                $table->decimal('refund_gst_amount', 10, 2)->default(0.00)->after('refund_taxable_amount');
            });
        }
        if (!Schema::hasColumn('booking_cancellations', 'refund_cgst_amount')) {
            Schema::table('booking_cancellations', function (Blueprint $table) {
                $table->decimal('refund_cgst_amount', 10, 2)->default(0.00)->after('refund_gst_amount');
            });
        }
        if (!Schema::hasColumn('booking_cancellations', 'refund_sgst_amount')) {
            Schema::table('booking_cancellations', function (Blueprint $table) {
                $table->decimal('refund_sgst_amount', 10, 2)->default(0.00)->after('refund_cgst_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('booking_cancellations', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['refund_taxable_amount', 'refund_gst_amount', 'refund_cgst_amount', 'refund_sgst_amount'] as $col) {
                if (Schema::hasColumn('booking_cancellations', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
