<?php

use App\Models\SaasSetting;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $notify_booking_created = true;
    public $notify_booking_cancelled = true;
    public $notify_payment_received = true;

    public function mount()
    {
        $setting = SaasSetting::first() ?? SaasSetting::create([
            'app_name' => 'TurfBooking',
        ]);

        $this->notify_booking_created = (bool) ($setting->notify_booking_created ?? true);
        $this->notify_booking_cancelled = (bool) ($setting->notify_booking_cancelled ?? true);
        $this->notify_payment_received = (bool) ($setting->notify_payment_received ?? true);
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName, [
            'notify_booking_created' => 'boolean',
            'notify_booking_cancelled' => 'boolean',
            'notify_payment_received' => 'boolean',
        ]);
    }

    public function saveSettings()
    {
        $this->validate([
            'notify_booking_created' => 'boolean',
            'notify_booking_cancelled' => 'boolean',
            'notify_payment_received' => 'boolean',
        ]);

        $setting = SaasSetting::first() ?? new SaasSetting();

        $setting->fill([
            'notify_booking_created' => $this->notify_booking_created,
            'notify_booking_cancelled' => $this->notify_booking_cancelled,
            'notify_payment_received' => $this->notify_payment_received,
        ])->save();

        session()->flash('status', __('Push notification event triggers updated successfully.'));
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Top Tab Bar -->
        @include('livewire.saas.settings.tabs', ['active' => 'notifications'])

        <form wire:submit="saveSettings" class="space-y-6">
            <!-- Header Card -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Push Notification Preferences') }}</h2>
                        <p class="text-xs text-gray-500 mt-1">{{ __('Enable or disable automated push notifications sent to players and turf managers.') }}</p>
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

            <!-- Notification Triggers Card -->
            <div class="bg-white shadow-sm hover:shadow-md transition-shadow duration-300 rounded-3xl border border-gray-100 p-6 sm:p-8 space-y-6">
                <div class="pb-5 border-b border-gray-100 flex items-center gap-3.5">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-sm shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-gray-900 tracking-tight">{{ __('Automated Event Triggers') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Select which lifecycle events trigger automated push notifications.') }}</p>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    <!-- Row 1: Booking Created -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="bookingCreatedToggle" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Booking Created') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Send notification to customer and turf admin/manager when a new slot booking is confirmed.') }}
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-3">
                            <span class="text-xs font-bold {{ $notify_booking_created ? 'text-indigo-600' : 'text-gray-400' }}">
                                {{ $notify_booking_created ? __('Active') : __('Disabled') }}
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="bookingCreatedToggle" type="checkbox" wire:model.live="notify_booking_created" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Row 2: Booking Cancelled -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="bookingCancelledToggle" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Booking Cancelled') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Send notification when a booking or slot date is cancelled by user or management.') }}
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-3">
                            <span class="text-xs font-bold {{ $notify_booking_cancelled ? 'text-indigo-600' : 'text-gray-400' }}">
                                {{ $notify_booking_cancelled ? __('Active') : __('Disabled') }}
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="bookingCancelledToggle" type="checkbox" wire:model.live="notify_booking_cancelled" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                            </label>
                        </div>
                    </div>

                    <!-- Row 3: Payment Received -->
                    <div class="py-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div class="space-y-1 max-w-xl">
                            <label for="paymentReceivedToggle" class="text-xs font-bold text-gray-900 block cursor-pointer">
                                {{ __('Payment Received') }}
                            </label>
                            <p class="text-xs text-gray-500 leading-relaxed">
                                {{ __('Send payment receipt notification when offline cash or online gateway payment is recorded.') }}
                            </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-3">
                            <span class="text-xs font-bold {{ $notify_payment_received ? 'text-indigo-600' : 'text-gray-400' }}">
                                {{ $notify_payment_received ? __('Active') : __('Disabled') }}
                            </span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input id="paymentReceivedToggle" type="checkbox" wire:model.live="notify_payment_received" class="sr-only peer">
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600 shadow-inner"></div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
