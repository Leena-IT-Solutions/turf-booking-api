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
        Schema::create('saas_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('TurfBooking');
            $table->string('contact_email')->default('sandeep198558@gmail.com');
            $table->string('contact_mobile')->default('9664588677');
            $table->text('address')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('is_maintenance_mode')->default(false);
            $table->string('gemini_api_key')->nullable();
            $table->string('razorpay_key')->nullable();
            $table->string('razorpay_secret')->nullable();
            $table->string('mailgun_domain')->nullable();
            $table->string('mailgun_secret')->nullable();
            $table->string('mailgun_endpoint')->default('api.mailgun.net');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saas_settings');
    }
};
