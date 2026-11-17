<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingInvoiceController;
use App\Http\Controllers\Api\BookingRescheduleController;
use App\Http\Controllers\Api\RazorpayPayoutWebhookController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SliderImageController;
use App\Http\Controllers\Api\SupportController;
use App\Http\Controllers\Api\TurfController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Models\SaasSetting;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::match(['get', 'post'], '/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);
Route::post('/razorpay/payout-webhook', [RazorpayPayoutWebhookController::class, 'handle']);

Route::post('/send-whatsapp-otp', [AuthController::class, 'sendWhatsAppOtp'])->middleware('throttle:5,1');
Route::post('/verify-whatsapp-otp', [AuthController::class, 'verifyWhatsAppOtp']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/slider-images', [SliderImageController::class, 'index']);
Route::get('/turfs', [TurfController::class, 'index']);
Route::get('/turfs/by-code/{code}', [TurfController::class, 'showByCode']);
Route::get('/turfs/{turf}', [TurfController::class, 'show']);
Route::get('/turfs/{turf}/reviews', [ReviewController::class, 'index']);
Route::get('/turfs/{turf}/slots', [BookingController::class, 'getSlots']);
Route::get('/turfs/{turf}/coupons', [BookingController::class, 'getCoupons']);
Route::get('/config', function () {
    $setting = SaasSetting::first();

    return response()->json([
        'app_name' => $setting?->app_name ?? 'TurfBooking',
        'google_maps_api_key' => $setting?->google_maps_api_key,
        'razorpay_key' => $setting?->razorpay_key,
        'turf_search_km' => $setting?->turf_search_km ?? 10,
        'min_slots_booking' => $setting?->min_slots_booking ?? 2,
        'policies' => [
            'terms' => url('/terms-and-conditions'),
            'privacy' => url('/privacy-policy'),
            'cancellation' => url('/refund-and-cancellation-policy'),
            'return' => url('/return-policy'),
            'shipping' => url('/shipping-policy'),
        ],
    ]);
});

Route::get('/policies', function () {
    return response()->json([
        'company' => 'LEENA IT SOLUTIONS',
        'address' => 'Plot No 65, Shree Satyam CHS B101, Sai Section, Ambernath East 421501 MS India',
        'email' => 'leenaadam28@gmail.com',
        'phone' => '+91 9769409405',
        'policies' => [
            [
                'id' => 'terms',
                'title' => 'Terms & Conditions',
                'url' => url('/terms-and-conditions'),
            ],
            [
                'id' => 'privacy',
                'title' => 'Privacy Policy',
                'url' => url('/privacy-policy'),
            ],
            [
                'id' => 'cancellation',
                'title' => 'Refund & Cancellation Policy',
                'url' => url('/refund-and-cancellation-policy'),
            ],
            [
                'id' => 'return',
                'title' => 'Return Policy',
                'url' => url('/return-policy'),
            ],
            [
                'id' => 'shipping',
                'title' => 'Shipping Policy',
                'url' => url('/shipping-policy'),
            ],
        ],
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/qr/attribution', [TurfController::class, 'recordQrAttribution']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::delete('/user', [AuthController::class, 'deleteAccount']);
    Route::get('/support/messages', [SupportController::class, 'index']);
    Route::post('/support/messages', [SupportController::class, 'store']);
    Route::post('/turfs/{turf}/reviews', [ReviewController::class, 'store']);
    Route::post('/turfs/{turf}/bookings', [BookingController::class, 'store']);
    Route::post('/turfs/{turf}/bookings/order', [BookingController::class, 'createRazorpayOrder']);
    Route::post('/turfs/{turf}/bookings/preview', [BookingController::class, 'preview']);
    Route::post('/coupons/verify', [BookingController::class, 'verifyCoupon']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/users/search', [AuthController::class, 'searchUsers']);
    Route::post('/users/quick-create', [AuthController::class, 'quickCreateUser']);
    Route::get('/dashboard/stats', [BookingController::class, 'getDashboardStats']);
    Route::post('/booking-dates/{bookingDate}/payments', [BookingController::class, 'recordPayment']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::get('/bookings/{booking}/invoice', [BookingInvoiceController::class, 'show']);
    Route::get('/bookings/{booking}/invoice/signed-url', [BookingInvoiceController::class, 'signedUrl']);
    // Booking Reschedule endpoints (staff-only)
    Route::get('/booking-dates/{bookingDate}/reschedule/slots', [BookingRescheduleController::class, 'slots']);
    Route::post('/booking-dates/{bookingDate}/reschedule/preview', [BookingRescheduleController::class, 'preview']);
    Route::post('/booking-dates/{bookingDate}/reschedule', [BookingRescheduleController::class, 'reschedule']);

    Route::post('/user/device-token', [AuthController::class, 'storeDeviceToken']);
    Route::delete('/user/device-token', [AuthController::class, 'destroyDeviceToken']);
    Route::patch('/user/notification-preferences', [AuthController::class, 'updateNotificationPreferences']);
});
