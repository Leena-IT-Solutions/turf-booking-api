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
            if (!Schema::hasColumn('bookings', 'turf_invoice_number')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->string('turf_invoice_number')->nullable()->after('booking_number');
                });
            }

            if (!Schema::hasColumn('bookings', 'saas_invoice_number')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->string('saas_invoice_number')->nullable()->after('turf_invoice_number');
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
            if (Schema::hasColumn('bookings', 'saas_invoice_number')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropColumn('saas_invoice_number');
                });
            }

            if (Schema::hasColumn('bookings', 'turf_invoice_number')) {
                Schema::table('bookings', function (Blueprint $table) {
                    $table->dropColumn('turf_invoice_number');
                });
            }
        }
    }
};
