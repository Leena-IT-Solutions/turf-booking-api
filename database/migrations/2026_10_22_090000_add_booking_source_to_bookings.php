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
        if (Schema::hasTable('bookings')) {
            if (!Schema::hasColumn('bookings', 'booking_source')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->string('booking_source', 10)->nullable()->after('booking_type');
                });
            }

            if (!Schema::hasColumn('bookings', 'booked_by_user_id')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->foreignId('booked_by_user_id')
                        ->nullable()
                        ->after('booking_source')
                        ->constrained('users')
                        ->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('bookings')) {
            if (Schema::hasColumn('bookings', 'booked_by_user_id')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropForeign(['booked_by_user_id']);
                    $table->dropColumn('booked_by_user_id');
                });
            }

            if (Schema::hasColumn('bookings', 'booking_source')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropColumn('booking_source');
                });
            }
        }
    }
};
