<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Pail\PailServiceProvider::class)) {
            $this->app->register(\Laravel\Pail\PailServiceProvider::class);
        }

        $this->app->singleton(\Kreait\Firebase\Contract\Messaging::class, function () {
            try {
                $setting = \App\Models\SaasSetting::first();
                $json = $setting?->fcm_service_account_json;
                if (!empty($json)) {
                    $credentials = json_decode($json, true);
                    if (is_array($credentials) && isset($credentials['client_email'], $credentials['private_key'], $credentials['project_id'])) {
                        return (new \Kreait\Firebase\Factory)
                            ->withServiceAccount($credentials)
                            ->createMessaging();
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Firebase Messaging initialization failed: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!app()->runningInConsole()) {
            try {
                $setting = \App\Models\SaasSetting::first();
                if ($setting) {
                    if ($setting->app_name) {
                        config(['app.name' => $setting->app_name]);
                    }
                    config([
                        'services.mailgun.domain' => $setting->mailgun_domain ?: config('services.mailgun.domain'),
                        'services.mailgun.secret' => $setting->mailgun_secret ?: config('services.mailgun.secret'),
                        'services.mailgun.endpoint' => $setting->mailgun_endpoint ?: config('services.mailgun.endpoint'),
                        'services.razorpay.key' => $setting->razorpay_key ?: config('services.razorpay.key'),
                        'services.razorpay.secret' => $setting->razorpay_secret ?: config('services.razorpay.secret'),
                    ]);
                }
            } catch (\Exception $e) {
                // Ignore errors if database/table doesn't exist yet
            }
        }
    }
}
