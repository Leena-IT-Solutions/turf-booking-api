<?php

use App\Models\Turf;
use App\Models\Slot;
use App\Models\SlotCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[On('global-context-updated')]
    public function refreshPricing()
    {
        // Placeholder for refreshing
    }

    public function with()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;
        $slots = collect();
        $availableCategories = collect();

        if ($activeTurfId) {
            $turf = Turf::whereHas('location', function ($q) {
                $q->where('user_id', auth()->id());
            })->find($activeTurfId);

            if ($turf) {
                $slots = $turf->slots()
                    ->wherePivot('is_active', true)
                    ->orderBy('from_time', 'asc')
                    ->get();

                $availableCategories = SlotCategory::where('is_active', true)
                    ->orderBy('sort_order', 'asc')
                    ->get();
            }
        }

        return [
            'turf' => $turf,
            'slots' => $slots,
            'availableCategories' => $availableCategories,
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">

        @if (!$turf)
            <!-- Unselected Turf Empty State -->
            <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-500 dark:text-amber-400 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 dark:border-amber-950/50">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-750 dark:text-gray-250 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure pricing.') }}</p>
            </div>
        @else
            <!-- Header Section -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Pricing for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage your court booking prices, surcharges, and packages here.') }}</p>
                </div>
            </div>

            <!-- Two-Column Grid Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left Column: Wizard Form Section (Initially Empty Placeholder) -->
                <div class="lg:col-span-5 bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6 min-h-[400px] flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2 pb-4 border-b border-gray-50 dark:border-gray-700/40">
                            <span class="flex items-center justify-center h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 9.172V5L8 4z" />
                                </svg>
                            </span>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Pricing Wizard') }}</h3>
                        </div>
                        
                        <!-- Empty Wizard Content (To be updated later) -->
                        <div class="py-12 text-center text-xs text-gray-400 dark:text-gray-500">
                            <svg class="h-10 w-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            {{ __('Wizard updates will be available here.') }}
                        </div>
                    </div>
                </div>

                <!-- Right Column: Slots List showing pricing -->
                <div class="lg:col-span-7 space-y-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-50 dark:border-gray-700/40">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Current Slot Rates') }}</h3>
                            <a href="{{ route('turf.slots') }}" wire:navigate class="text-xs text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 font-bold transition flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                {{ __('Configure Slots') }}
                            </a>
                        </div>

                        @if ($slots->isEmpty())
                            <div class="py-12 text-center text-xs text-gray-400 dark:text-gray-500">
                                <svg class="h-10 w-10 mx-auto mb-3 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                {{ __('No active time slots selected. Please enable slots on the Slots manager page first.') }}
                            </div>
                        @else
                            <div class="mt-6 space-y-6">
                                @foreach ($availableCategories as $category)
                                    @php
                                        $categorySlots = $slots->where('slot_category_id', $category->id);
                                    @endphp
                                    @if ($categorySlots->isNotEmpty())
                                        <div class="space-y-3">
                                            <!-- Category Header -->
                                            <div class="flex items-center gap-2 pb-1.5 border-b border-gray-50 dark:border-gray-800/60">
                                                <span class="text-sm shrink-0">{{ $category->icon ?: '⏰' }}</span>
                                                <h4 class="text-[10px] font-bold text-gray-450 dark:text-gray-500 uppercase tracking-wider">{{ $category->name }}</h4>
                                            </div>

                                            <!-- Slots Grid -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                @foreach ($categorySlots as $slot)
                                                    <div class="p-4 rounded-2xl border border-gray-150/40 bg-gray-50/20 dark:border-gray-700/50 dark:bg-gray-800/30 flex flex-col justify-between">
                                                        <div class="flex justify-between items-start">
                                                            <div class="flex flex-col">
                                                                <span class="font-bold text-sm text-gray-900 dark:text-gray-100 font-mono">
                                                                    {{ date('h:i A', strtotime($slot->from_time)) }} - {{ date('h:i A', strtotime($slot->to_time)) }}
                                                                </span>
                                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">
                                                                    {{ __('Duration:') }} {{ $slot->duration }}m
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <!-- Weekly Price Grid -->
                                                        <div class="grid grid-cols-7 gap-1 mt-3 pt-3 border-t border-gray-100 dark:border-gray-700/40">
                                                            @foreach (['Mon' => 'mon', 'Tue' => 'tue', 'Wed' => 'wed', 'Thu' => 'thu', 'Fri' => 'fri', 'Sat' => 'sat', 'Sun' => 'sun'] as $dayName => $dayKey)
                                                                @php
                                                                    $price = $slot->pivot ? $slot->pivot->$dayKey : null;
                                                                @endphp
                                                                <div class="flex flex-col items-center bg-gray-50 dark:bg-gray-900/50 py-1.5 px-0.5 rounded-lg border border-gray-100/50 dark:border-gray-800/30">
                                                                    <span class="text-[8px] font-bold text-gray-450 dark:text-gray-500 uppercase">{{ $dayName }}</span>
                                                                    <span class="text-[10px] font-mono font-bold text-indigo-600 dark:text-indigo-400 mt-1">
                                                                        {{ $price !== null ? '₹' . number_format($price, 0) : '—' }}
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        @endif

    </div>
</div>
