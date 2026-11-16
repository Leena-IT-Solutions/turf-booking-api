<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('turfs') && !Schema::hasColumn('turfs', 'qr_code')) {
            Schema::table('turfs', function (Blueprint $table) {
                $table->string('qr_code', 16)->nullable()->unique()->after('submitted_at');
            });
        }

        // Backfill a unique QR code for every existing turf
        if (Schema::hasTable('turfs')) {
            $used = DB::table('turfs')->whereNotNull('qr_code')->pluck('qr_code')->flip()->all();
            DB::table('turfs')->whereNull('qr_code')->orderBy('id')->each(function ($turf) use (&$used) {
                do {
                    $code = strtoupper(Str::random(8));
                    $code = preg_replace('/[^A-Z0-9]/', 'X', $code);
                } while (isset($used[$code]));
                $used[$code] = true;
                DB::table('turfs')->where('id', $turf->id)->update(['qr_code' => $code]);
            });
        }

        if (!Schema::hasTable('turf_qr_scans')) {
            Schema::create('turf_qr_scans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('turf_id')->constrained('turfs')->cascadeOnDelete();
                $table->string('platform', 20)->default('desktop'); // android, ios, desktop
                $table->string('source', 20)->default('web');        // web (landing page), app (opened in app)
                $table->string('user_agent', 500)->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->foreignId('converted_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('converted_at')->nullable();
                $table->timestamps();

                $table->index(['turf_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('turf_qr_scans');

        if (Schema::hasTable('turfs') && Schema::hasColumn('turfs', 'qr_code')) {
            Schema::table('turfs', function (Blueprint $table) {
                $table->dropUnique(['qr_code']);
                $table->dropColumn('qr_code');
            });
        }
    }
};
