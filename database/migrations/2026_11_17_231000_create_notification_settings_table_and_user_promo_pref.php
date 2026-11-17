<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 100)->unique();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('email_enabled')->nullable();
            $table->json('config')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (! Schema::hasColumn('users', 'promo_notifications_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('promo_notifications_enabled')->default(true)->after('remember_token');
            });
        }

        // Seed from existing saas_settings row if present so current behaviour is preserved
        if (Schema::hasTable('saas_settings')) {
            $saas = DB::table('saas_settings')->first();
            if ($saas) {
                $now = now();
                $legacyMap = [
                    'customer.booking_created' => (bool) ($saas->notify_booking_created ?? true),
                    'venue.booking_created' => (bool) ($saas->notify_booking_created ?? true),
                    'customer.booking_cancelled' => (bool) ($saas->notify_booking_cancelled ?? true),
                    'venue.booking_cancelled' => (bool) ($saas->notify_booking_cancelled ?? true),
                    'customer.booking_rescheduled' => (bool) ($saas->notify_booking_rescheduled ?? true),
                    'venue.booking_rescheduled' => (bool) ($saas->notify_booking_rescheduled ?? true),
                    'customer.payment_received' => (bool) ($saas->notify_payment_received ?? true),
                ];

                foreach ($legacyMap as $eventKey => $enabled) {
                    DB::table('notification_settings')->updateOrInsert(
                        ['event_key' => $eventKey],
                        [
                            'push_enabled' => $enabled,
                            'email_enabled' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');

        if (Schema::hasColumn('users', 'promo_notifications_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('promo_notifications_enabled');
            });
        }
    }
};
