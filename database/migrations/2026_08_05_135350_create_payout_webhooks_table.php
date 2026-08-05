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
        Schema::create('payout_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event_type')->nullable();
            $table->json('payload')->nullable();
            $table->boolean('processed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_webhooks');
    }
};
