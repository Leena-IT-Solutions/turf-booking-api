<?php

use App\Models\SaasSetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $turf_search_km = 10;
    public $min_slots_booking = 2;
    public $is_maintenance_mode = false;
    public $commission_percentage = 7.00;
    public $payment_gateway_percentage = 2.00;
    public $payout_hours = 24;
    public $payout_charges = 40.00;
    public $max_commission_due = 2000.00;
    public $commission_due_grace_days = 7;

    public function mount()
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
            'contact_email' => 'sandeep198558@gmail.com',
            'contact_mobile' => '9664588677',
            'address' => 'Mumbai, India',
        ]);

        $this->turf_search_km = $setting->turf_search_km ?? 10;
        $this->min_slots_booking = $setting->min_slots_booking ?? 2;
        $this->is_maintenance_mode = (bool) $setting->is_maintenance_mode;
        $this->commission_percentage = (float) ($setting->commission_percentage ?? 7.00);
        $this->payment_gateway_percentage = (float) ($setting->payment_gateway_percentage ?? 2.00);
        $this->payout_hours = (int) ($setting->payout_hours ?? 24);
        $this->payout_charges = (float) ($setting->payout_charges ?? 40.00);
        $this->max_commission_due = (float) ($setting->max_commission_due ?? 2000.00);
        $this->commission_due_grace_days = (int) ($setting->commission_due_grace_days ?? 7);
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, [
            'turf_search_km' => 'required|integer|min:1|max:500',
            'min_slots_booking' => 'required|integer|min:1|max:50',
            'is_maintenance_mode' => 'boolean',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'payment_gateway_percentage' => 'required|numeric|min:0|max:100',
            'payout_hours' => 'required|integer|min:0',
            'payout_charges' => 'required|numeric|min:0',
            'max_commission_due' => 'required|numeric|min:0',
            'commission_due_grace_days' => 'required|integer|min:0',
        ]);
    }

    public function saveSettings()
    {
        $this->validate([
            'turf_search_km' => 'required|integer|min:1|max:500',
            'min_slots_booking' => 'required|integer|min:1|max:50',
            'is_maintenance_mode' => 'boolean',
            'commission_percentage' => 'required|numeric|min:0|max:100',
            'payment_gateway_percentage' => 'required|numeric|min:0|max:100',
            'payout_hours' => 'required|integer|min:0',
            'payout_charges' => 'required|numeric|min:0',
            'max_commission_due' => 'required|numeric|min:0',
            'commission_due_grace_days' => 'required|integer|min:0',
        ]);

        $setting = SaasSetting::first() ?? new SaasSetting();

        $data = [
            'turf_search_km' => $this->turf_search_km,
            'min_slots_booking' => $this->min_slots_booking,
            'is_maintenance_mode' => $this->is_maintenance_mode,
            'commission_percentage' => $this->commission_percentage,
            'payment_gateway_percentage' => $this->payment_gateway_percentage,
            'payout_hours' => $this->payout_hours,
            'payout_charges' => $this->payout_charges,
            'max_commission_due' => $this->max_commission_due,
            'commission_due_grace_days' => $this->commission_due_grace_days,
        ];

        $setting->fill($data)->save();

        session()->flash('status', __('Application, commission & payout settings updated successfully.'));
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Top Tab Bar -->
        @include('livewire.saas.settings.tabs', ['active' => 'application'])

        <form wire:submit="saveSettings" class="space-y-6">
            <!-- Header Card -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Application & Business Settings') }}</h2>
                        <p class="text-xs text-gray-500 mt-1">{{ __('Control core application search radius, booking slot limits, maintenance mode, and commission/payout parameters.') }}</p>
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
                        <span>{{ __('Save Settings') }}</span>
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

            @if ($is_maintenance_mode)
                <div class="bg-amber-50 border border-amber-100 text-amber-800 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ __('Warning: Maintenance mode is currently active. User mobile app operations and front-end booking options might be restricted.') }}</span>
                </div>
            @endif

            <!-- Application Settings Card (Left Label - Right Field Layout) -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Core Application Preferences') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Search boundaries and slot booking rules applied to the player mobile app.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- Row 1: Turf Search Km -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="turfSearchKm" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Turf Search Radius') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Default search radius around player location to find available turfs.') }}
                            </p>
                            <x-input-error :messages="$errors->get('turf_search_km')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <input wire:model.live.debounce.250ms="turf_search_km" id="turfSearchKm" type="number" min="1" max="500" 
                                    class="w-full pr-12 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="10" />
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">km</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Minimum Slots Booking -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="minSlotsBooking" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Minimum Slots per Booking') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Enforce consecutive slot requirement for single bookings (e.g. 2 slots = 1 hour).') }}
                            </p>
                            <x-input-error :messages="$errors->get('min_slots_booking')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <input wire:model.live.debounce.250ms="min_slots_booking" id="minSlotsBooking" type="number" min="1" max="50" 
                                    class="w-full pr-14 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="2" />
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">slots</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Maintenance Mode -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="maintenanceToggle" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Maintenance Mode') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Temporarily suspend public app bookings and customer checkouts while keeping administrator portal active.') }}
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center">
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="maintenanceToggle" type="checkbox" wire:model.live="is_maintenance_mode" class="sr-only peer">
                                <div class="w-12 h-6.5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commission & Payout Settings Card (Left Label - Right Field Layout) -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Commission, Fees & Payout Parameters') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Configure SaaS revenue deductions, automated payout processing schedules, and debt guardrails.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- Row 1: Commission Percentage -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="commissionPerc" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Platform Commission') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Default percentage revenue deducted on completed slot bookings.') }}
                            </p>
                            <x-input-error :messages="$errors->get('commission_percentage')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <input wire:model.live.debounce.250ms="commission_percentage" id="commissionPerc" type="number" step="0.01" min="0" max="100" 
                                    class="w-full pr-10 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="7.00" />
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Payment Gateway Percentage -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="gatewayPerc" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Payment Gateway Fee') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Payment gateway processing charge rate deducted per transaction.') }}
                            </p>
                            <x-input-error :messages="$errors->get('payment_gateway_percentage')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <input wire:model.live.debounce.250ms="payment_gateway_percentage" id="gatewayPerc" type="number" step="0.01" min="0" max="100" 
                                    class="w-full pr-10 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="2.00" />
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Payout Processing Hours -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="payoutHours" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Payout Processing Window') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Minimum hours after booking completion before payout release.') }}
                            </p>
                            <x-input-error :messages="$errors->get('payout_hours')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <input wire:model.live.debounce.250ms="payout_hours" id="payoutHours" type="number" min="0" 
                                    class="w-full pr-14 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="24" />
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">hours</span>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Payout Fixed Fee -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="payoutCharges" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Payout Fixed Fee') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Fixed processing fee charged per bank payout transfer.') }}
                            </p>
                            <x-input-error :messages="$errors->get('payout_charges')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">₹</span>
                                <input wire:model.live.debounce.250ms="payout_charges" id="payoutCharges" type="number" step="0.01" min="0" 
                                    class="w-full pl-8 pr-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="40.00" />
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: Max Commission Due -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="maxCommissionDue" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Max Commission Due Limit') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Offline booking guardrail threshold before turf account lockout.') }}
                            </p>
                            <x-input-error :messages="$errors->get('max_commission_due')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">₹</span>
                                <input wire:model.live.debounce.250ms="max_commission_due" id="maxCommissionDue" type="number" step="0.01" min="0" 
                                    class="w-full pl-8 pr-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="2000.00" />
                            </div>
                        </div>
                    </div>

                    <!-- Row 6: Commission Due Grace Days -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="graceDays" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Commission Due Grace Period') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Days permitted to clear due balance before booking lockout.') }}
                            </p>
                            <x-input-error :messages="$errors->get('commission_due_grace_days')" class="mt-1" />
                        </div>
                        <div class="shrink-0 flex items-center">
                            <div class="relative w-full sm:w-44">
                                <input wire:model.live.debounce.250ms="commission_due_grace_days" id="graceDays" type="number" min="0" 
                                    class="w-full pr-14 pl-4 py-2.5 bg-gray-50/60 hover:bg-white focus:bg-white rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 text-right transition" 
                                    placeholder="7" />
                                <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
