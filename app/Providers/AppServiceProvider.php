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
        //
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
