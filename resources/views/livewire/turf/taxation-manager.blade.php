<?php

use App\Models\Turf;
use App\Models\TurfSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public const INDIAN_STATES = [
        '01' => 'Jammu and Kashmir',
        '02' => 'Himachal Pradesh',
        '03' => 'Punjab',
        '04' => 'Chandigarh',
        '05' => 'Uttarakhand',
        '06' => 'Haryana',
        '07' => 'Delhi',
        '08' => 'Rajasthan',
        '09' => 'Uttar Pradesh',
        '10' => 'Bihar',
        '11' => 'Sikkim',
        '12' => 'Arunachal Pradesh',
        '13' => 'Nagaland',
        '14' => 'Manipur',
        '15' => 'Mizoram',
        '16' => 'Tripura',
        '17' => 'Meghalaya',
        '18' => 'Assam',
        '19' => 'West Bengal',
        '20' => 'Jharkhand',
        '21' => 'Odisha',
        '22' => 'Chhattisgarh',
        '23' => 'Madhya Pradesh',
        '24' => 'Gujarat',
        '26' => 'Dadra and Nagar Haveli and Daman and Diu',
        '27' => 'Maharashtra',
        '29' => 'Karnataka',
        '30' => 'Goa',
        '31' => 'Lakshadweep',
        '32' => 'Kerala',
        '33' => 'Tamil Nadu',
        '34' => 'Puducherry',
        '35' => 'Andaman and Nicobar Islands',
        '36' => 'Telangana',
        '37' => 'Andhra Pradesh',
        '38' => 'Ladakh',
        '97' => 'Other Territory',
    ];

    public $turfId = null;
    public $turfName = '';

    // Legal & Company Details
    public $company_name = '';
    public $company_email = '';
    public $company_phone = '';

    // Registered Business Address
    public $address = '';
    public $city = '';
    public $state = '';
    public $state_code = '';
    public $country = 'India';
    public $pincode = '';

    // Tax & Regulatory Identification
    public $is_gst_billing_active = false;
    public $gst_pricing_type = 'included'; // 'included' or 'excluded'
    public $gst_percentage = 18.00;
    public $gst_number = '';

    #[On('global-context-updated')]
    public function refreshTaxation()
    {
        $this->loadTaxation();
    }

    public function mount()
    {
        $this->loadTaxation();
    }

    public function loadTaxation()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;

        if ($activeTurfId) {
            $turf = Turf::manageable()->find($activeTurfId);
        }

        if (!$turf) {
            $turf = Turf::manageable()->first();
            if ($turf) {
                session(['active_turf_id' => $turf->id]);
            }
        }

        if ($turf) {
            $this->turfId = $turf->id;
            $this->turfName = $turf->name;

            $setting = TurfSetting::firstOrCreate(
                ['turf_id' => $turf->id],
                [
                    'country' => 'India',
                    'is_gst_billing_active' => false,
                    'gst_pricing_type' => 'included',
                    'gst_percentage' => 18.00,
                ]
            );

            $this->company_name = $setting->company_name ?? '';
            $this->company_email = $setting->company_email ?? '';
            $this->company_phone = $setting->company_phone ?? '';
            $this->address = $setting->address ?? '';
            $this->city = $setting->city ?? '';
            $this->state = $setting->state ?? '';
            $this->state_code = $setting->state_code ?? '';
            $this->country = $setting->country ?: 'India';
            $this->pincode = $setting->pincode ?? '';
            $this->is_gst_billing_active = (bool)($setting->is_gst_billing_active ?? false);
            $this->gst_pricing_type = $setting->gst_pricing_type ?: 'included';
            $this->gst_percentage = (float)($setting->gst_percentage ?? 18.00);
            $this->gst_number = $setting->gst_number ?? '';

            if (empty($this->state_code) && !empty($this->state)) {
                $code = array_search($this->state, self::INDIAN_STATES);
                if ($code !== false) {
                    $this->state_code = (string)$code;
                }
            }

            return;
        }

        $this->turfId = null;
        $this->turfName = '';
    }

    public function updatedState($value)
    {
        if (empty($value)) {
            $this->state_code = '';
            return;
        }

        $code = array_search($value, self::INDIAN_STATES);
        if ($code !== false) {
            $this->state_code = (string)$code;
        } elseif (isset(self::INDIAN_STATES[$value])) {
            $this->state = self::INDIAN_STATES[$value];
            $this->state_code = (string)$value;
        }
    }

    public function updatedGstNumber()
    {
        $this->gst_number = strtoupper(trim((string)$this->gst_number));

        // If state is not selected yet and GSTIN starts with a valid state code, auto-select state
        if (empty($this->state) && strlen($this->gst_number) >= 2) {
            $prefix = substr($this->gst_number, 0, 2);
            if (isset(self::INDIAN_STATES[$prefix])) {
                $this->state = self::INDIAN_STATES[$prefix];
                $this->state_code = $prefix;
            }
        }
    }

    public function save()
    {
        if (!$this->turfId) {
            return;
        }

        $turf = Turf::manageable()->findOrFail($this->turfId);

        // Ensure state_code is synchronized with selected state
        if (!empty($this->state)) {
            $code = array_search($this->state, self::INDIAN_STATES);
            if ($code !== false) {
                $this->state_code = (string)$code;
            }
        } else {
            $this->state_code = null;
        }

        $validated = $this->validate([
            'company_name' => 'nullable|string|max:150',
            'company_email' => 'nullable|email|max:150',
            'company_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'state_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:15',
            'is_gst_billing_active' => 'required|boolean',
            'gst_pricing_type' => 'required|in:included,excluded',
            'gst_percentage' => 'required|numeric|min:0|max:100',
            'gst_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i'],
        ], [
            'gst_number.regex' => 'Please enter a valid 15-character GSTIN format (e.g., 27AAAAA0000A1Z5).',
        ]);

        if (!empty($validated['gst_number'])) {
            $validated['gst_number'] = strtoupper(trim($validated['gst_number']));
        }

        TurfSetting::updateOrCreate(
            ['turf_id' => $turf->id],
            $validated
        );

        session()->flash('status', 'Taxation & legal details saved successfully.');
    }
}; ?>

