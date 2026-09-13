<?php

use App\Models\SaasSetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $gemini_api_key = '';
    public $google_maps_api_key = '';
    public $whatsapp_token = '';
    public $whatsapp_phone_number_id = '';
    public $whatsapp_business_account_id = '';
    public $whatsapp_otp_template = 'turf_otp';
    public $razorpay_key = '';
    public $razorpay_secret = '';
    public $razorpayx_account_number = '';
    public $razorpayx_webhook_secret = '';
    public $mailgun_domain = '';
    public $mailgun_secret = '';
    public $mailgun_endpoint = 'api.mailgun.net';

    public function mount()
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
        ]);

        $this->gemini_api_key = $setting->gemini_api_key;
        $this->google_maps_api_key = $setting->google_maps_api_key;
        $this->whatsapp_token = $setting->whatsapp_token;
        $this->whatsapp_phone_number_id = $setting->whatsapp_phone_number_id;
        $this->whatsapp_business_account_id = $setting->whatsapp_business_account_id;
        $this->whatsapp_otp_template = $setting->whatsapp_otp_template ?: 'turf_otp';
        $this->razorpay_key = $setting->razorpay_key;
        $this->razorpay_secret = $setting->razorpay_secret;
        $this->razorpayx_account_number = $setting->razorpayx_account_number;
        $this->razorpayx_webhook_secret = $setting->razorpayx_webhook_secret;
        $this->mailgun_domain = $setting->mailgun_domain;
        $this->mailgun_secret = $setting->mailgun_secret;
        $this->mailgun_endpoint = $setting->mailgun_endpoint ?: 'api.mailgun.net';
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, [
            'gemini_api_key' => 'nullable|string|max:255',
            'google_maps_api_key' => 'nullable|string|max:255',
            'whatsapp_token' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'whatsapp_business_account_id' => 'nullable|string|max:255',
            'whatsapp_otp_template' => 'nullable|string|max:255',
            'razorpay_key' => 'nullable|string|max:255',
            'razorpay_secret' => 'nullable|string|max:255',
            'razorpayx_account_number' => 'nullable|string|max:255',
            'razorpayx_webhook_secret' => 'nullable|string|max:255',
            'mailgun_domain' => 'nullable|string|max:255',
            'mailgun_secret' => 'nullable|string|max:255',
            'mailgun_endpoint' => 'nullable|string|max:255',
        ]);
    }

    public function saveSettings()
    {
        $this->validate([
            'gemini_api_key' => 'nullable|string|max:255',
            'google_maps_api_key' => 'nullable|string|max:255',
            'whatsapp_token' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|string|max:255',
            'whatsapp_business_account_id' => 'nullable|string|max:255',
            'whatsapp_otp_template' => 'nullable|string|max:255',
            'razorpay_key' => 'nullable|string|max:255',
            'razorpay_secret' => 'nullable|string|max:255',
            'razorpayx_account_number' => 'nullable|string|max:255',
            'razorpayx_webhook_secret' => 'nullable|string|max:255',
            'mailgun_domain' => 'nullable|string|max:255',
            'mailgun_secret' => 'nullable|string|max:255',
            'mailgun_endpoint' => 'nullable|string|max:255',
        ]);

        $setting = SaasSetting::first() ?? new SaasSetting();

        $data = [
            'gemini_api_key' => $this->gemini_api_key,
            'google_maps_api_key' => $this->google_maps_api_key,
            'whatsapp_token' => $this->whatsapp_token,
            'whatsapp_phone_number_id' => $this->whatsapp_phone_number_id,
            'whatsapp_business_account_id' => $this->whatsapp_business_account_id,
            'whatsapp_otp_template' => $this->whatsapp_otp_template,
            'razorpay_key' => $this->razorpay_key,
            'razorpay_secret' => $this->razorpay_secret,
            'razorpayx_account_number' => $this->razorpayx_account_number,
            'razorpayx_webhook_secret' => $this->razorpayx_webhook_secret,
            'mailgun_domain' => $this->mailgun_domain,
            'mailgun_secret' => $this->mailgun_secret,
            'mailgun_endpoint' => $this->mailgun_endpoint,
        ];

        $setting->fill($data)->save();

        session()->flash('status', __('API credentials and service keys updated successfully.'));
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Top Tab Bar -->
        @include('livewire.saas.settings.tabs', ['active' => 'credentials'])

        <form wire:submit="saveSettings" class="space-y-6">
            <!-- Header Card -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('API Credentials & Integrations') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5">{{ __('Securely configure external service keys for AI Assistant, Google Maps, WhatsApp Cloud, Razorpay, and Mailgun.') }}</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition duration-150 cursor-pointer">
                        {{ __('Save Credentials') }}
                    </button>
                </div>
            </div>

            @if (session()->has('status'))
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <!-- Gemini AI & Google Maps Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>{{ __('Google Cloud Services (AI & Maps)') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('API keys for intelligent assistant capabilities and interactive turf map locations.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Gemini API Key -->
                    <div>
                        <x-input-label for="geminiKey" :value="__('Google Gemini API Key')" />
                        <x-text-input wire:model.live.debounce.250ms="gemini_api_key" id="geminiKey" type="password" class="mt-1.5 block w-full font-mono text-xs" placeholder="AIzaSy..." />
                        <x-input-error :messages="$errors->get('gemini_api_key')" class="mt-2" />
                    </div>

                    <!-- Google Maps API Key -->
                    <div>
                        <x-input-label for="mapsKey" :value="__('Google Maps Platform API Key')" />
                        <x-text-input wire:model.live.debounce.250ms="google_maps_api_key" id="mapsKey" type="password" class="mt-1.5 block w-full font-mono text-xs" placeholder="AIzaSy..." />
                        <x-input-error :messages="$errors->get('google_maps_api_key')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- WhatsApp Cloud API Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <span>{{ __('Meta WhatsApp Business Cloud API') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('Credentials for automated WhatsApp OTP login, booking confirmation alerts, and player receipts.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Phone Number ID -->
                    <div>
                        <x-input-label for="waPhoneId" :value="__('WhatsApp Phone Number ID')" />
                        <x-text-input wire:model.live.debounce.250ms="whatsapp_phone_number_id" id="waPhoneId" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="e.g. 1004928374829" />
                        <x-input-error :messages="$errors->get('whatsapp_phone_number_id')" class="mt-2" />
                    </div>

                    <!-- Business Account ID -->
                    <div>
                        <x-input-label for="waBizId" :value="__('WhatsApp Business Account ID (WABA ID)')" />
                        <x-text-input wire:model.live.debounce.250ms="whatsapp_business_account_id" id="waBizId" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="e.g. 2003948572910" />
                        <x-input-error :messages="$errors->get('whatsapp_business_account_id')" class="mt-2" />
                    </div>

                    <!-- OTP Template Name -->
                    <div>
                        <x-input-label for="waTemplate" :value="__('Authentication OTP Template Name')" />
                        <x-text-input wire:model.live.debounce.250ms="whatsapp_otp_template" id="waTemplate" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="e.g. turf_otp" />
                        <x-input-error :messages="$errors->get('whatsapp_otp_template')" class="mt-2" />
                    </div>

                    <!-- Access Token (Full Width) -->
                    <div class="md:col-span-2">
                        <x-input-label for="waToken" :value="__('Permanent System User Access Token')" />
                        <textarea wire:model.live.debounce.250ms="whatsapp_token" id="waToken" rows="2" class="mt-1.5 block w-full rounded-2xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm font-mono text-xs p-3" placeholder="EAAB..."></textarea>
                        <x-input-error :messages="$errors->get('whatsapp_token')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Razorpay & RazorpayX Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                        <span>{{ __('Razorpay Payment Gateway & RazorpayX Payouts') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('API credentials for customer online slot payments and automated turf owner bank settlements.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Razorpay Key -->
                    <div>
                        <x-input-label for="rzpKey" :value="__('Razorpay Key ID')" />
                        <x-text-input wire:model.live.debounce.250ms="razorpay_key" id="rzpKey" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="rzp_live_..." />
                        <x-input-error :messages="$errors->get('razorpay_key')" class="mt-2" />
                    </div>

                    <!-- Razorpay Secret -->
                    <div>
                        <x-input-label for="rzpSecret" :value="__('Razorpay Key Secret')" />
                        <x-text-input wire:model.live.debounce.250ms="razorpay_secret" id="rzpSecret" type="password" class="mt-1.5 block w-full font-mono text-xs" placeholder="••••••••••••••••" />
                        <x-input-error :messages="$errors->get('razorpay_secret')" class="mt-2" />
                    </div>

                    <!-- RazorpayX Account Number -->
                    <div>
                        <x-input-label for="rzpxAcc" :value="__('RazorpayX Payouts Virtual Account Number')" />
                        <x-text-input wire:model.live.debounce.250ms="razorpayx_account_number" id="rzpxAcc" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="e.g. 7878780080312345" />
                        <x-input-error :messages="$errors->get('razorpayx_account_number')" class="mt-2" />
                    </div>

                    <!-- RazorpayX Webhook Secret -->
                    <div>
                        <x-input-label for="rzpxWebhook" :value="__('RazorpayX Webhook Secret')" />
                        <x-text-input wire:model.live.debounce.250ms="razorpayx_webhook_secret" id="rzpxWebhook" type="password" class="mt-1.5 block w-full font-mono text-xs" placeholder="••••••••••••••••" />
                        <x-input-error :messages="$errors->get('razorpayx_webhook_secret')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Mailgun Email Service Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>{{ __('Mailgun Transactional Email') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('API settings for reliable email delivery (booking receipts, system alerts, password resets).') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Mailgun Domain -->
                    <div>
                        <x-input-label for="mgDomain" :value="__('Mailgun Domain')" />
                        <x-text-input wire:model.live.debounce.250ms="mailgun_domain" id="mgDomain" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="mg.turfbooking.com" />
                        <x-input-error :messages="$errors->get('mailgun_domain')" class="mt-2" />
                    </div>

                    <!-- Mailgun Secret Key -->
                    <div>
                        <x-input-label for="mgSecret" :value="__('Mailgun API Secret Key')" />
                        <x-text-input wire:model.live.debounce.250ms="mailgun_secret" id="mgSecret" type="password" class="mt-1.5 block w-full font-mono text-xs" placeholder="key-..." />
                        <x-input-error :messages="$errors->get('mailgun_secret')" class="mt-2" />
                    </div>

                    <!-- Mailgun Endpoint -->
                    <div>
                        <x-input-label for="mgEndpoint" :value="__('API Endpoint')" />
                        <x-text-input wire:model.live.debounce.250ms="mailgun_endpoint" id="mgEndpoint" type="text" class="mt-1.5 block w-full font-mono text-xs" placeholder="api.mailgun.net" />
                        <x-input-error :messages="$errors->get('mailgun_endpoint')" class="mt-2" />
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
