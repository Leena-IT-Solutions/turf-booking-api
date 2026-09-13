<?php

use App\Models\SaasSetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $company_name = '';
    public $company_email = '';
    public $company_phone = '';
    public $company_address = '';
    public $pincode = '';
    public $city = '';
    public $state = '';
    public $country = 'India';
    public $gst_number = '';
    public $udyam_registration_number = '';

    // GST & SAC Configurations
    public $subscription_gst_sac = '998314';
    public $subscription_gst_percentage = 18.00;
    public $commission_gst_sac = '998599';
    public $commission_gst_percentage = 18.00;
    public $booking_gst_sac = '999652';
    public $booking_gst_percentage = 18.00;

    public function mount()
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
        ]);

        $this->company_name = $setting->company_name;
        $this->company_email = $setting->company_email;
        $this->company_phone = $setting->company_phone;
        $this->company_address = $setting->company_address;
        $this->pincode = $setting->pincode;
        $this->city = $setting->city;
        $this->state = $setting->state;
        $this->country = $setting->country ?: 'India';
        $this->gst_number = $setting->gst_number;
        $this->udyam_registration_number = $setting->udyam_registration_number;

        $this->subscription_gst_sac = $setting->subscription_gst_sac ?: '998314';
        $this->subscription_gst_percentage = $setting->subscription_gst_percentage ?? 18.00;
        $this->commission_gst_sac = $setting->commission_gst_sac ?: '998599';
        $this->commission_gst_percentage = $setting->commission_gst_percentage ?? 18.00;
        $this->booking_gst_sac = $setting->booking_gst_sac ?: '999652';
        $this->booking_gst_percentage = $setting->booking_gst_percentage ?? 18.00;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, [
            'company_name' => 'nullable|string|max:150',
            'company_email' => 'nullable|email|max:150',
            'company_phone' => 'nullable|string|max:20',
            'company_address' => 'nullable|string|max:500',
            'pincode' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:20',
            'udyam_registration_number' => 'nullable|string|max:50',
            'subscription_gst_sac' => 'nullable|string|max:20',
            'subscription_gst_percentage' => 'nullable|numeric|min:0|max:100',
            'commission_gst_sac' => 'nullable|string|max:20',
            'commission_gst_percentage' => 'nullable|numeric|min:0|max:100',
            'booking_gst_sac' => 'nullable|string|max:20',
            'booking_gst_percentage' => 'nullable|numeric|min:0|max:100',
        ]);
    }

    public function saveSettings()
    {
        $this->validate([
            'company_name' => 'nullable|string|max:150',
            'company_email' => 'nullable|email|max:150',
            'company_phone' => 'nullable|string|max:20',
            'company_address' => 'nullable|string|max:500',
            'pincode' => 'nullable|string|max:10',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'gst_number' => 'nullable|string|max:20',
            'udyam_registration_number' => 'nullable|string|max:50',
            'subscription_gst_sac' => 'nullable|string|max:20',
            'subscription_gst_percentage' => 'nullable|numeric|min:0|max:100',
            'commission_gst_sac' => 'nullable|string|max:20',
            'commission_gst_percentage' => 'nullable|numeric|min:0|max:100',
            'booking_gst_sac' => 'nullable|string|max:20',
            'booking_gst_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $setting = SaasSetting::first() ?? new SaasSetting();

        $data = [
            'company_name' => $this->company_name,
            'company_email' => $this->company_email,
            'company_phone' => $this->company_phone,
            'company_address' => $this->company_address,
            'pincode' => $this->pincode,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'gst_number' => $this->gst_number ? strtoupper(trim($this->gst_number)) : null,
            'udyam_registration_number' => $this->udyam_registration_number ? strtoupper(trim($this->udyam_registration_number)) : null,
            'subscription_gst_sac' => $this->subscription_gst_sac ? trim($this->subscription_gst_sac) : '998314',
            'subscription_gst_percentage' => $this->subscription_gst_percentage !== '' ? (float) $this->subscription_gst_percentage : 0,
            'commission_gst_sac' => $this->commission_gst_sac ? trim($this->commission_gst_sac) : '998599',
            'commission_gst_percentage' => $this->commission_gst_percentage !== '' ? (float) $this->commission_gst_percentage : 0,
            'booking_gst_sac' => $this->booking_gst_sac ? trim($this->booking_gst_sac) : '999652',
            'booking_gst_percentage' => $this->booking_gst_percentage !== '' ? (float) $this->booking_gst_percentage : 0,
        ];

        $setting->fill($data)->save();

        session()->flash('status', __('Legal, GST & SAC settings updated successfully.'));
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Top Tab Bar -->
        @include('livewire.saas.settings.tabs', ['active' => 'legal'])

        <form wire:submit="saveSettings" class="space-y-6">
            <!-- Header Card -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Legal, Tax & SAC Configurations') }}</h2>
                        <p class="text-xs text-gray-500 mt-1">{{ __('Manage corporate identity, registered address, GSTIN, MSME Udyam, and SAC tax codes for invoicing.') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow-sm hover:shadow transition duration-150 cursor-pointer disabled:opacity-50">
                        <svg wire:loading.remove wire:target="saveSettings" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg wire:loading wire:target="saveSettings" class="animate-spin w-4 h-4 text-white" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ __('Save Legal Details') }}</span>
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

            <!-- GST & SAC Tax Service Configurations (Left Label - Right Field Layout) -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('GST & SAC Service Tax Rates') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Define Service Accounting Codes (SAC) and applicable GST rates for each billing case.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- Case 1: Subscription Plan for Turf Owners -->
                    <div class="py-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="space-y-1.5 max-w-xl">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-bold text-gray-900 block">
                                    {{ __('1. Turf Owner Subscription Plans') }}
                                </label>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ __('B2B SaaS') }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Applied on software subscriptions sold to turf owners for venue listing and management.') }}
                                <span class="text-gray-400 font-medium">({{ __('Standard SAC: 998314 - IT Software Services') }})</span>
                            </p>
                            <x-input-error :messages="$errors->get('subscription_gst_sac')" class="mt-1" />
                            <x-input-error :messages="$errors->get('subscription_gst_percentage')" class="mt-1" />
                        </div>

                        <div class="shrink-0 flex items-center gap-3">
                            <!-- SAC Code -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-400 block">{{ __('SAC Code') }}</span>
                                <div class="relative w-36">
                                    <input wire:model.live.debounce.250ms="subscription_gst_sac" id="subscriptionGstSac" type="text" maxlength="10" 
                                        class="w-full px-3 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 text-center uppercase tracking-wider transition" 
                                        placeholder="998314" />
                                </div>
                            </div>

                            <!-- GST % -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-400 block">{{ __('GST Rate') }}</span>
                                <div class="relative w-28">
                                    <input wire:model.live.debounce.250ms="subscription_gst_percentage" id="subscriptionGstPct" type="number" step="0.01" min="0" max="100" 
                                        class="w-full pr-8 pl-3 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 text-right transition" 
                                        placeholder="18.00" />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Case 2: Commission for Non-Subscription Turf Owners -->
                    <div class="py-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="space-y-1.5 max-w-xl">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-bold text-gray-900 block">
                                    {{ __('2. Platform Commission (Non-Subscription)') }}
                                </label>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-100">
                                    {{ __('Commission Fee') }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Commission charged to turf owners who operate without a monthly subscription plan.') }}
                                <span class="text-gray-400 font-medium">({{ __('Standard SAC: 998599 - Other Support & Facilitation Services') }})</span>
                            </p>
                            <x-input-error :messages="$errors->get('commission_gst_sac')" class="mt-1" />
                            <x-input-error :messages="$errors->get('commission_gst_percentage')" class="mt-1" />
                        </div>

                        <div class="shrink-0 flex items-center gap-3">
                            <!-- SAC Code -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-400 block">{{ __('SAC Code') }}</span>
                                <div class="relative w-36">
                                    <input wire:model.live.debounce.250ms="commission_gst_sac" id="commissionGstSac" type="text" maxlength="10" 
                                        class="w-full px-3 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 text-center uppercase tracking-wider transition" 
                                        placeholder="998599" />
                                </div>
                            </div>

                            <!-- GST % -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-400 block">{{ __('GST Rate') }}</span>
                                <div class="relative w-28">
                                    <input wire:model.live.debounce.250ms="commission_gst_percentage" id="commissionGstPct" type="number" step="0.01" min="0" max="100" 
                                        class="w-full pr-8 pl-3 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 text-right transition" 
                                        placeholder="18.00" />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Case 3: Customer Turf Slot Bookings -->
                    <div class="py-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div class="space-y-1.5 max-w-xl">
                            <div class="flex items-center gap-2">
                                <label class="text-xs font-bold text-gray-900 block">
                                    {{ __('3. Turf Customer Bookings') }}
                                </label>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                    {{ __('Ground Bookings') }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Invoiced for ground & slot bookings made by players and customers at sports turfs.') }}
                                <span class="text-gray-400 font-medium">({{ __('Standard SAC: 999652 - Sports & Athletic Facility Services') }})</span>
                            </p>
                            <x-input-error :messages="$errors->get('booking_gst_sac')" class="mt-1" />
                            <x-input-error :messages="$errors->get('booking_gst_percentage')" class="mt-1" />
                        </div>

                        <div class="shrink-0 flex items-center gap-3">
                            <!-- SAC Code -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-400 block">{{ __('SAC Code') }}</span>
                                <div class="relative w-36">
                                    <input wire:model.live.debounce.250ms="booking_gst_sac" id="bookingGstSac" type="text" maxlength="10" 
                                        class="w-full px-3 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 text-center uppercase tracking-wider transition" 
                                        placeholder="999652" />
                                </div>
                            </div>

                            <!-- GST % -->
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase text-gray-400 block">{{ __('GST Rate') }}</span>
                                <div class="relative w-28">
                                    <input wire:model.live.debounce.250ms="booking_gst_percentage" id="bookingGstPct" type="number" step="0.01" min="0" max="100" 
                                        class="w-full pr-8 pl-3 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 text-right transition" 
                                        placeholder="18.00" />
                                    <span class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tax & Business Identifiers Card -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Tax & Government Registrations') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Required for statutory compliance, GST tax invoices, and B2B vendor reporting.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- GSTIN -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="gstNumber" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('GST Number (GSTIN)') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('15-digit Goods and Services Tax Identification Number printed on invoices.') }}
                            </p>
                            <x-input-error :messages="$errors->get('gst_number')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-60">
                                <input wire:model.live.debounce.250ms="gst_number" id="gstNumber" type="text" maxlength="15" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 uppercase tracking-wider transition" 
                                    placeholder="27AAAAA0000A1Z5" />
                            </div>
                        </div>
                    </div>

                    <!-- Udyam MSME -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="udyamReg" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Udyam Registration Number') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Ministry of Micro, Small & Medium Enterprises (MSME) certificate identification.') }}
                            </p>
                            <x-input-error :messages="$errors->get('udyam_registration_number')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-60">
                                <input wire:model.live.debounce.250ms="udyam_registration_number" id="udyamReg" type="text" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 uppercase tracking-wider transition" 
                                    placeholder="UDYAM-MH-01-0000000" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Profile & Contact Card -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Corporate Profile & Communications') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Official legal entity name and official communication channels.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- Company Name -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="companyName" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Company / Entity Name') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Registered legal name under Companies Act or LLP incorporation.') }}
                            </p>
                            <x-input-error :messages="$errors->get('company_name')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-72">
                                <input wire:model.live.debounce.250ms="company_name" id="companyName" type="text" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 transition" 
                                    placeholder="e.g. TurfBooking Private Limited" />
                            </div>
                        </div>
                    </div>

                    <!-- Company Email -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="companyEmail" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Legal / Finance Email') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Email designated for official billing, GST receipts, and legal notices.') }}
                            </p>
                            <x-input-error :messages="$errors->get('company_email')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-72">
                                <input wire:model.live.debounce.250ms="company_email" id="companyEmail" type="email" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 transition" 
                                    placeholder="e.g. legal@turfbooking.com" />
                            </div>
                        </div>
                    </div>

                    <!-- Company Phone -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="companyPhone" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Official Telephone / Mobile') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Primary contact number printed on invoices.') }}
                            </p>
                            <x-input-error :messages="$errors->get('company_phone')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-72">
                                <input wire:model.live.debounce.250ms="company_phone" id="companyPhone" type="text" 
                                    class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 transition" 
                                    placeholder="e.g. +91 9664588677" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registered Office Address Card -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Registered Office Address') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Address printed on official tax invoices, billing receipts, and legal communications.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- Street Address -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="companyAddressText" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Street / Building Address') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Unit number, premises name, street and locality.') }}
                            </p>
                            <x-input-error :messages="$errors->get('company_address')" class="mt-1" />
                        </div>
                        <div class="shrink-0 w-full sm:w-96">
                            <textarea wire:model.live.debounce.250ms="company_address" id="companyAddressText" rows="2" 
                                class="w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-semibold text-gray-900 transition leading-relaxed" 
                                placeholder="e.g. 101, Sports Arena Tower, Andheri East"></textarea>
                        </div>
                    </div>

                    <!-- City, State, Pincode, Country Grid -->
                    <div class="py-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- City -->
                        <div>
                            <x-input-label for="city" :value="__('City')" />
                            <input wire:model.live.debounce.250ms="city" id="city" type="text" 
                                class="mt-1.5 w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 transition" 
                                placeholder="e.g. Mumbai" />
                            <x-input-error :messages="$errors->get('city')" class="mt-1" />
                        </div>

                        <!-- State -->
                        <div>
                            <x-input-label for="state" :value="__('State')" />
                            <input wire:model.live.debounce.250ms="state" id="state" type="text" 
                                class="mt-1.5 w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 transition" 
                                placeholder="e.g. Maharashtra" />
                            <x-input-error :messages="$errors->get('state')" class="mt-1" />
                        </div>

                        <!-- Pincode -->
                        <div>
                            <x-input-label for="pincode" :value="__('Pincode')" />
                            <input wire:model.live.debounce.250ms="pincode" id="pincode" type="text" 
                                class="mt-1.5 w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold font-mono text-gray-900 transition" 
                                placeholder="e.g. 400069" />
                            <x-input-error :messages="$errors->get('pincode')" class="mt-1" />
                        </div>

                        <!-- Country -->
                        <div>
                            <x-input-label for="country" :value="__('Country')" />
                            <input wire:model.live.debounce.250ms="country" id="country" type="text" 
                                class="mt-1.5 w-full px-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-xs font-bold text-gray-900 transition" 
                                placeholder="e.g. India" />
                            <x-input-error :messages="$errors->get('country')" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