<div class="w-full space-y-6">

    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl text-xs font-bold tracking-wide flex items-center gap-3 shadow-2xs">
            <div class="w-6 h-6 rounded-full bg-emerald-500/10 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (!$turfId)
        <!-- Unselected Turf Empty State -->
        <div class="bg-white p-12 sm:p-16 rounded-3xl border border-gray-100 shadow-sm text-center">
            <div class="h-16 w-16 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 shadow-2xs">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
            <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure its taxation details.') }}</p>
        </div>
    @else
        <!-- Page Header -->
        <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-[10px] font-bold tracking-wider uppercase mb-1.5 border border-indigo-100/60">
                    <svg class="w-3 h-3 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                    <span>{{ __('Legal & Compliance') }}</span>
                    @if ($turfName)
                        <span class="text-indigo-400">•</span>
                        <span class="text-indigo-800 font-extrabold">{{ $turfName }}</span>
                    @endif
                </div>
                <h2 class="text-xl font-extrabold text-gray-900 tracking-tight">{{ __('Taxation & Legal Details') }}</h2>
                <p class="text-xs text-gray-500 mt-1">{{ __('Configure registered business identity, GST billing options, state code, and address stored for this turf.') }}</p>
            </div>
            <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 text-white font-bold text-xs tracking-wider uppercase transition shadow-sm cursor-pointer shrink-0">
                <svg wire:loading class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span>{{ __('Save Changes') }}</span>
            </button>
        </div>

        <!-- Form Cards Stack -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left 2 Cols: Configuration Cards -->
            <div class="lg:col-span-2 space-y-6">

                <!-- 1. Company Legal Identity Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg shadow-2xs">
                            🏢
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Company Legal Identity') }}</h3>
                            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Official company entity name and communication contact channels') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Company Name -->
                        <div>
                            <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Company / Legal Business Name') }}</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </span>
                                <input type="text" wire:model.live.debounce.300ms="company_name" placeholder="e.g. Apex Sports Arena Private Limited" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">{{ __('This official legal name will be printed on customer booking invoices and receipts.') }}</p>
                            @error('company_name') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <!-- Email & Phone in 2 cols -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Company Email -->
                            <div>
                                <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Company Email') }}</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </span>
                                    <input type="email" wire:model.live.debounce.300ms="company_email" placeholder="billing@apexsports.com" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                                </div>
                                @error('company_email') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- Company Phone -->
                            <div>
                                <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Company Phone') }}</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </span>
                                    <input type="text" wire:model.live.debounce.300ms="company_phone" placeholder="+91 98765 43210" class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                                </div>
                                @error('company_phone') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Tax Identification & GST Billing Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-lg shadow-2xs">
                                🧾
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Tax & GST Identification') }}</h3>
                                <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Goods & Services Tax configuration and statutory billing options') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <!-- Option 1: Enable / Disable GST Billing Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <div class="flex items-center gap-2">
                                    <label class="text-xs font-bold text-gray-900 cursor-pointer">{{ __('GST Billing') }}</label>
                                    @if ($is_gst_billing_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800">
                                            {{ __('Enabled') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-gray-200 text-gray-600">
                                            {{ __('Disabled') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Enable statutory GST calculation, breakdown, and tax invoices for customer slot bookings') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_gst_billing_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                            </label>
                        </div>

                        <!-- Option 2: Included / Excluded option when GST billing is active -->
                        @if ($is_gst_billing_active)
                            <div class="p-5 sm:p-6 bg-emerald-50/40 rounded-2xl border border-emerald-200/70 space-y-5 transition-all">
                                <div>
                                    <label class="block text-xs font-bold text-gray-900 mb-1">{{ __('GST in Turf Slot Pricing') }}</label>
                                    <p class="text-[11px] text-gray-500 mb-3">{{ __('Specify whether your configured turf slot pricing already includes GST or if GST should be added on top') }}</p>
                                    
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                        <!-- Included Option -->
                                        <label class="relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition {{ $gst_pricing_type === 'included' ? 'border-emerald-600 bg-white shadow-xs ring-2 ring-emerald-500/10' : 'border-gray-200 bg-white/80 hover:border-gray-300' }}">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <div class="flex items-center gap-2">
                                                    <input type="radio" wire:model.live="gst_pricing_type" value="included" class="text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-bold text-gray-900">{{ __('Included (Inclusive)') }}</span>
                                                </div>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $gst_pricing_type === 'included' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ __('Most Common') }}</span>
                                            </div>
                                            <p class="text-[11px] text-gray-500 leading-relaxed pl-6">
                                                {{ __('Slot prices already include GST. The customer pays the exact displayed price, and GST is calculated within it on receipts.') }}
                                            </p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 pl-6 text-[10px] font-mono font-bold text-emerald-700">
                                                {{ __('e.g. ₹1,000 slot = ₹847.46 base + ₹152.54 GST (18%)') }}
                                            </div>
                                        </label>

                                        <!-- Excluded Option -->
                                        <label class="relative flex flex-col p-4 rounded-2xl border-2 cursor-pointer transition {{ $gst_pricing_type === 'excluded' ? 'border-emerald-600 bg-white shadow-xs ring-2 ring-emerald-500/10' : 'border-gray-200 bg-white/80 hover:border-gray-300' }}">
                                            <div class="flex items-center justify-between mb-1.5">
                                                <div class="flex items-center gap-2">
                                                    <input type="radio" wire:model.live="gst_pricing_type" value="excluded" class="text-emerald-600 focus:ring-emerald-500">
                                                    <span class="text-xs font-bold text-gray-900">{{ __('Excluded (Exclusive)') }}</span>
                                                </div>
                                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $gst_pricing_type === 'excluded' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-500' }}">{{ __('Added at Checkout') }}</span>
                                            </div>
                                            <p class="text-[11px] text-gray-500 leading-relaxed pl-6">
                                                {{ __('GST is added additionally on top of the slot price when the customer proceeds to checkout and pays.') }}
                                            </p>
                                            <div class="mt-2.5 pt-2 border-t border-gray-100 pl-6 text-[10px] font-mono font-bold text-indigo-700">
                                                {{ __('e.g. ₹1,000 slot + ₹180 GST (18%) = ₹1,180 total') }}
                                            </div>
                                        </label>
                                    </div>
                                    @error('gst_pricing_type') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                                </div>

                                <!-- GST Rate & SAC info -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-900 mb-1.5">{{ __('GST Rate Percentage (%)') }}</label>
                                        <div class="relative">
                                            <input type="number" step="0.01" min="0" max="100" wire:model.live.debounce.300ms="gst_percentage" placeholder="18.00" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 focus:ring-4 focus:ring-emerald-500/10 focus:border-emerald-500 transition shadow-2xs">
                                            <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-gray-400 font-bold text-xs">
                                                %
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">{{ __('Standard rate for sports facility rental SAC 999652 is 18%.') }}</p>
                                        @error('gst_percentage') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                                    </div>

                                    <div>
                                        <label class="block text-xs font-bold text-gray-900 mb-1.5">{{ __('SAC Service Code') }}</label>
                                        <div class="w-full px-4 py-2.5 rounded-xl border border-dashed border-gray-300 bg-gray-50/80 text-xs font-mono font-bold text-gray-700 flex items-center justify-between">
                                            <span>999652</span>
                                            <span class="font-sans font-normal text-gray-500 text-[11px]">{{ __('Sports Facility Rental') }}</span>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-1">{{ __('Statutory service accounting code stamped on tax invoices.') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- GST Number Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-gray-800">{{ __('GST Number (GSTIN)') }}</label>
                                @if (!empty($gst_number) && preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_number))
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ __('Valid GSTIN Format') }}
                                    </span>
                                @elseif (!empty($gst_number))
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                        {{ __('15-character GSTIN') }}
                                    </span>
                                @endif
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400 font-mono font-bold text-xs">
                                    #
                                </span>
                                <input type="text" wire:model.live.debounce.300ms="gst_number" placeholder="27AAAAA0000A1Z5" maxlength="15" class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-mono font-bold uppercase tracking-wider text-gray-900 placeholder:text-gray-400 placeholder:font-sans placeholder:font-normal focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1">{{ __('15-digit GSTIN format (e.g. 27AAAAA0000A1Z5). Leave empty if not registered for GST.') }}</p>
                            @error('gst_number') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- 3. Registered Business Address Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-lg shadow-2xs">
                            📍
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Registered Business Address') }}</h3>
                            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Address details where the turf entity is legally domiciled') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Address Line -->
                        <div>
                            <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Street / Facility Address') }}</label>
                            <textarea wire:model.live.debounce.300ms="address" rows="3" placeholder="Plot No. 42, Turf Sports Complex, Opp. Central Park, Link Road" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs"></textarea>
                            @error('address') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                        </div>

                        <!-- City & State -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('City') }}</label>
                                <input type="text" wire:model.live.debounce.300ms="city" placeholder="e.g. Mumbai" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                                @error('city') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <!-- State with Select List & Automatic State Code -->
                            <div>
                                <div class="flex items-center justify-between mb-1.5">
                                    <label class="block text-xs font-bold text-gray-800">{{ __('State') }}</label>
                                    @if ($state_code)
                                        <span class="inline-flex items-center gap-1 text-[10px] font-mono font-extrabold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100">
                                            <span class="text-indigo-400 font-sans font-medium text-[9px] uppercase tracking-wider">{{ __('State Code:') }}</span> {{ $state_code }}
                                        </span>
                                    @endif
                                </div>
                                <select wire:model.live="state" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs cursor-pointer">
                                    <option value="">{{ __('-- Select State --') }}</option>
                                    @foreach (self::INDIAN_STATES as $code => $name)
                                        <option value="{{ $name }}">{{ $name }} ({{ $code }})</option>
                                    @endforeach
                                </select>
                                <p class="text-[11px] text-gray-400 mt-1">
                                    @if ($state_code)
                                        {{ __('Official GST State Code :code is automatically stored.', ['code' => $state_code]) }}
                                    @else
                                        {{ __('Select your state; statutory state code is mapped automatically.') }}
                                    @endif
                                </p>
                                @error('state') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Country & Pincode -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Country') }}</label>
                                <input type="text" wire:model.live.debounce.300ms="country" placeholder="India" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                                @error('country') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Pincode / Postal Code') }}</label>
                                <input type="text" wire:model.live.debounce.300ms="pincode" placeholder="e.g. 400053" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                                @error('pincode') <span class="block text-[10px] text-rose-600 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right Col: Live Invoice Preview & Quick Summary -->
            <div class="space-y-6">

                <!-- Live Tax Invoice Header Preview Card -->
                <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-3xl border border-slate-800 shadow-lg relative overflow-hidden">
                    <div class="absolute -right-10 -bottom-10 w-36 h-36 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

                    <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-400 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full {{ $is_gst_billing_active ? 'bg-emerald-400' : 'bg-slate-500' }} animate-pulse"></span>
                            {{ __('Invoice Preview') }}
                        </span>
                        @if ($is_gst_billing_active)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                {{ __('Tax Invoice (GST)') }}
                            </span>
                        @else
                            <span class="text-[10px] font-medium text-slate-400">
                                {{ __('Standard Receipt') }}
                            </span>
                        @endif
                    </div>

                    <div class="space-y-3">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ __('Issued By') }}</div>
                            <div class="text-sm font-extrabold text-white mt-0.5">
                                {{ $company_name ?: ($turfName ?: __('Company / Turf Name')) }}
                            </div>
                        </div>

                        <!-- GST & Status Badge -->
                        @if ($is_gst_billing_active)
                            <div class="bg-emerald-950/40 border border-emerald-500/30 rounded-xl p-3 space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[9px] uppercase font-bold text-emerald-300 tracking-wider">{{ __('GSTIN / Tax ID') }}</span>
                                    <span class="text-[9px] font-extrabold uppercase px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-200">
                                        {{ $gst_pricing_type === 'included' ? __('GST Included') : __('GST Excluded') }}
                                    </span>
                                </div>
                                <div class="text-xs font-mono font-extrabold text-emerald-400 tracking-wider">
                                    {{ $gst_number ?: __('GSTIN Pending') }}
                                </div>
                                <div class="text-[10px] text-emerald-300/80">
                                    {{ __('Rate: :rate% (SAC :sac)', ['rate' => number_format((float)$gst_percentage, 2), 'sac' => '999652']) }}
                                </div>
                            </div>
                        @else
                            <div class="bg-white/5 border border-white/10 rounded-xl p-2.5 text-[11px] text-slate-400 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                <span>{{ __('GST Billing is currently disabled for this turf.') }}</span>
                            </div>
                        @endif

                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ __('Operating Address') }}</div>
                            <div class="text-xs text-slate-300 font-medium leading-relaxed mt-0.5">
                                @if ($address || $city || $state || $pincode)
                                    {{ $address ?: '' }}
                                    @if ($city || $state)
                                        <br>{{ implode(', ', array_filter([$city, $state ? ($state_code ? "$state (Code: $state_code)" : $state) : null])) }}
                                    @endif
                                    @if ($pincode || $country)
                                        <br>{{ implode(' - ', array_filter([$country ?: 'India', $pincode])) }}
                                    @endif
                                @else
                                    <span class="text-slate-500 italic">{{ __('Address not configured yet') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="border-t border-slate-800/80 pt-3 grid grid-cols-1 gap-1.5 text-[11px] text-slate-300">
                            @if ($company_email)
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-500">✉️</span>
                                    <span class="truncate">{{ $company_email }}</span>
                                </div>
                            @endif
                            @if ($company_phone)
                                <div class="flex items-center gap-2">
                                    <span class="text-slate-500">📞</span>
                                    <span>{{ $company_phone }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-800 text-[10px] text-slate-400 leading-normal">
                        {{ __('This data is automatically stamped on tax invoices, booking confirmations, and customer receipts.') }}
                    </div>
                </div>

                <!-- Guidance & Help Box -->
                <div class="bg-indigo-50/60 border border-indigo-100 rounded-3xl p-6 space-y-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-7 h-7 rounded-xl bg-indigo-600 text-white flex items-center justify-center text-xs font-bold">
                            💡
                        </div>
                        <h4 class="text-xs font-bold text-indigo-950 uppercase tracking-wider">{{ __('State & GST Guide') }}</h4>
                    </div>
                    <ul class="text-xs text-indigo-900/80 space-y-2 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="text-indigo-600 font-bold">•</span>
                            <span><strong>{{ __('Automatic State Code:') }}</strong> {{ __('Selecting your state automatically assigns the statutory 2-digit GST state code (e.g. Maharashtra = 27, Delhi = 07).') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-indigo-600 font-bold">•</span>
                            <span><strong>{{ __('Intra-State vs Inter-State:') }}</strong> {{ __('The state code determines CGST+SGST (intra-state) vs IGST (inter-state) on customer booking invoices.') }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Mobile & Desktop Bottom Save Action Card -->
                <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-xs flex items-center justify-between gap-3">
                    <div class="text-xs text-gray-500">
                        {{ __('Ready to update records?') }}
                    </div>
                    <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 text-white font-bold text-xs tracking-wider uppercase transition shadow-sm cursor-pointer">
                        <svg wire:loading class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ __('Save') }}</span>
                    </button>
                </div>

            </div>

        </div>

    @endif

</div>
