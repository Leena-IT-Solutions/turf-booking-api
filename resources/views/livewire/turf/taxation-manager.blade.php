<?php

use App\Models\Turf;
use App\Models\TurfSetting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
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
    public $country = 'India';
    public $pincode = '';

    // Tax & Regulatory Identification
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
                ['country' => 'India']
            );

            $this->company_name = $setting->company_name ?? '';
            $this->company_email = $setting->company_email ?? '';
            $this->company_phone = $setting->company_phone ?? '';
            $this->address = $setting->address ?? '';
            $this->city = $setting->city ?? '';
            $this->state = $setting->state ?? '';
            $this->country = $setting->country ?: 'India';
            $this->pincode = $setting->pincode ?? '';
            $this->gst_number = $setting->gst_number ?? '';
            return;
        }

        $this->turfId = null;
        $this->turfName = '';
    }

    public function updatedGstNumber()
    {
        $this->gst_number = strtoupper(trim((string)$this->gst_number));
    }

    public function save()
    {
        if (!$this->turfId) {
            return;
        }

        $turf = Turf::manageable()->findOrFail($this->turfId);

        $validated = $this->validate([
            'company_name' => 'nullable|string|max:150',
            'company_email' => 'nullable|email|max:150',
            'company_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:15',
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
                <p class="text-xs text-gray-500 mt-1">{{ __('Configure registered business identity, GST number, and address stored for this turf.') }}</p>
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

                <!-- 2. Tax Identification Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-lg shadow-2xs">
                                🧾
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Tax & GST Identification') }}</h3>
                                <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Goods & Services Tax identification number for statutory invoices') }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold text-gray-800">{{ __('GST Number (GSTIN)') }}</label>
                                @if (!empty($gst_number) && preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i', $gst_number))
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full border border-emerald-200">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        Valid GSTIN Format
                                    </span>
                                @elseif (!empty($gst_number))
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                                        15-character GSTIN
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

                            <div>
                                <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('State') }}</label>
                                <input type="text" list="indian-states-list" wire:model.live.debounce.300ms="state" placeholder="e.g. Maharashtra" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 placeholder:text-gray-400 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs">
                                <datalist id="indian-states-list">
                                    <option value="Andhra Pradesh"></option>
                                    <option value="Arunachal Pradesh"></option>
                                    <option value="Assam"></option>
                                    <option value="Bihar"></option>
                                    <option value="Chhattisgarh"></option>
                                    <option value="Goa"></option>
                                    <option value="Gujarat"></option>
                                    <option value="Haryana"></option>
                                    <option value="Himachal Pradesh"></option>
                                    <option value="Jharkhand"></option>
                                    <option value="Karnataka"></option>
                                    <option value="Kerala"></option>
                                    <option value="Madhya Pradesh"></option>
                                    <option value="Maharashtra"></option>
                                    <option value="Manipur"></option>
                                    <option value="Meghalaya"></option>
                                    <option value="Mizoram"></option>
                                    <option value="Nagaland"></option>
                                    <option value="Odisha"></option>
                                    <option value="Punjab"></option>
                                    <option value="Rajasthan"></option>
                                    <option value="Sikkim"></option>
                                    <option value="Tamil Nadu"></option>
                                    <option value="Telangana"></option>
                                    <option value="Tripura"></option>
                                    <option value="Uttar Pradesh"></option>
                                    <option value="Uttarakhand"></option>
                                    <option value="West Bengal"></option>
                                    <option value="Delhi"></option>
                                </datalist>
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
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            {{ __('Invoice Preview') }}
                        </span>
                        <span class="text-[10px] text-slate-400">{{ __('Customer Receipt') }}</span>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ __('Issued By') }}</div>
                            <div class="text-sm font-extrabold text-white mt-0.5">
                                {{ $company_name ?: ($turfName ?: __('Company / Turf Name')) }}
                            </div>
                        </div>

                        @if ($gst_number)
                            <div class="bg-white/5 border border-white/10 rounded-xl p-2.5">
                                <div class="text-[9px] uppercase font-bold text-slate-400 tracking-wider">{{ __('GSTIN / Tax ID') }}</div>
                                <div class="text-xs font-mono font-extrabold text-emerald-400 tracking-wide mt-0.5">
                                    {{ $gst_number }}
                                </div>
                            </div>
                        @endif

                        <div>
                            <div class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">{{ __('Operating Address') }}</div>
                            <div class="text-xs text-slate-300 font-medium leading-relaxed mt-0.5">
                                @if ($address || $city || $state || $pincode)
                                    {{ $address ?: '' }}
                                    @if ($city || $state)
                                        <br>{{ implode(', ', array_filter([$city, $state])) }}
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
                        <h4 class="text-xs font-bold text-indigo-950 uppercase tracking-wider">{{ __('Taxation Note') }}</h4>
                    </div>
                    <ul class="text-xs text-indigo-900/80 space-y-2 leading-relaxed">
                        <li class="flex items-start gap-2">
                            <span class="text-indigo-600 font-bold">•</span>
                            <span>{{ __('Under Indian GST laws, services related to sports and turf slot booking are categorized under SAC code 999652.') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-indigo-600 font-bold">•</span>
                            <span>{{ __('Providing your GST number ensures compliant B2B invoicing when corporate teams book slots.') }}</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="text-indigo-600 font-bold">•</span>
                            <span>{{ __('If you operate multiple turfs, you can customize or keep distinct legal entities for each turf location.') }}</span>
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
