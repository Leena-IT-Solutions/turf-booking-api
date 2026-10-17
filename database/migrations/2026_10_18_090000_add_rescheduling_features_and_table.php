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
        // 1. Add reschedule settings to turfs
        if (Schema::hasTable('turfs')) {
            Schema::table('turfs', function (Blueprint $table) {
                if (!Schema::hasColumn('turfs', 'is_reschedule_active')) {
                    $table->boolean('is_reschedule_active')->default(true)->after('is_cancellation_active');
                }
                if (!Schema::hasColumn('turfs', 'max_reschedules')) {
                    $table->unsignedTinyInteger('max_reschedules')->default(1)->after('is_reschedule_active');
                }
            });
        }

        // 2. Add notification toggle to saas_settings
        if (Schema::hasTable('saas_settings')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                if (!Schema::hasColumn('saas_settings', 'notify_booking_rescheduled')) {
                    $table->boolean('notify_booking_rescheduled')->default(true)->after('notify_payment_received');
                }
            });
        }

        // 3. Create booking_reschedules table
        if (!Schema::hasTable('booking_reschedules')) {
            Schema::create('booking_reschedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('booking_date_id')->constrained('booking_dates')->cascadeOnDelete();
                $table->date('from_date');
                $table->date('to_date');
                $table->json('from_slot_ids');
                $table->json('to_slot_ids');
                $table->decimal('old_amount', 10, 2);
                $table->decimal('new_amount', 10, 2);
                $table->decimal('price_difference', 10, 2);
                $table->string('settlement_type', 30)->default('none'); // none, balance_due, refund
                $table->decimal('refund_amount', 10, 2)->default(0.00);
                $table->string('refund_channel', 30)->nullable(); // online_gateway, offline
                $table->string('razorpay_refund_id')->nullable();
                $table->text('reason')->nullable();
                $table->foreignId('rescheduled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_reschedules');

        if (Schema::hasTable('saas_settings')) {
            Schema::table('saas_settings', function (Blueprint $table) {
                if (Schema::hasColumn('saas_settings', 'notify_booking_rescheduled')) {
                    $table->dropColumn('notify_booking_rescheduled');
                }
            });
        }

        if (Schema::hasTable('turfs')) {
            Schema::table('turfs', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('turfs', 'max_reschedules')) {
                    $cols[] = 'max_reschedules';
                }
                if (Schema::hasColumn('turfs', 'is_reschedule_active')) {
                    $cols[] = 'is_reschedule_active';
                }
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
