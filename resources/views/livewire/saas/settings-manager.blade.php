<?php

use App\Models\SaasSetting;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    // Form inputs
    public $app_name = '';
    public $contact_email = '';
    public $contact_mobile = '';
    public $address = '';
    public $is_maintenance_mode = false;
    
    // File inputs
    public $new_logo;
    public $current_logo_path;

    public function mount()
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
            'is_maintenance_mode' => false,
        ]);

        $this->app_name = $setting->app_name;
        $this->contact_email = $setting->contact_email;
        $this->contact_mobile = $setting->contact_mobile;
        $this->address = $setting->address;
        $this->is_maintenance_mode = $setting->is_maintenance_mode;
        $this->current_logo_path = $setting->logo_path;
    }

    public function updated($propertyName)
    {
        $rules = [
            'app_name' => 'required|string|max:100',
            'contact_email' => 'required|email|max:150',
            'contact_mobile' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'new_logo' => 'nullable|image|max:2048', // 2MB max
            'is_maintenance_mode' => 'boolean',
        ];

        $this->validateOnly($propertyName, $rules);
    }

    public function saveSettings()
    {
        $rules = [
            'app_name' => 'required|string|max:100',
            'contact_email' => 'required|email|max:150',
            'contact_mobile' => 'required|string|max:20',
            'address' => 'nullable|string|max:500',
            'new_logo' => 'nullable|image|max:2048',
            'is_maintenance_mode' => 'boolean',
        ];

        $this->validate($rules);

        $setting = SaasSetting::first() ?? new SaasSetting();

        $data = [
            'app_name' => $this->app_name,
            'contact_email' => $this->contact_email,
            'contact_mobile' => $this->contact_mobile,
            'address' => $this->address,
            'is_maintenance_mode' => $this->is_maintenance_mode,
        ];

        if ($this->new_logo) {
            // Delete old logo if exists
            if ($setting->logo_path && Storage::disk('public')->exists($setting->logo_path)) {
                Storage::disk('public')->delete($setting->logo_path);
            }

            // Store new logo
            $path = $this->new_logo->store('logos', 'public');
            $data['logo_path'] = $path;
            $this->current_logo_path = $path;
            $this->reset('new_logo');
        }

        $setting->fill($data)->save();

        session()->flash('status', 'SaaS Settings updated successfully.');
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Card -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('SaaS Global Settings') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Configure and control core platform settings, logos, contact info, and maintenance mode status.') }}</p>
            </div>
        </div>

        @if (session()->has('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if ($is_maintenance_mode)
            <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/40 text-amber-800 dark:text-amber-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span>{{ __('Warning: Maintenance mode is currently active. The front-end booking options might be restricted.') }}</span>
            </div>
        @endif

        <!-- Form Card Grid -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50 overflow-hidden">
            <form wire:submit="saveSettings" class="p-6 sm:p-8 space-y-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left: Logo Upload -->
                    <div class="flex flex-col items-center justify-start text-center space-y-4 lg:border-r lg:border-gray-100 lg:dark:border-gray-700/50 lg:pr-8">
                        <x-input-label :value="__('SaaS Brand Logo')" />
                        
                        <div class="relative group">
                            <!-- Logo Box Preview -->
                            <div class="h-32 w-32 rounded-3xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center overflow-hidden relative shadow-inner">
                                @if ($new_logo)
                                    <img src="{{ $new_logo->temporaryUrl() }}" class="h-full w-full object-contain p-2" />
                                @elseif ($current_logo_path)
                                    <img src="{{ Storage::url($current_logo_path) }}" class="h-full w-full object-contain p-2" />
                                @else
                                    <svg class="h-10 w-10 text-gray-400 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                                    </svg>
                                @endif
                                
                                <div class="absolute inset-0 bg-gray-950/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition duration-200 backdrop-blur-[1px]">
                                    <span class="text-[9px] font-black uppercase text-white tracking-widest">{{ __('Upload Logo') }}</span>
                                </div>
                                <input type="file" wire:model="new_logo" class="absolute inset-0 opacity-0 w-full h-full cursor-pointer" accept="image/*" />
                            </div>
                        </div>

                        <div class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold space-y-1">
                            <p>{{ __('Recommended Aspect Ratio: 1:1 Square') }}</p>
                            <p>{{ __('Maximum allowed size: 2MB') }}</p>
                        </div>
                        <x-input-error :messages="$errors->get('new_logo')" class="mt-2" />
                    </div>

                    <!-- Right: Form Fields -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- App Name -->
                            <div>
                                <x-input-label for="appName" :value="__('App Name')" />
                                <x-text-input wire:model.live.debounce.250ms="app_name" id="appName" type="text" class="mt-1.5 block w-full" placeholder="TurfBooking" />
                                <x-input-error :messages="$errors->get('app_name')" class="mt-2" />
                            </div>

                            <!-- Maintenance Mode -->
                            <div class="flex items-center justify-between bg-gray-50/50 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-700/60 px-4 py-3 rounded-2xl self-end h-[42px]">
                                <span class="text-xs font-bold text-gray-750 dark:text-gray-300 uppercase tracking-wider">{{ __('Maintenance Mode') }}</span>
                                <button type="button" wire:click="$toggle('is_maintenance_mode')" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $is_maintenance_mode ? 'bg-amber-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $is_maintenance_mode ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>

                            <!-- Contact Email -->
                            <div>
                                <x-input-label for="contactEmail" :value="__('Contact Email')" />
                                <x-text-input wire:model.live.debounce.250ms="contact_email" id="contactEmail" type="email" class="mt-1.5 block w-full" placeholder="support@turfbooking.com" />
                                <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                            </div>

                            <!-- Contact Mobile -->
                            <div>
                                <x-input-label for="contactMobile" :value="__('Contact Mobile')" />
                                <x-text-input wire:model.live.debounce.250ms="contact_mobile" id="contactMobile" type="text" class="mt-1.5 block w-full" placeholder="9876543210" />
                                <x-input-error :messages="$errors->get('contact_mobile')" class="mt-2" />
                            </div>

                        </div>

                        <!-- Address -->
                        <div>
                            <x-input-label for="companyAddress" :value="__('Address')" />
                            <textarea wire:model.live.debounce.250ms="address" id="companyAddress" rows="3" class="mt-1.5 block w-full rounded-2xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 shadow-sm text-xs p-4" placeholder="123 Sport Complex St, Mumbai, India"></textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <!-- Form Actions footer -->
                <div class="flex justify-end pt-6 border-t border-gray-100 dark:border-gray-700/50">
                    <button type="submit" class="px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                        {{ __('Save Settings') }}
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
