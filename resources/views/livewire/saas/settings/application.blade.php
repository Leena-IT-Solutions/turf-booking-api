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
                <div>
                    <h2 class="text-xl font-bold text-gray-900">{{ __('Application & Business Settings') }}</h2>
                    <p class="text-xs text-gray-500 mt-1.5">{{ __('Control core application search radius, booking slot limits, maintenance mode, and commission/payout parameters.') }}</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-wider shadow transition duration-150 cursor-pointer">
                        {{ __('Save Application Settings') }}
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

            <!-- Application Settings Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <span>{{ __('Core Application Preferences') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('Search boundaries and slot booking rules applied to the player mobile app.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-start">
                    <!-- Turf Search Km -->
                    <div>
                        <x-input-label for="turfSearchKm" :value="__('Turf Search Radius (km)')" />
                        <x-text-input wire:model.live.debounce.250ms="turf_search_km" id="turfSearchKm" type="number" min="1" max="500" class="mt-1.5 block w-full" placeholder="10" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Default search radius around player location to find available turfs.') }}
                        </span>
                        <x-input-error :messages="$errors->get('turf_search_km')" class="mt-2" />
                    </div>

                    <!-- Minimum Slots Booking Number -->
                    <div>
                        <x-input-label for="minSlotsBooking" :value="__('Minimum Slots per Booking')" />
                        <x-text-input wire:model.live.debounce.250ms="min_slots_booking" id="minSlotsBooking" type="number" min="1" max="50" class="mt-1.5 block w-full" placeholder="2" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Enforce consecutive slot requirement (e.g. 2 slots = 1 hour).') }}
                        </span>
                        <x-input-error :messages="$errors->get('min_slots_booking')" class="mt-2" />
                    </div>

                    <!-- Maintenance Mode Switch -->
                    <div class="bg-gray-50/80 rounded-2xl p-4 border border-gray-100 flex items-center justify-between">
                        <div>
                            <span class="text-xs font-bold text-gray-800 block">{{ __('Maintenance Mode') }}</span>
                            <span class="text-[10px] text-gray-400 font-semibold block mt-0.5">{{ __('Temporarily suspend public app bookings.') }}</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" wire:model.live="is_maintenance_mode" class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Commission & Payout Settings Card -->
            <div class="bg-white shadow-sm rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div>
                    <h3 class="text-xs font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ __('Commission, Fees & Payout Parameters') }}</span>
                    </h3>
                    <p class="text-[11px] text-gray-400 font-semibold mt-1">{{ __('Configure SaaS revenue deductions, automated payout processing schedules, and debt guardrails.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Commission Percentage % -->
                    <div>
                        <x-input-label for="commissionPerc" :value="__('Platform Commission (%)')" />
                        <x-text-input wire:model.live.debounce.250ms="commission_percentage" id="commissionPerc" type="number" step="0.01" min="0" max="100" class="mt-1.5 block w-full" placeholder="7.00" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Default percentage deducted on completed slot bookings.') }}
                        </span>
                        <x-input-error :messages="$errors->get('commission_percentage')" class="mt-2" />
                    </div>

                    <!-- Payment Gateway Percentage % -->
                    <div>
                        <x-input-label for="gatewayPerc" :value="__('Payment Gateway Fee (%)')" />
                        <x-text-input wire:model.live.debounce.250ms="payment_gateway_percentage" id="gatewayPerc" type="number" step="0.01" min="0" max="100" class="mt-1.5 block w-full" placeholder="2.00" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Payment gateway processing charge rate.') }}
                        </span>
                        <x-input-error :messages="$errors->get('payment_gateway_percentage')" class="mt-2" />
                    </div>

                    <!-- Payout Processing Hours -->
                    <div>
                        <x-input-label for="payoutHours" :value="__('Payout Processing Window (Hours)')" />
                        <x-text-input wire:model.live.debounce.250ms="payout_hours" id="payoutHours" type="number" min="0" class="mt-1.5 block w-full" placeholder="24" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Minimum hours after booking completion before payout release.') }}
                        </span>
                        <x-input-error :messages="$errors->get('payout_hours')" class="mt-2" />
                    </div>

                    <!-- Payout Charges (₹) -->
                    <div>
                        <x-input-label for="payoutCharges" :value="__('Payout Fixed Fee (₹)')" />
                        <x-text-input wire:model.live.debounce.250ms="payout_charges" id="payoutCharges" type="number" step="0.01" min="0" class="mt-1.5 block w-full" placeholder="40.00" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Fixed processing fee charged per bank payout transfer.') }}
                        </span>
                        <x-input-error :messages="$errors->get('payout_charges')" class="mt-2" />
                    </div>

                    <!-- Max Commission Due (₹) -->
                    <div>
                        <x-input-label for="maxCommissionDue" :value="__('Max Commission Due Limit (₹)')" />
                        <x-text-input wire:model.live.debounce.250ms="max_commission_due" id="maxCommissionDue" type="number" step="0.01" min="0" class="mt-1.5 block w-full" placeholder="2000.00" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Offline booking guardrail threshold before turf account lockout.') }}
                        </span>
                        <x-input-error :messages="$errors->get('max_commission_due')" class="mt-2" />
                    </div>

                    <!-- Commission Due Grace Days -->
                    <div>
                        <x-input-label for="graceDays" :value="__('Commission Due Grace Period (Days)')" />
                        <x-text-input wire:model.live.debounce.250ms="commission_due_grace_days" id="graceDays" type="number" min="0" class="mt-1.5 block w-full" placeholder="7" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1.5 block">
                            {{ __('Days permitted to clear due balance before booking lockout.') }}
                        </span>
                        <x-input-error :messages="$errors->get('commission_due_grace_days')" class="mt-2" />
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
