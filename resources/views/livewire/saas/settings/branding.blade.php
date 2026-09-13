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
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('Branding & Identity') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5">{{ __('Customize your SaaS platform brand name, public logo, contact email, phone, and main address.') }}</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition duration-150 cursor-pointer">
                        {{ __('Save Branding') }}
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

            <!-- Branding Card (Matching User Screenshot) -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    
                    <!-- Left Column: Logo Upload -->
                    <div class="flex flex-col items-center justify-start text-center space-y-4 lg:border-r lg:border-gray-100 lg:pr-8">
                        <span class="text-sm font-semibold text-gray-700">{{ __('SaaS Brand Logo') }}</span>
                        
                        <div class="relative group">
                            <!-- Logo Box Preview -->
                            <div class="h-32 w-32 rounded-3xl bg-gray-50 border border-gray-200 flex items-center justify-center overflow-hidden relative shadow-inner">
                                @if ($new_logo)
                                    <img src="{{ $new_logo->temporaryUrl() }}" class="h-full w-full object-contain p-2" />
                                @elseif ($current_logo_path)
                                    <img src="{{ Storage::url($current_logo_path) }}" class="h-full w-full object-contain p-2" />
                                @else
                                    <svg class="h-10 w-10 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" />
                                    </svg>
                                @endif
                                
                                <div class="absolute inset-0 bg-gray-950/40 opacity-0 group-hover:opacity-100 flex items-center justify-center transition duration-200 backdrop-blur-[1px]">
                                    <span class="text-[9px] font-black uppercase text-white tracking-widest">{{ __('Upload Logo') }}</span>
                                </div>
                                <input type="file" wire:model="new_logo" class="absolute inset-0 opacity-0 w-full h-full cursor-pointer" accept="image/*" />
                            </div>
                        </div>

                        <div class="text-[10px] text-gray-400 font-semibold space-y-1">
                            <p>{{ __('Recommended Aspect Ratio: 1:1 Square') }}</p>
                            <p>{{ __('Maximum allowed size: 2MB') }}</p>
                        </div>
                        <x-input-error :messages="$errors->get('new_logo')" class="mt-2" />
                    </div>

                    <!-- Right Column: Form Fields -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- App Name -->
                            <div>
                                <x-input-label for="appName" :value="__('App Name')" />
                                <x-text-input wire:model.live.debounce.250ms="app_name" id="appName" type="text" class="mt-1.5 block w-full" placeholder="TurfBooking" />
                                <x-input-error :messages="$errors->get('app_name')" class="mt-2" />
                            </div>

                            <!-- Contact Email -->
                            <div>
                                <x-input-label for="contactEmail" :value="__('Contact Email')" />
                                <x-text-input wire:model.live.debounce.250ms="contact_email" id="contactEmail" type="email" class="mt-1.5 block w-full" placeholder="sandeep198558@gmail.com" />
                                <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                            </div>

                            <!-- Contact Mobile -->
                            <div>
                                <x-input-label for="contactMobile" :value="__('Contact Mobile')" />
                                <x-text-input wire:model.live.debounce.250ms="contact_mobile" id="contactMobile" type="text" class="mt-1.5 block w-full" placeholder="9664588677" />
                                <x-input-error :messages="$errors->get('contact_mobile')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Address -->
                        <div>
                            <x-input-label for="companyAddress" :value="__('Address')" />
                            <textarea wire:model.live.debounce.250ms="address" id="companyAddress" rows="3" class="mt-1.5 block w-full rounded-2xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm text-xs p-4" placeholder="Mumbai, India"></textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
