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
                if ($setting && $setting->app_name) {
                    config(['app.name' => $setting->app_name]);
                }
            } catch (\Exception $e) {
                // Ignore errors if database/table doesn't exist yet
            }
        }
    }
}
