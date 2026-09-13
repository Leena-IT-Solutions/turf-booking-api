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
        ];

        $setting->fill($data)->save();

        session()->flash('status', __('Legal & Corporate details updated successfully.'));
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Top Tab Bar -->
        @include('livewire.saas.settings.tabs', ['active' => 'legal'])

        <form wire:submit="saveSettings" class="space-y-6">
            <!-- Header Card -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('Legal & Compliance Details') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5">{{ __('Manage corporate identity, registered office address, GSTIN, and MSME Udyam registration for B2B invoicing.') }}</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition duration-150 cursor-pointer">
                        {{ __('Save Legal Details') }}
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

            <!-- Company Profile & Contact Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span>{{ __('Corporate Profile') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('Official legal entity name and official communication channels.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Company Name -->
                    <div>
                        <x-input-label for="companyName" :value="__('Company Name')" />
                        <x-text-input wire:model.live.debounce.250ms="company_name" id="companyName" type="text" class="mt-1.5 block w-full" placeholder="e.g. TurfBooking Private Limited" />
                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                    </div>

                    <!-- Company Email -->
                    <div>
                        <x-input-label for="companyEmail" :value="__('Company Email')" />
                        <x-text-input wire:model.live.debounce.250ms="company_email" id="companyEmail" type="email" class="mt-1.5 block w-full" placeholder="e.g. legal@turfbooking.com" />
                        <x-input-error :messages="$errors->get('company_email')" class="mt-2" />
                    </div>

                    <!-- Company Phone -->
                    <div>
                        <x-input-label for="companyPhone" :value="__('Company Phone')" />
                        <x-text-input wire:model.live.debounce.250ms="company_phone" id="companyPhone" type="text" class="mt-1.5 block w-full" placeholder="e.g. +91 9664588677" />
                        <x-input-error :messages="$errors->get('company_phone')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Tax & Business Identifiers Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>{{ __('Tax & Government Registrations') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('Required for GST tax invoicing, commission billing, and compliance.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- GST Number -->
                    <div>
                        <x-input-label for="gstNumber" :value="__('GST Number (GSTIN)')" />
                        <x-text-input wire:model.live.debounce.250ms="gst_number" id="gstNumber" type="text" maxlength="15" class="mt-1.5 block w-full uppercase font-mono" placeholder="e.g. 27AAAAA0000A1Z5" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('15-digit Goods and Services Tax Identification Number.') }}
                        </span>
                        <x-input-error :messages="$errors->get('gst_number')" class="mt-2" />
                    </div>

                    <!-- Udyam Registration Number -->
                    <div>
                        <x-input-label for="udyamReg" :value="__('Udyam Registration Number (MSME)')" />
                        <x-text-input wire:model.live.debounce.250ms="udyam_registration_number" id="udyamReg" type="text" class="mt-1.5 block w-full uppercase font-mono" placeholder="e.g. UDYAM-MH-01-0000000" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Ministry of Micro, Small & Medium Enterprises (MSME) certificate number.') }}
                        </span>
                        <x-input-error :messages="$errors->get('udyam_registration_number')" class="mt-2" />
                    </div>
                </div>
            </div>

            <!-- Registered Office Address Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ __('Registered Office Address') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('This address will be printed on official tax invoices and customer receipts.') }}</p>
                </div>

                <div class="space-y-6">
                    <!-- Address line -->
                    <div>
                        <x-input-label for="companyAddressText" :value="__('Registered Office Street Address')" />
                        <textarea wire:model.live.debounce.250ms="company_address" id="companyAddressText" rows="3" class="mt-1.5 block w-full rounded-2xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm text-xs p-4" placeholder="e.g. 101, Sports Arena Tower, Andheri East"></textarea>
                        <x-input-error :messages="$errors->get('company_address')" class="mt-2" />
                    </div>

                    <!-- City, State, Pincode, Country Grid -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- City -->
                        <div>
                            <x-input-label for="city" :value="__('City')" />
                            <x-text-input wire:model.live.debounce.250ms="city" id="city" type="text" class="mt-1.5 block w-full" placeholder="e.g. Mumbai" />
                            <x-input-error :messages="$errors->get('city')" class="mt-2" />
                        </div>

                        <!-- State -->
                        <div>
                            <x-input-label for="state" :value="__('State')" />
                            <x-text-input wire:model.live.debounce.250ms="state" id="state" type="text" class="mt-1.5 block w-full" placeholder="e.g. Maharashtra" />
                            <x-input-error :messages="$errors->get('state')" class="mt-2" />
                        </div>

                        <!-- Pincode -->
                        <div>
                            <x-input-label for="pincode" :value="__('Pincode')" />
                            <x-text-input wire:model.live.debounce.250ms="pincode" id="pincode" type="text" class="mt-1.5 block w-full" placeholder="e.g. 400069" />
                            <x-input-error :messages="$errors->get('pincode')" class="mt-2" />
                        </div>

                        <!-- Country -->
                        <div>
                            <x-input-label for="country" :value="__('Country')" />
                            <x-text-input wire:model.live.debounce.250ms="country" id="country" type="text" class="mt-1.5 block w-full" placeholder="e.g. India" />
                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
