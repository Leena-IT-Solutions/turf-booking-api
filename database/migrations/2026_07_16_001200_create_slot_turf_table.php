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
        Schema::create('slot_turf', function (Blueprint $table) {
            $table->id();
            $table->foreignId('slot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turf_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->decimal('mon', 10, 2)->nullable();
            $table->decimal('tue', 10, 2)->nullable();
            $table->decimal('wed', 10, 2)->nullable();
            $table->decimal('thu', 10, 2)->nullable();
            $table->decimal('fri', 10, 2)->nullable();
            $table->decimal('sat', 10, 2)->nullable();
            $table->decimal('sun', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['slot_id', 'turf_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_turf');
    }
};
