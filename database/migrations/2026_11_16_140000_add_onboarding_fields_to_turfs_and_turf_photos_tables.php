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
        if (Schema::hasTable('turf_photos') && !Schema::hasColumn('turf_photos', 'sort_order')) {
            Schema::table('turf_photos', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            });
        }

        if (Schema::hasTable('turfs')) {
            Schema::table('turfs', function (Blueprint $table) {
                if (!Schema::hasColumn('turfs', 'onboarded_by')) {
                    $table->foreignId('onboarded_by')
                        ->nullable()
                        ->after('status')
                        ->constrained('users')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('turfs', 'submitted_at')) {
                    $table->timestamp('submitted_at')->nullable()->after('onboarded_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('turfs')) {
            Schema::table('turfs', function (Blueprint $table) {
                if (Schema::hasColumn('turfs', 'onboarded_by')) {
                    $table->dropConstrainedForeignId('onboarded_by');
                }
                if (Schema::hasColumn('turfs', 'submitted_at')) {
                    $table->dropColumn('submitted_at');
                }
            });
        }

        if (Schema::hasTable('turf_photos') && Schema::hasColumn('turf_photos', 'sort_order')) {
            Schema::table('turf_photos', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }
    }
};
