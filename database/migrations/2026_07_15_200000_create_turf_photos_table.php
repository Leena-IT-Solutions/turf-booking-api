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
        Schema::create('turf_photos', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('turf_id')->constrained('turfs')->cascadeOnDelete();
            $blueprint->string('photo');
            $blueprint->boolean('is_active')->default(true);
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('turf_photos');
    }
};
