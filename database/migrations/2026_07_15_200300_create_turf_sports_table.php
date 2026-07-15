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
        Schema::create('turf_sports', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('turf_id')->constrained('turfs')->cascadeOnDelete();
            $blueprint->foreignId('sport_id')->constrained('sports')->cascadeOnDelete();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turf_sports');
    }
};
