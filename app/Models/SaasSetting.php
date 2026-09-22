<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaasSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'app_name',
        'contact_email',
        'contact_mobile',
        'address',
        'company_name',
        'company_email',
        'company_phone',
        'company_address',
        'pincode',
        'city',
        'state',
        'state_code',
        'country',
        'gst_number',
        'is_gst_billing_active',
        'udyam_registration_number',
        'subscription_gst_sac',
        'subscription_gst_percentage',
        'commission_gst_sac',
        'commission_gst_percentage',
        'booking_gst_sac',
        'booking_gst_percentage',
        'logo_path',
        'is_maintenance_mode',
        'gemini_api_key',
        'whatsapp_token',
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_otp_template',
        'google_maps_api_key',
        'razorpay_key',
        'razorpay_secret',
        'mailgun_domain',
        'mailgun_secret',
        'mailgun_endpoint',
        'turf_search_km',
        'min_slots_booking',
        'free_trial_days',
        'commission_percentage',
        'platform_fee',
        'cancellation_fee_percentage',
        'payout_hours',
        'payout_charges',
        'razorpayx_account_number',
        'razorpayx_webhook_secret',
        'max_commission_due',
        'commission_due_grace_days',
        'notify_booking_created',
        'notify_booking_cancelled',
        'notify_payment_received',
        'fcm_project_id',
        'fcm_service_account_json',
    ];

    protected $casts = [
        'is_maintenance_mode' => 'boolean',
        'is_gst_billing_active' => 'boolean',
        'notify_booking_created' => 'boolean',
        'notify_booking_cancelled' => 'boolean',
        'notify_payment_received' => 'boolean',
        'turf_search_km' => 'integer',
        'min_slots_booking' => 'integer',
        'free_trial_days' => 'integer',
        'commission_percentage' => 'float',
        'platform_fee' => 'float',
        'cancellation_fee_percentage' => 'float',
        'payout_hours' => 'integer',
        'payout_charges' => 'float',
        'max_commission_due' => 'float',
        'commission_due_grace_days' => 'integer',
        'subscription_gst_percentage' => 'float',
        'commission_gst_percentage' => 'float',
        'booking_gst_percentage' => 'float',
    ];

}
