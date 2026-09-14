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

    // Message Sharing settings
    public $share_message_template = '';

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
                $this->share_message_template = $turf->share_message_template ?? "*Booking Confirmed!*\n\n⚽ *Turf:* {turf_name}\n📅 *Date:* {booking_date}\n⏰ *Slots:* {slots}\n\n💳 *Payment Details:*\n• Total Amount: ₹{total_amount}\n• Paid Amount: ₹{paid_amount}\n• Balance Due: ₹{balance_amount}\n\nThank you for booking with us!";
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
            'share_message_template' => 'nullable|string',
        ];

        if ($this->is_part_payment_active && $this->part_payment_type === 'percentage') {
            $rules['part_payment_value'] .= '|max:100';
        }

        $validated = $this->validate($rules);

        $turf->update($validated);

        session()->flash('status', 'Turf settings saved successfully.');
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
            <div class="bg-white p-16 rounded-3xl border border-gray-100 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 shadow-2xs">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
                <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure its settings.') }}</p>
            </div>
        @else
            <!-- Header section -->
            <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-xl font-extrabold text-gray-900 tracking-tight">{{ __('Turf Configuration & Policies') }}</h2>
                    <p class="text-xs text-gray-500 mt-1">{{ __('Manage payment policies, customer booking windows, and cancellation rules.') }}</p>
                </div>
                <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs tracking-wider uppercase transition shadow-sm cursor-pointer">
                    <svg wire:loading class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ __('Save Settings') }}</span>
                </button>
            </div>

            <!-- Settings cards stack -->
            <div class="grid grid-cols-1 gap-6">

                <!-- 1. Payment Settings Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-lg">
                            💳
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Payment Settings') }}</h3>
                            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Configure online payment options, deposits, and offline collections') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Online Payment Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800 cursor-pointer">{{ __('Online Payment') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Enable customers to pay online using integrated payment gateways') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_online_payment_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Part Payment Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800 cursor-pointer">{{ __('Part Payment (Advance Deposit)') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Allow booking slots by paying a deposit amount or percentage upfront') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_part_payment_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Collapsible Part Payment Fields -->
                        @if ($is_part_payment_active)
                            <div class="p-5 sm:p-6 bg-indigo-50/40 rounded-2xl border border-indigo-200/70 grid grid-cols-1 md:grid-cols-2 gap-5 transition-all">
                                <div>
                                    <label class="block text-xs font-bold text-gray-900 mb-1">{{ __('Deposit Calculation Type') }}</label>
                                    <p class="text-[11px] text-gray-500 mb-2">{{ __('Choose between percentage of slot total or flat amount') }}</p>
                                    <select wire:model.live="part_payment_type" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs cursor-pointer">
                                        <option value="percentage">{{ __('Percentage of Slot Price (%)') }}</option>
                                        <option value="flat">{{ __('Flat Amount (₹)') }}</option>
                                    </select>
                                    @error('part_payment_type') <span class="block text-[10px] text-rose-600 mt-1.5 font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-900 mb-1">{{ __('Booking Deposit Requirement') }}</label>
                                    <p class="text-[11px] text-gray-500 mb-2">{{ __('Required advance deposit value to confirm the slot') }}</p>
                                    <div class="relative">
                                        @if ($part_payment_type === 'flat')
                                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">₹</span>
                                            <input wire:model="part_payment_value" type="number" step="0.01" min="0" 
                                                class="w-full pl-8 pr-4 py-2.5 bg-white rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 transition shadow-2xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                                placeholder="100.00" />
                                        @else
                                            <input wire:model="part_payment_value" type="number" step="0.01" min="0" max="100" 
                                                class="w-full pl-4 pr-10 py-2.5 bg-white rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 text-sm font-semibold text-gray-900 transition shadow-2xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                                placeholder="50.00" />
                                            <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">%</span>
                                        @endif
                                    </div>
                                    @error('part_payment_value') <span class="block text-[10px] text-rose-600 mt-1.5 font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif

                        <!-- Pay At Location Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800 cursor-pointer">{{ __('Pay At Location') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Allow booking slots with payment collected in cash/UPI at the ground') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_pay_at_location_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Booking & Window Settings Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-lg">
                            📅
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Booking & Window Settings') }}</h3>
                            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Configure booking availability windows and manager roles') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Booking Open Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800 cursor-pointer">{{ __('Customer Booking Open') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Enable or pause customer slot bookings for this turf across apps and web') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_booking_open" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Booking Open For Days -->
                        <div class="p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800">{{ __('Booking Availability Window') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Define how many days in advance customers can view and book slots') }}</p>
                            </div>
                            <div class="w-full sm:w-48 shrink-0">
                                <select wire:model="booking_open_days" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 bg-white text-xs font-semibold text-gray-900 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 transition shadow-2xs cursor-pointer">
                                    <option value="30">{{ __('30 Days Ahead') }}</option>
                                    <option value="60">{{ __('60 Days Ahead') }}</option>
                                    <option value="90">{{ __('90 Days Ahead') }}</option>
                                </select>
                                @error('booking_open_days') <span class="block text-[10px] text-rose-600 mt-1.5 font-semibold">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Manager Booking Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800 cursor-pointer">{{ __('Manager Direct Booking') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Allow managers and staff to create bookings directly on behalf of walk-in customers') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_manager_booking_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 3. Cancellation Settings Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-lg">
                            ⚠️
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Cancellation Policy') }}</h3>
                            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Configure customer self-cancellation deadlines and penalty fees') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <!-- Cancellation Switch -->
                        <div class="flex items-center justify-between p-4 sm:p-5 bg-gray-50/70 hover:bg-gray-50 rounded-2xl border border-gray-100 transition">
                            <div class="space-y-0.5 pr-4">
                                <label class="block text-xs font-bold text-gray-800 cursor-pointer">{{ __('Allow Customer Cancellation') }}</label>
                                <p class="text-xs text-gray-400 leading-relaxed">{{ __('Permit customers to cancel their confirmed bookings from customer dashboard or app') }}</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer shrink-0">
                                <input type="checkbox" wire:model.live="is_cancellation_active" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <!-- Collapsible Cancellation Fields -->
                        @if ($is_cancellation_active)
                            <div class="p-5 sm:p-6 bg-rose-50/40 rounded-2xl border border-rose-200/70 grid grid-cols-1 md:grid-cols-2 gap-5 transition-all">
                                <div>
                                    <label class="block text-xs font-bold text-gray-900 mb-1">
                                        {{ __('Cancellation Window') }}
                                    </label>
                                    <p class="text-[11px] text-gray-500 mb-2">
                                        {{ __('Minimum hours prior to slot time required for cancellation') }}
                                    </p>
                                    <div class="relative">
                                        <input wire:model="cancellation_hours" type="number" min="0" step="1" 
                                            class="w-full pl-4 pr-16 py-2.5 bg-white rounded-xl border border-gray-200 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10 text-sm font-semibold text-gray-900 transition shadow-2xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                            placeholder="48" />
                                        <span class="absolute inset-y-0 right-0 pr-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">hours</span>
                                    </div>
                                    @error('cancellation_hours') <span class="block text-[10px] text-rose-600 mt-1.5 font-semibold">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-900 mb-1">
                                        {{ __('Cancellation Fee (Per Slot)') }}
                                    </label>
                                    <p class="text-[11px] text-gray-500 mb-2">
                                        {{ __('Fixed deduction retained by turf on customer cancellation') }}
                                    </p>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-xs font-bold text-gray-400">₹</span>
                                        <input wire:model="cancellation_fee" type="number" min="0" step="0.01" 
                                            class="w-full pl-8 pr-4 py-2.5 bg-white rounded-xl border border-gray-200 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10 text-sm font-semibold text-gray-900 transition shadow-2xs [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                            placeholder="0.00" />
                                    </div>
                                    @error('cancellation_fee') <span class="block text-[10px] text-rose-600 mt-1.5 font-semibold">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 4. Message Sharing Settings Card -->
                <div class="bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-6">
                    <div class="pb-4 border-b border-gray-100 flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-lg">
                            💬
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Message Sharing Template') }}</h3>
                            <p class="text-xs text-gray-400 font-medium mt-0.5">{{ __('Configure custom template messages for sharing booking confirmations with clients') }}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-800 mb-1.5">{{ __('Share Message Template') }}</label>
                            <textarea wire:model="share_message_template" rows="7" 
                                class="w-full p-4 rounded-2xl border border-gray-200 bg-gray-50/50 hover:bg-white focus:bg-white text-xs text-gray-900 focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500 font-mono transition shadow-2xs leading-relaxed" 
                                placeholder="*Booking Confirmed!*..."></textarea>
                            
                            <div class="mt-3">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-1.5">{{ __('Supported Placeholders:') }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{customer_name}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{turf_name}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{booking_date}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{slots}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{total_amount}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{paid_amount}</span>
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[11px] font-mono font-medium bg-gray-100 text-gray-700 border border-gray-200/60">{balance_amount}</span>
                                </div>
                            </div>
                            @error('share_message_template') <span class="block text-[10px] text-rose-600 mt-1.5 font-semibold">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Action Controls -->
            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button wire:click="save" wire:loading.attr="disabled" class="inline-flex items-center gap-2 px-7 py-3 rounded-2xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs tracking-wider uppercase transition shadow-sm cursor-pointer">
                    <svg wire:loading class="animate-spin -ml-1 mr-1.5 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ __('Save All Configurations') }}</span>
                </button>
            </div>
        @endif

    </div>
