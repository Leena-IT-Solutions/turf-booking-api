<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SupportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::get('/slider-images', [\App\Http\Controllers\Api\SliderImageController::class, 'index']);
Route::get('/turfs', [\App\Http\Controllers\Api\TurfController::class, 'index']);
Route::get('/config', function () {
    $setting = \App\Models\SaasSetting::first();
    return response()->json([
        'app_name' => $setting?->app_name ?? 'TurfBooking',
        'google_maps_api_key' => $setting?->google_maps_api_key,
        'turf_search_km' => $setting?->turf_search_km ?? 5,
    ]);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::put('/user/password', [AuthController::class, 'changePassword']);
    Route::delete('/user', [AuthController::class, 'deleteAccount']);
    Route::get('/support/messages', [SupportController::class, 'index']);
    Route::post('/support/messages', [SupportController::class, 'store']);
});
