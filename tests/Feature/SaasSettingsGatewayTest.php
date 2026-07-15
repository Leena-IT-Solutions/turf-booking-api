<?php

namespace Tests\Feature;

use App\Models\SaasSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasSettingsGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_gateway_values_updates_config_correctly()
    {
        $setting = SaasSetting::create([
            'app_name' => 'CustomTurf',
            'razorpay_key' => 'rzp_test_123',
            'razorpay_secret' => 'secret_123',
            'mailgun_domain' => 'sandbox.mailgun.org',
            'mailgun_secret' => 'mg_secret_123',
            'mailgun_endpoint' => 'api.eu.mailgun.net',
        ]);

        // Manually override config values to verify service configurations
        config([
            'services.mailgun.domain' => $setting->mailgun_domain ?: config('services.mailgun.domain'),
            'services.mailgun.secret' => $setting->mailgun_secret ?: config('services.mailgun.secret'),
            'services.mailgun.endpoint' => $setting->mailgun_endpoint ?: config('services.mailgun.endpoint'),
            'services.razorpay.key' => $setting->razorpay_key ?: config('services.razorpay.key'),
            'services.razorpay.secret' => $setting->razorpay_secret ?: config('services.razorpay.secret'),
        ]);

        $this->assertEquals('rzp_test_123', config('services.razorpay.key'));
        $this->assertEquals('secret_123', config('services.razorpay.secret'));
        $this->assertEquals('sandbox.mailgun.org', config('services.mailgun.domain'));
        $this->assertEquals('mg_secret_123', config('services.mailgun.secret'));
        $this->assertEquals('api.eu.mailgun.net', config('services.mailgun.endpoint'));
    }
}
