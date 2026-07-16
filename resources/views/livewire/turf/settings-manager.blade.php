<?php

use App\Models\Turf;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $turfId = null;

    // Payment settings
    public $is_online_payment_active = true;
    public $is_part_payment_active = false;
    public $is_pay_at_location_active = false;
    public $part_payment_type = 'percentage';
    public $part_payment_value = 50;

    // Booking & Window settings
    public $is_booking_open = true;
    public $booking_open_days = 90;
    public $is_manager_booking_active = true;

    // Cancellation settings
    public $is_cancellation_active = false;
    public $cancellation_hours = 48;
    public $cancellation_fee = 0;

    #[On('global-context-updated')]
    public function refreshSettings()
    {
        $this->loadSettings();
    }

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $activeTurfId = session('active_turf_id');
        if ($activeTurfId) {
            $turf = Turf::manageable()->find($activeTurfId);

            if ($turf) {
                $this->turfId = $turf->id;
                $this->is_online_payment_active = (bool)$turf->is_online_payment_active;
                $this->is_part_payment_active = (bool)$turf->is_part_payment_active;
                $this->is_pay_at_location_active = (bool)$turf->is_pay_at_location_active;
                $this->part_payment_type = $turf->part_payment_type;
                $this->part_payment_value = $turf->part_payment_value;

                $this->is_booking_open = (bool)$turf->is_booking_open;
                $this->booking_open_days = (int)$turf->booking_open_days;
                $this->is_manager_booking_active = (bool)$turf->is_manager_booking_active;

                $this->is_cancellation_active = (bool)$turf->is_cancellation_active;
                $this->cancellation_hours = (int)$turf->cancellation_hours;
                $this->cancellation_fee = $turf->cancellation_fee;
                return;
            }
        }

        $this->turfId = null;
    }

    public function save()
    {
        if (!$this->turfId) {
            return;
        }

        $turf = Turf::manageable()->findOrFail($this->turfId);

        $rules = [
            'is_online_payment_active' => 'required|boolean',
            'is_part_payment_active' => 'required|boolean',
            'is_pay_at_location_active' => 'required|boolean',
            'part_payment_type' => 'required|in:percentage,flat',
            'part_payment_value' => 'required|numeric|min:0',
            'is_booking_open' => 'required|boolean',
            'booking_open_days' => 'required|integer|in:30,60,90',
            'is_manager_booking_active' => 'required|boolean',
            'is_cancellation_active' => 'required|boolean',
            'cancellation_hours' => 'required|integer|min:0',
            'cancellation_fee' => 'required|numeric|min:0',
        ];

        if ($this->is_part_payment_active && $this->part_payment_type === 'percentage') {
            $rules['part_payment_value'] .= '|max:100';
        }

        $validated = $this->validate($rules);

        $turf->update($validated);

        session()->flash('status', 'Turf settings saved successfully.');
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">

        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (!$turfId)
            <!-- Unselected Turf Empty State -->
            <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-500 dark:text-amber-400 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 dark:border-amber-950/50">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure its settings.') }}</p>
            </div>
        @else
            <!-- Header section -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Turf Configuration Settings') }}</h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage payment policies, booking window parameters, and cancellation policies.') }}</p>
                </div>
                <button wire:click="save" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs tracking-wider uppercase transition shadow-sm">
                    {{ __('Save Settings') }}
                </button>
            </div>

            <!-- Settings cards stack -->
            <div class="grid grid-cols-1 gap-6">

                <!-- 1. Payment Settings Card -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
                    <div class="pb-4 border-b border-gray-50 dark:border-gray-700/40 flex items-center gap-2">
                        <span class="text-xl">💳</span>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Payment Settings') }}</h3>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Configure online payment options and deposits') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Online Payment Switch -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Online Payment') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Enable customers to pay online using integrated Razorpay gateways') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_online_payment_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Part Payment Switch -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Part Payment') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Allow booking slots by paying a deposit amount/percentage upfront') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_part_payment_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Collapsible Part Payment Fields -->
                        @if ($is_part_payment_active)
                            <div class="p-5 bg-indigo-50/20 dark:bg-indigo-950/10 rounded-2xl border border-indigo-100/50 dark:border-indigo-950/30 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-indigo-900 dark:text-indigo-400 uppercase tracking-wider mb-2">{{ __('Part Payment Booking Amount Type') }}</label>
                                    <select wire:model.live="part_payment_type" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="percentage">{{ __('Percentage (%)') }}</option>
                                        <option value="flat">{{ __('Flat Amount (₹)') }}</option>
                                    </select>
                                    @error('part_payment_type') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-indigo-900 dark:text-indigo-400 uppercase tracking-wider mb-2">{{ __('Booking Deposit Amount') }}</label>
                                    <div class="relative">
                                        <input wire:model="part_payment_value" type="number" step="0.01" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                        <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">
                                            {{ $part_payment_type === 'percentage' ? '%' : '₹' }}
                                        </span>
                                    </div>
                                    @error('part_payment_value') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <!-- Pay At Location Switch -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Pay At Location') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Allow booking slots and paying offline at the ground') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_pay_at_location_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Booking & Window Settings Card -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
                    <div class="pb-4 border-b border-gray-50 dark:border-gray-700/40 flex items-center gap-2">
                        <span class="text-xl">📅</span>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Booking & Window Settings') }}</h3>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Configure booking availability windows and manager roles') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Booking Open Switch -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Booking Open') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Enable/Disable slot bookings for customers entirely') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_booking_open" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Booking Open For Days -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Booking Availability Window') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Define how many days in advance slots can be booked') }}</span>
                            </div>
                            <div class="w-full sm:w-48">
                                <select wire:model="booking_open_days" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="30">{{ __('30 Days') }}</option>
                                    <option value="60">{{ __('60 Days') }}</option>
                                    <option value="90">{{ __('90 Days') }}</option>
                                </select>
                                @error('booking_open_days') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Manager Booking Switch -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Manager Booking') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Enable/Disable managers to manually book slots on behalf of customers') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_manager_booking_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 3. Cancellation Settings Card -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
                    <div class="pb-4 border-b border-gray-50 dark:border-gray-700/40 flex items-center gap-2">
                        <span class="text-xl">⚠️</span>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Cancellation Settings') }}</h3>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Configure customer cancellation limits and penalties') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Cancellation Switch -->
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900/30 rounded-2xl border border-gray-100 dark:border-gray-700/30">
                            <div>
                                <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Cancellation Option') }}</label>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ __('Enable customers to cancel bookings themselves from their dashboard') }}</span>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" wire:model.live="is_cancellation_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Collapsible Cancellation Fields -->
                        @if ($is_cancellation_active)
                            <div class="p-5 bg-rose-50/20 dark:bg-rose-950/10 rounded-2xl border border-rose-100/50 dark:border-rose-950/30 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-rose-900 dark:text-rose-400 uppercase tracking-wider mb-2">{{ __('Cancellation Window Hours') }}</label>
                                    <div class="relative">
                                        <input wire:model="cancellation_hours" type="number" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                        <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">{{ __('Hours') }}</span>
                                    </div>
                                    @error('cancellation_hours') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-rose-900 dark:text-rose-400 uppercase tracking-wider mb-2">{{ __('Cancellation Fee') }}</label>
                                    <div class="relative">
                                        <input wire:model="cancellation_fee" type="number" step="0.01" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-xs text-gray-900 dark:text-gray-100 focus:ring-indigo-500 focus:border-indigo-500">
                                        <span class="absolute inset-y-0 right-0 pr-4 flex items-center text-xs font-semibold text-gray-400 pointer-events-none">₹</span>
                                    </div>
                                    @error('cancellation_fee') <span class="block text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            <!-- Bottom Action Controls -->
            <div class="flex justify-end pt-4 border-t border-gray-100 dark:border-gray-700/40">
                <button wire:click="save" class="px-6 py-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs tracking-wider uppercase transition shadow-sm">
                    {{ __('Save All Configurations') }}
                </button>
            </div>
        @endif

    </div>
</div>
