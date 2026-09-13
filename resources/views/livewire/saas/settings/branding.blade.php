<?php

use App\Models\SaasSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $app_name = '';
    public $contact_email = '';
    public $contact_mobile = '';
    public $address = '';
    public $new_logo;
    public $current_logo_path;

    public function mount()
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
        ]);

        $this->app_name = $setting->app_name;
        $this->contact_email = $setting->contact_email;
        $this->contact_mobile = $setting->contact_mobile;
        $this->address = $setting->address;
        $this->current_logo_path = $setting->logo_path;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, [
            'app_name' => 'required|string|max:100',
            'contact_email' => 'required|email|max:150',
            'contact_mobile' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'new_logo' => 'nullable|image|max:2048',
        ]);
    }

    public function saveSettings()
    {
        $this->validate([
            'app_name' => 'required|string|max:100',
            'contact_email' => 'required|email|max:150',
            'contact_mobile' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'new_logo' => 'nullable|image|max:2048',
        ]);

        $setting = SaasSetting::first() ?? new SaasSetting();

        $data = [
            'app_name' => $this->app_name,
            'contact_email' => $this->contact_email,
            'contact_mobile' => $this->contact_mobile,
            'address' => $this->address,
        ];

        if ($this->new_logo) {
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            $path = $this->new_logo->store('logos', 'public');
            $data['logo_path'] = $path;
            $this->current_logo_path = $path;
            $this->reset('new_logo');
        }

        $setting->fill($data)->save();

        session()->flash('status', __('Branding settings updated successfully.'));
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Top Tab Bar -->
        @include('livewire.saas.settings.tabs', ['active' => 'branding'])

        <form wire:submit="saveSettings" class="space-y-6">
            <!-- Header Card -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-2xs shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Branding & Identity') }}</h2>
                        <p class="text-xs text-gray-500 mt-1">{{ __('Customize your SaaS platform brand name, public logo, contact email, phone, and main address.') }}</p>
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
                        <span>{{ __('Save Branding') }}</span>
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

            <!-- Modern Brand Visual Identity Studio Card -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-8">
                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-gray-100">
                    <div class="flex items-center gap-3.5">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100/80 flex items-center justify-center text-indigo-600 shadow-sm">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 tracking-tight flex items-center gap-2">
                                <span>{{ __('Brand Visual Identity') }}</span>
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100">{{ __('Public Asset') }}</span>
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">{{ __('The official visual mark displayed across player mobile apps, website navigation, invoices, and automated emails.') }}</p>
                        </div>
                    </div>
                    
                    <div>
                        @if ($new_logo)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                {{ __('Unsaved Preview') }}
                            </span>
                        @elseif ($current_logo_path)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                {{ __('Active Brand Logo') }}
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-gray-50 text-gray-600 border border-gray-200">
                                {{ __('Default Icon') }}
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Logo Upload & Live Interactive Preview Studio -->
                <div class="flex flex-col lg:flex-row items-center lg:items-start gap-8">
                    
                    <!-- Col 1: Logo Avatar Frame (Interactive drop/upload) -->
                    <div class="w-full lg:w-44 shrink-0 flex flex-col items-center sm:items-start text-center sm:text-left gap-3">
                        <div class="relative group cursor-pointer">
                            <!-- Outer Frame with soft shadow & gradient ring -->
                            <div class="w-36 h-36 sm:w-40 sm:h-40 rounded-3xl bg-gradient-to-br from-gray-50 via-white to-gray-50 p-3 border-2 border-dashed border-gray-200 group-hover:border-indigo-500 transition-all duration-300 shadow-sm flex items-center justify-center relative overflow-hidden">
                                @if ($new_logo)
                                    <img src="{{ $new_logo->temporaryUrl() }}" alt="New Logo Preview" class="max-h-full max-w-full object-contain drop-shadow-sm" />
                                @elseif ($current_logo_path)
                                    <img src="{{ Storage::url($current_logo_path) }}" alt="Current Brand Logo" class="max-h-full max-w-full object-contain drop-shadow-sm" />
                                @else
                                    <div class="flex flex-col items-center justify-center text-gray-400 space-y-1">
                                        <svg class="h-10 w-10 text-gray-300 group-hover:text-indigo-400 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                                        </svg>
                                        <span class="text-[10px] font-bold text-gray-400">{{ __('No Logo Set') }}</span>
                                    </div>
                                @endif

                                <!-- Hover Overlay with Action Text -->
                                <div class="absolute inset-0 bg-gray-900/60 opacity-0 group-hover:opacity-100 flex flex-col items-center justify-center transition-opacity duration-200 backdrop-blur-[2px] rounded-3xl p-2 text-white">
                                    <svg class="w-6 h-6 text-white mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">{{ __('Change Logo') }}</span>
                                </div>

                                <!-- Actual File Input Overlay -->
                                <input type="file" wire:model="new_logo" class="absolute inset-0 opacity-0 w-full h-full cursor-pointer z-10" accept="image/png,image/jpeg,image/svg+xml,image/webp" />
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex items-center gap-2">
                            <label class="relative cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold rounded-xl border border-indigo-200/70 transition shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <span>{{ __('Upload New Logo') }}</span>
                                <input type="file" wire:model="new_logo" class="sr-only" accept="image/png,image/jpeg,image/svg+xml,image/webp" />
                            </label>

                            @if ($new_logo)
                                <button type="button" wire:click="$set('new_logo', null)" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-xl transition cursor-pointer">
                                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    <span>{{ __('Revert') }}</span>
                                </button>
                            @endif
                        </div>

                        <div wire:loading wire:target="new_logo" class="text-xs font-bold text-indigo-600 flex items-center gap-2">
                            <svg class="animate-spin h-3.5 w-3.5 text-indigo-600" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>{{ __('Uploading preview...') }}</span>
                        </div>

                        <x-input-error :messages="$errors->get('new_logo')" class="mt-1" />
                    </div>

                    <!-- Col 2: Specifications and Live Client Mockup -->
                    <div class="w-full flex-1 flex flex-col space-y-5">
                        <!-- Format Pills -->
                        <div class="bg-gray-50/70 border border-gray-100 rounded-2xl p-4 space-y-2.5">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500 block">{{ __('Asset Guidelines & Best Practices') }}</span>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white text-gray-700 text-xs font-semibold border border-gray-200/80 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                                    </svg>
                                    {{ __('1:1 Square Aspect Ratio') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white text-gray-700 text-xs font-semibold border border-gray-200/80 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('PNG, SVG, JPG or WebP') }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-white text-gray-700 text-xs font-semibold border border-gray-200/80 shadow-sm">
                                    <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Maximum 2MB File Size') }}
                                </span>
                            </div>
                        </div>

                        <!-- Real-time Client App Header Mockup Preview (Clean Light Theme) -->
                        <div class="rounded-2xl border border-indigo-100/70 bg-gradient-to-b from-slate-50/80 via-white to-white p-4 shadow-sm relative overflow-hidden">
                            <!-- Top Browser/Device Mockup Bar -->
                            <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-rose-400/80"></span>
                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400/80"></span>
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400/80"></span>
                                    </div>
                                    <div class="hidden sm:flex items-center gap-1.5 ml-2 px-2.5 py-0.5 rounded-lg bg-gray-50 border border-gray-200/60 text-[10px] text-gray-500 font-mono">
                                        <svg class="w-3 h-3 text-emerald-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 1a4.5 4.5 0 00-4.5 4.5V9H5a2 2 0 00-2 2v6a2 2 0 002 2h10a2 2 0 002-2v-6a2 2 0 00-2-2h-.5V5.5A4.5 4.5 0 0010 1zm3 8V5.5a3 3 0 10-6 0V9h6z" clip-rule="evenodd" />
                                        </svg>
                                        <span>app.turfbooking.com</span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Live Client Preview') }}</span>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                        {{ __('Customer View') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Simulated App Navbar -->
                            <div class="flex items-center justify-between bg-white rounded-xl p-3 border border-gray-200/80 shadow-xs">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-50/50 to-white p-1 border border-indigo-100 shadow-xs flex items-center justify-center shrink-0">
                                        @if ($new_logo)
                                            <img src="{{ $new_logo->temporaryUrl() }}" class="max-h-full max-w-full object-contain" />
                                        @elseif ($current_logo_path)
                                            <img src="{{ Storage::url($current_logo_path) }}" class="max-h-full max-w-full object-contain" />
                                        @else
                                            <div class="w-full h-full bg-indigo-600 rounded-lg flex items-center justify-center text-white text-xs font-black">
                                                {{ substr($app_name ?: 'T', 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-bold text-gray-900 tracking-tight">{{ $app_name ?: __('TurfBooking') }}</span>
                                            <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200/70 text-[9px] font-bold tracking-wider uppercase">VERIFIED</span>
                                        </div>
                                        <p class="text-[11px] text-gray-400 font-medium">{{ __('Online Sports & Turf Ground Booking') }}</p>
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div class="hidden sm:flex items-center gap-3 text-xs font-semibold text-gray-500">
                                        <span class="text-indigo-600 font-bold">{{ __('Turfs') }}</span>
                                        <span class="hover:text-gray-900 cursor-pointer">{{ __('Slots') }}</span>
                                    </div>
                                    <span class="text-xs px-3.5 py-1.5 rounded-xl bg-indigo-600 text-white font-bold shadow-xs flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        <span>{{ __('Book Slot') }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modern General Information & Public Contact Card -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <!-- Section Header -->
                <div class="flex items-center gap-3.5 pb-6 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100/80 flex items-center justify-center text-emerald-600 shadow-sm">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight flex items-center gap-2">
                            <span>{{ __('Public Platform Details & Contact Channels') }}</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Define your official platform name, player helpline contacts, and operating business location.') }}</p>
                    </div>
                </div>

                <!-- 3-Column Responsive Grid for Top Inputs -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- App Name -->
                    <div class="space-y-1.5">
                        <label for="appName" class="text-xs font-bold text-gray-700 tracking-wide flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>{{ __('Platform / App Name') }}</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-2xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4 text-indigo-500/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <input wire:model.live.debounce.250ms="app_name" id="appName" type="text" 
                                class="w-full pl-10 pr-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 transition duration-200 placeholder:text-gray-400 placeholder:font-normal" 
                                placeholder="TurfBooking" />
                        </div>
                        <p class="text-[11px] text-gray-400 font-medium">{{ __('Public brand title shown in app header & receipts.') }}</p>
                        <x-input-error :messages="$errors->get('app_name')" class="mt-1" />
                    </div>

                    <!-- Contact Email -->
                    <div class="space-y-1.5">
                        <label for="contactEmail" class="text-xs font-bold text-gray-700 tracking-wide flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                            <span>{{ __('Public Support Email') }}</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-2xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4 text-sky-500/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <input wire:model.live.debounce.250ms="contact_email" id="contactEmail" type="email" 
                                class="w-full pl-10 pr-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 transition duration-200 placeholder:text-gray-400 placeholder:font-normal" 
                                placeholder="sandeep198558@gmail.com" />
                        </div>
                        <p class="text-[11px] text-gray-400 font-medium">{{ __('Customer support & transactional alerts recipient.') }}</p>
                        <x-input-error :messages="$errors->get('contact_email')" class="mt-1" />
                    </div>

                    <!-- Contact Mobile -->
                    <div class="space-y-1.5">
                        <label for="contactMobile" class="text-xs font-bold text-gray-700 tracking-wide flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                            <span>{{ __('Contact Mobile / WhatsApp') }}</span>
                            <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative rounded-2xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                <svg class="w-4 h-4 text-emerald-500/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <input wire:model.live.debounce.250ms="contact_mobile" id="contactMobile" type="text" 
                                class="w-full pl-10 pr-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 transition duration-200 placeholder:text-gray-400 placeholder:font-normal" 
                                placeholder="9664588677" />
                        </div>
                        <p class="text-[11px] text-gray-400 font-medium">{{ __('Customer helpline shown in player mobile app.') }}</p>
                        <x-input-error :messages="$errors->get('contact_mobile')" class="mt-1" />
                    </div>
                </div>

                <!-- Operating Headquarters Address -->
                <div class="space-y-1.5 pt-2">
                    <label for="companyAddress" class="text-xs font-bold text-gray-700 tracking-wide flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <span>{{ __('Headquarters / Operating Address') }}</span>
                    </label>
                    <div class="relative rounded-2xl shadow-sm">
                        <div class="absolute top-3 left-3.5 pointer-events-none text-gray-400">
                            <svg class="w-4 h-4 text-rose-500/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z" />
                            </svg>
                        </div>
                        <textarea wire:model.live.debounce.250ms="address" id="companyAddress" rows="3" 
                            class="w-full pl-10 pr-4 py-3 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-medium text-gray-900 transition duration-200 placeholder:text-gray-400" 
                            placeholder="e.g. 102, Sports Arena Hub, Andheri West, Mumbai, India - 400053"></textarea>
                    </div>
                    <p class="text-[11px] text-gray-400 font-medium">{{ __('Physical office location or primary operations center printed on customer booking invoices.') }}</p>
                    <x-input-error :messages="$errors->get('address')" class="mt-1" />
                </div>
            </div>
        </form>
    </div>
</div>
