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
            
            // Company & Legal Details
            $table->string('company_name')->nullable();
            $table->string('company_email')->nullable();
            $table->string('company_phone')->nullable();
            $table->text('company_address')->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('state_code', 10)->nullable();
            $table->string('country', 100)->default('India');
            $table->string('gst_number', 50)->nullable();
            $table->boolean('is_gst_billing_active')->default(false);
            $table->string('udyam_registration_number', 100)->nullable();

            // GST SAC & Rates
            $table->string('subscription_gst_sac', 20)->default('998314');
            $table->decimal('subscription_gst_percentage', 5, 2)->default(18.00);
            $table->string('commission_gst_sac', 20)->default('998599');
            $table->decimal('commission_gst_percentage', 5, 2)->default(18.00);
            $table->string('booking_gst_sac', 20)->default('999652');
            $table->decimal('booking_gst_percentage', 5, 2)->default(18.00);

            $table->string('logo_path')->nullable();
            $table->boolean('is_maintenance_mode')->default(false);
            $table->string('gemini_api_key')->nullable();
            
            // WhatsApp Integration
            $table->text('whatsapp_token')->nullable();
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->string('whatsapp_otp_template')->nullable()->default('turf_otp');

            // Gateways & Mail
            $table->string('razorpay_key')->nullable();
            $table->string('razorpay_secret')->nullable();
            $table->string('mailgun_domain')->nullable();
            $table->string('mailgun_secret')->nullable();
            $table->string('mailgun_endpoint')->default('api.mailgun.net');

            // Search & Bookings
            $table->integer('turf_search_km')->default(10);
            $table->string('google_maps_api_key')->nullable();
            $table->integer('min_slots_booking')->default(2);
            $table->unsignedInteger('free_trial_days')->default(30);

            // Commission, Platform Fees & Cancellations
            $table->decimal('commission_percentage', 5, 2)->default(7.00);
            $table->decimal('platform_fee', 10, 2)->default(0.00);
            $table->decimal('cancellation_fee_percentage', 5, 2)->default(5.00);
            $table->integer('payout_hours')->default(24);
            $table->decimal('payout_charges', 10, 2)->default(40.00);
            $table->string('razorpayx_account_number')->nullable();
            $table->string('razorpayx_webhook_secret')->nullable();
            $table->decimal('max_commission_due', 10, 2)->default(2000.00);
            $table->integer('commission_due_grace_days')->default(7);

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
