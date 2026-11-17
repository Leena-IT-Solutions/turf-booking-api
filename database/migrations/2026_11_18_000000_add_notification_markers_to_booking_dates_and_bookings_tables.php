<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('booking_dates', 'reminder_sent_at')) {
            Schema::table('booking_dates', function (Blueprint $table) {
                $table->timestamp('reminder_sent_at')->nullable()->after('refunded_at');
            });
        }

        if (! Schema::hasColumn('bookings', 'review_prompt_sent_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('review_prompt_sent_at')->nullable()->after('refunded_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('booking_dates', 'reminder_sent_at')) {
            Schema::table('booking_dates', function (Blueprint $table) {
                $table->dropColumn('reminder_sent_at');
            });
        }

        if (Schema::hasColumn('bookings', 'review_prompt_sent_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('review_prompt_sent_at');
            });
        }
    }
};
