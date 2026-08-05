<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');
Route::view('/features', 'features')->name('features');
Route::view('/pricing', 'pricing')->name('pricing');
Route::view('/contact', 'contact')->name('contact');
Route::view('/privacy-policy', 'privacy-policy')->name('privacy-policy');

Route::get('dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Volt::route('saas/sliders', 'saas.slider-manager')
        ->middleware('role:saas-admin')
        ->name('saas.sliders');
    Volt::route('saas/users', 'saas.user-manager')
        ->middleware('role:saas-admin')
        ->name('saas.users');
    Volt::route('saas/slot-categories', 'saas.slot-category-manager')
        ->middleware('role:saas-admin')
        ->name('saas.slot-categories');
    Volt::route('saas/slots', 'saas.slot-manager')
        ->middleware('role:saas-admin')
        ->name('saas.slots');
    Volt::route('saas/facilities', 'saas.facilities-manager')
        ->middleware('role:saas-admin')
        ->name('saas.facilities');
    Volt::route('saas/equipments', 'saas.equipments-manager')
        ->middleware('role:saas-admin')
        ->name('saas.equipments');
    Volt::route('saas/sports', 'saas.sports-manager')
        ->middleware('role:saas-admin')
        ->name('saas.sports');
    Volt::route('saas/settings', 'saas.settings-manager')
        ->middleware('role:saas-admin')
        ->name('saas.settings');
    Volt::route('saas/support', 'saas.chat-manager')
        ->middleware('role:saas-admin')
        ->name('saas.support');
    Volt::route('saas/turf-verification', 'saas.turf-verification')
        ->middleware('role:saas-admin')
        ->name('saas.turf-verification');
    Volt::route('saas/subscription-packages', 'saas.subscription-package-manager')
        ->middleware('role:saas-admin')
        ->name('saas.subscription-packages');
    Volt::route('saas/administrator', 'saas.administrator-dashboard')
        ->middleware('role:saas-admin')
        ->name('saas.administrator');

    Volt::route('turf/dashboard', 'turf.dashboard-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.dashboard');
    Volt::route('turf/bookings', 'turf.booking-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.bookings');
    Volt::route('turf/settings', 'turf.settings-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.settings');
    Volt::route('turf/locations', 'turf.location-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.locations');
    Volt::route('turf/turfs', 'turf.turf-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.turfs');
    Volt::route('turf/photos', 'turf.photos-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.photos');
    Volt::route('turf/facilities', 'turf.facilities-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.facilities');
    Volt::route('turf/equipments', 'turf.equipments-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.equipments');
    Volt::route('turf/sports', 'turf.sports-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.sports');
    Volt::route('turf/slots', 'turf.slots-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.slots');
    Volt::route('turf/slot-locks', 'turf.slot-lock-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.slot-locks');
    Volt::route('turf/pricing', 'turf.pricing-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.pricing');
    Volt::route('turf/coupons', 'turf.coupons-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.coupons');
    Volt::route('turf/subscription', 'turf.subscription-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.subscription');
    Volt::route('turf/staff', 'turf.staff-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.staff');
    Volt::route('turf/reports', 'turf.reports-manager')
        ->middleware('role:turf-admin|manager|admin')
        ->name('turf.reports');
    Route::get('/turf/reports/export-bookings', [\App\Http\Controllers\ReportController::class, 'exportBookings'])
        ->middleware('role:turf-admin|manager|admin')
        ->name('reports.export-bookings');
    Route::get('/turf/reports/export-revenue', [\App\Http\Controllers\ReportController::class, 'exportRevenue'])
        ->middleware('role:turf-admin|manager|admin')
        ->name('reports.export-revenue');
});

require __DIR__.'/auth.php';
