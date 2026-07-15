<?php

use App\Models\Turf;
use App\Models\Slot;
use App\Models\SlotCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $step = 1;
    public $sameRateThroughoutWeek = 'yes';
    public $sameRateThroughoutDayAll = 'yes';
    public $flatRateAll = '';
    public $timeRangesAll = [['from' => '00:00', 'to' => '23:59', 'rate' => '']];
    public $dayGroups = [
        [
            'days' => [],
            'sameRateThroughoutDay' => 'yes',
            'flatRate' => '',
            'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']]
        ]
    ];

    #[On('global-context-updated')]
    public function refreshPricing()
    {
        $this->loadPricingWizardData();
    }

    public function mount()
    {
        $this->loadPricingWizardData();
    }

    public function loadPricingWizardData()
    {
        $activeTurfId = session('active_turf_id');
        if ($activeTurfId) {
            $turf = Turf::whereHas('location', function ($q) {
                $q->where('user_id', auth()->id());
            })->find($activeTurfId);

            if ($turf && $turf->pricing_wizard_data) {
                $data = $turf->pricing_wizard_data;
                $this->sameRateThroughoutWeek = $data['sameRateThroughoutWeek'] ?? 'yes';
                $this->sameRateThroughoutDayAll = $data['sameRateThroughoutDayAll'] ?? 'yes';
                $this->flatRateAll = $data['flatRateAll'] ?? '';
                $this->timeRangesAll = $data['timeRangesAll'] ?? [['from' => '00:00', 'to' => '23:59', 'rate' => '']];
                $this->dayGroups = $data['dayGroups'] ?? [
                    [
                        'days' => [],
                        'sameRateThroughoutDay' => 'yes',
                        'flatRate' => '',
                        'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']]
                    ]
                ];
            } else {
                $this->resetWizardToDefaults();
            }
        } else {
            $this->resetWizardToDefaults();
        }
    }

    private function resetWizardToDefaults()
    {
        $this->sameRateThroughoutWeek = 'yes';
        $this->sameRateThroughoutDayAll = 'yes';
        $this->flatRateAll = '';
        $this->timeRangesAll = [['from' => '00:00', 'to' => '23:59', 'rate' => '']];
        $this->dayGroups = [
            [
                'days' => [],
                'sameRateThroughoutDay' => 'yes',
                'flatRate' => '',
                'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']]
            ]
        ];
    }

    public function nextStep()
    {
        $this->step = 2;
    }

    public function prevStep()
    {
        $this->step = 1;
    }

    public function addTimeRange($group, $groupIndex = null)
    {
        if ($group === 'all') {
            $this->timeRangesAll[] = ['from' => '00:00', 'to' => '23:59', 'rate' => ''];
        } elseif ($group === 'group' && $groupIndex !== null) {
            $this->dayGroups[$groupIndex]['timeRanges'][] = ['from' => '00:00', 'to' => '23:59', 'rate' => ''];
        }
    }

    public function removeTimeRange($group, $index, $groupIndex = null)
    {
        if ($group === 'all') {
            unset($this->timeRangesAll[$index]);
            $this->timeRangesAll = array_values($this->timeRangesAll);
        } elseif ($group === 'group' && $groupIndex !== null) {
            unset($this->dayGroups[$groupIndex]['timeRanges'][$index]);
            $this->dayGroups[$groupIndex]['timeRanges'] = array_values($this->dayGroups[$groupIndex]['timeRanges']);
        }
    }

    public function addDayGroup()
    {
        $this->dayGroups[] = [
            'days' => [],
            'sameRateThroughoutDay' => 'yes',
            'flatRate' => '',
            'timeRanges' => [['from' => '00:00', 'to' => '23:59', 'rate' => '']]
        ];
    }

    public function removeDayGroup($index)
    {
        unset($this->dayGroups[$index]);
        $this->dayGroups = array_values($this->dayGroups);
    }

    private function slotMatchesRange($slotFrom, $slotTo, $rangeFrom, $rangeTo)
    {
        $slotFromStr = date('H:i', strtotime($slotFrom));
        $slotToStr = date('H:i', strtotime($slotTo));

        if ($rangeFrom > $rangeTo) {
            return ($slotFromStr >= $rangeFrom || $slotFromStr < $rangeTo);
        }

        return ($slotFromStr >= $rangeFrom && $slotToStr <= $rangeTo);
    }

    public function applyPricing()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) {
            return;
        }

        $turf = Turf::whereHas('location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($activeTurfId);

        // Save wizard parameters to turf model
        $turf->update([
            'pricing_wizard_data' => [
                'sameRateThroughoutWeek' => $this->sameRateThroughoutWeek,
                'sameRateThroughoutDayAll' => $this->sameRateThroughoutDayAll,
                'flatRateAll' => $this->flatRateAll,
                'timeRangesAll' => $this->timeRangesAll,
                'dayGroups' => $this->dayGroups,
            ]
        ]);

        // Get active slots for pivot update
        $activeSlots = $turf->slots()->wherePivot('is_active', true)->get();

        foreach ($activeSlots as $slot) {
            $prices = [];

            if ($this->sameRateThroughoutWeek === 'yes') {
                if ($this->sameRateThroughoutDayAll === 'yes') {
                    $rate = (float)$this->flatRateAll;
                    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
                        $prices[$day] = $rate;
                    }
                } else {
                    $rate = null;
                    foreach ($this->timeRangesAll as $range) {
                        if ($this->slotMatchesRange($slot->from_time, $slot->to_time, $range['from'], $range['to'])) {
                            $rate = (float)$range['rate'];
                            break;
                        }
                    }
                    foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
                        $prices[$day] = $rate;
                    }
                }
            } else {
                foreach ($this->dayGroups as $group) {
                    $selectedDays = $group['days'] ?? [];
                    if (empty($selectedDays)) {
                        continue;
                    }

                    if ($group['sameRateThroughoutDay'] === 'yes') {
                        $rate = (float)$group['flatRate'];
                        foreach ($selectedDays as $day) {
                            $prices[$day] = $rate;
                        }
                    } else {
                        $rate = null;
                        foreach ($group['timeRanges'] as $range) {
                            if ($this->slotMatchesRange($slot->from_time, $slot->to_time, $range['from'], $range['to'])) {
                                $rate = (float)$range['rate'];
                                break;
                            }
                        }
                        foreach ($selectedDays as $day) {
                            $prices[$day] = $rate;
                        }
                    }
                }
            }

            if (!empty($prices)) {
                $turf->slots()->updateExistingPivot($slot->id, $prices);
            }
        }

        session()->flash('status', 'Pricing rules updated and applied successfully.');
        $this->step = 1;
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

        @if (session('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if (!$turf)
            <!-- Unselected Turf Empty State -->
            <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-500 dark:text-amber-400 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 dark:border-amber-950/50">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
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

            <!-- Vertical Stack Layout -->
            <div class="space-y-6">
                
                <!-- Pricing Wizard Section -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
                    <div>
                        <div class="flex items-center justify-between pb-4 border-b border-gray-50 dark:border-gray-700/40 gap-4">
                            <div class="flex items-center gap-2">
                                <span class="flex items-center justify-center h-8 w-8 rounded-lg bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 shrink-0">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 9.172V5L8 4z" />
                                    </svg>
                                </span>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Pricing Wizard') }}</h3>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Step') }} {{ $step }} {{ __('of 2') }}</p>
                                </div>
                            </div>
                        </div>
                        
                        @if ($step === 1)
                            <!-- Step 1: Weekly Rule -->
                            <div class="space-y-4 py-4">
                                <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                    {{ __('Q1. Do you have the same rate throughout the week days?') }}
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <!-- Yes Option -->
                                    <label class="relative p-5 rounded-2xl border transition duration-150 cursor-pointer flex flex-col justify-between h-32 {{ $sameRateThroughoutWeek === 'yes' ? 'bg-indigo-50/40 border-indigo-600 dark:bg-indigo-950/20 dark:border-indigo-500 ring-2 ring-indigo-600/10' : 'bg-transparent border-gray-200 hover:bg-gray-50/50 dark:border-gray-700/60 dark:hover:bg-gray-800/30' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ __('Yes, same price everyday') }}</span>
                                            <input type="radio" wire:model.live="sameRateThroughoutWeek" value="yes" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                        </div>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed mt-2">
                                            {{ __('Choose this if booking prices are identical from Monday through Sunday.') }}
                                        </p>
                                    </label>

                                    <!-- No Option -->
                                    <label class="relative p-5 rounded-2xl border transition duration-150 cursor-pointer flex flex-col justify-between h-32 {{ $sameRateThroughoutWeek === 'no' ? 'bg-indigo-50/40 border-indigo-600 dark:bg-indigo-950/20 dark:border-indigo-500 ring-2 ring-indigo-600/10' : 'bg-transparent border-gray-200 hover:bg-gray-50/50 dark:border-gray-700/60 dark:hover:bg-gray-800/30' }}">
                                        <div class="flex items-center justify-between">
                                            <span class="font-bold text-sm text-gray-900 dark:text-gray-100">{{ __('No, rates are different') }}</span>
                                            <input type="radio" wire:model.live="sameRateThroughoutWeek" value="no" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                                        </div>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500 leading-relaxed mt-2">
                                            {{ __('Choose this if you want to group days (e.g. Mon/Wed/Fri, Tue/Thu, Sat/Sun) with separate prices.') }}
                                        </p>
                                    </label>
                                </div>
                                <div class="flex justify-end pt-4 border-t border-gray-50 dark:border-gray-700/40">
                                    <button type="button" wire:click="nextStep" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5 shadow-md shadow-indigo-500/20">
                                        {{ __('Next Step') }}
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            <!-- Step 2: Rates & Details Configuration -->
                            <div class="space-y-6 py-4">
                                @if ($sameRateThroughoutWeek === 'yes')
                                    <!-- Case A: All Days Same -->
                                    <div class="space-y-4">
                                        <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                            {{ __('Q2. Do you have same rate throughout the day?') }}
                                        </h4>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <!-- Flat Rate Option -->
                                            <label class="relative p-4 rounded-xl border transition duration-150 cursor-pointer flex items-center justify-between {{ $sameRateThroughoutDayAll === 'yes' ? 'bg-indigo-50/30 border-indigo-600 dark:bg-indigo-950/20 dark:border-indigo-500 ring-2 ring-indigo-600/10' : 'bg-transparent border-gray-200 hover:bg-gray-50/50 dark:border-gray-700/60 dark:hover:bg-gray-800/30' }}">
                                                <span class="font-bold text-xs text-gray-900 dark:text-gray-105">{{ __('Yes, flat rate all day') }}</span>
                                                <input type="radio" wire:model.live="sameRateThroughoutDayAll" value="yes" class="h-4 w-4 text-indigo-600 border-gray-300">
                                            </label>

                                            <!-- Dynamic Rates Option -->
                                            <label class="relative p-4 rounded-xl border transition duration-150 cursor-pointer flex items-center justify-between {{ $sameRateThroughoutDayAll === 'no' ? 'bg-indigo-50/30 border-indigo-600 dark:bg-indigo-950/20 dark:border-indigo-500 ring-2 ring-indigo-600/10' : 'bg-transparent border-gray-200 hover:bg-gray-50/50 dark:border-gray-700/60 dark:hover:bg-gray-800/30' }}">
                                                <span class="font-bold text-xs text-gray-900 dark:text-gray-105">{{ __('No, dynamic rates') }}</span>
                                                <input type="radio" wire:model.live="sameRateThroughoutDayAll" value="no" class="h-4 w-4 text-indigo-600 border-gray-300">
                                            </label>
                                        </div>

                                        @if ($sameRateThroughoutDayAll === 'yes')
                                            <!-- Flat Price Input -->
                                            <div class="p-4 bg-gray-50/40 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800/40 rounded-2xl space-y-2">
                                                <label class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Flat Rate per Hour (₹)') }}</label>
                                                <div class="relative rounded-xl shadow-sm max-w-xs">
                                                    <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                                        <span class="text-gray-500 dark:text-gray-400 text-xs font-semibold">₹</span>
                                                    </div>
                                                    <input type="number" wire:model="flatRateAll" required class="block w-full ps-7 pe-3 py-2 text-xs font-mono font-bold rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600 transition" placeholder="500">
                                                </div>
                                            </div>
                                        @else
                                            <!-- Dynamic Time-Range Repeaters -->
                                            <div class="p-5 bg-gray-50/40 dark:bg-gray-900/30 border border-gray-100 dark:border-gray-800/40 rounded-2xl space-y-4">
                                                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800/50 pb-2">
                                                    <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('Time Slices & Surcharges') }}</span>
                                                    <button type="button" wire:click="addTimeRange('all')" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 flex items-center gap-1 transition cursor-pointer">
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                        </svg>
                                                        {{ __('Add Rule') }}
                                                    </button>
                                                </div>

                                                <div class="space-y-3 max-h-[280px] overflow-y-auto custom-scrollbar pr-1">
                                                    @foreach ($timeRangesAll as $idx => $range)
                                                        <div wire:key="time-range-all-{{ $idx }}" class="flex flex-col sm:flex-row items-center gap-3 bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200/40 dark:border-gray-800/60 shadow-sm relative">
                                                            <!-- From Time -->
                                                            <div class="w-full">
                                                                <label class="block text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">{{ __('From') }}</label>
                                                                <input type="time" wire:model="timeRangesAll.{{ $idx }}.from" required class="block w-full py-1.5 px-2 text-xs font-mono font-semibold rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600">
                                                            </div>
                                                            <!-- To Time -->
                                                            <div class="w-full">
                                                                <label class="block text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">{{ __('To') }}</label>
                                                                <input type="time" wire:model="timeRangesAll.{{ $idx }}.to" required class="block w-full py-1.5 px-2 text-xs font-mono font-semibold rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600">
                                                            </div>
                                                            <!-- Rate -->
                                                            <div class="w-full">
                                                                <label class="block text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">{{ __('Rate/Hr (₹)') }}</label>
                                                                <input type="number" wire:model="timeRangesAll.{{ $idx }}.rate" required class="block w-full py-1.5 px-2 text-xs font-mono font-bold rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600" placeholder="600">
                                                            </div>
                                                            <!-- Delete Button -->
                                                            @if (count($timeRangesAll) > 1)
                                                                <button type="button" wire:click="removeTimeRange('all', {{ $idx }})" class="sm:mt-4 p-1.5 text-gray-400 hover:text-red-500 transition cursor-pointer">
                                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @else
                                    <!-- Case B: Custom Day Groups -->
                                    <div class="space-y-6">
                                        <div class="flex items-center justify-between pb-2 border-b border-gray-100 dark:border-gray-800/50">
                                            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                                {{ __('Configure Day Groups & Pricing Rules') }}
                                            </h4>
                                            <button type="button" wire:click="addDayGroup" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 flex items-center gap-1 transition cursor-pointer bg-indigo-50 dark:bg-indigo-950/20 px-3 py-1.5 rounded-xl border border-indigo-100/30">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                </svg>
                                                {{ __('Add Day Group') }}
                                            </button>
                                        </div>

                                         <div class="space-y-6 max-h-[480px] overflow-y-auto custom-scrollbar pr-1.5 pb-2">
                                             @foreach ($dayGroups as $gIndex => $group)
                                                 <div wire:key="day-group-{{ $gIndex }}" class="p-5 bg-gray-50/40 dark:bg-gray-900/30 border border-gray-200/60 dark:border-gray-700/40 rounded-3xl space-y-4 relative">
                                                     <!-- Remove Group button -->
                                                     @if (count($dayGroups) > 1)
                                                         <button type="button" wire:click="removeDayGroup({{ $gIndex }})" class="absolute top-4 end-4 text-[10px] font-bold text-red-500 hover:text-red-600 flex items-center gap-1 transition cursor-pointer">
                                                             <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                 <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                             </svg>
                                                             {{ __('Remove Group') }}
                                                         </button>
                                                     @endif

                                                     <!-- Group Title & Days checkboxes -->
                                                     <div class="space-y-2">
                                                         <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ __('1. Select days in this group:') }}</span>
                                                         
                                                         <div class="flex flex-wrap gap-2">
                                                             @foreach (['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'] as $dayVal => $dayLbl)
                                                                 <label wire:key="day-checkbox-{{ $gIndex }}-{{ $dayVal }}" class="relative flex items-center px-3.5 py-1.5 rounded-xl border text-[10px] font-bold transition duration-100 cursor-pointer select-none {{ in_array($dayVal, $group['days'] ?? []) ? 'bg-indigo-600 border-indigo-600 text-white dark:bg-indigo-500 dark:border-indigo-500 shadow-sm' : 'bg-transparent text-gray-500 hover:bg-gray-50/50 border-gray-300 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800/40' }}">
                                                                     <input type="checkbox" wire:model.live="dayGroups.{{ $gIndex }}.days" value="{{ $dayVal }}" class="sr-only">
                                                                     {{ $dayLbl }}
                                                                 </label>
                                                             @endforeach
                                                         </div>
                                                     </div>

                                                     <!-- Rate selector -->
                                                     <div class="space-y-3 pt-2">
                                                         <span class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider block">{{ __('2. Rates configuration:') }}</span>
                                                         <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                                             <label class="relative p-3.5 rounded-xl border transition duration-150 cursor-pointer flex items-center justify-between {{ $group['sameRateThroughoutDay'] === 'yes' ? 'bg-indigo-50/20 border-indigo-600 dark:bg-indigo-950/10 dark:border-indigo-500 ring-2 ring-indigo-600/10' : 'bg-transparent border-gray-200 hover:bg-gray-50/50 dark:border-gray-750/60 dark:hover:bg-gray-800/30' }}">
                                                                 <span class="font-bold text-xs text-gray-900 dark:text-gray-100">{{ __('Flat rate all day') }}</span>
                                                                 <input type="radio" wire:model.live="dayGroups.{{ $gIndex }}.sameRateThroughoutDay" value="yes" class="h-4 w-4 text-indigo-600 border-gray-300">
                                                             </label>

                                                             <label class="relative p-3.5 rounded-xl border transition duration-150 cursor-pointer flex items-center justify-between {{ $group['sameRateThroughoutDay'] === 'no' ? 'bg-indigo-50/20 border-indigo-600 dark:bg-indigo-950/10 dark:border-indigo-500 ring-2 ring-indigo-600/10' : 'bg-transparent border-gray-200 hover:bg-gray-50/50 dark:border-gray-750/60 dark:hover:bg-gray-800/30' }}">
                                                                 <span class="font-bold text-xs text-gray-900 dark:text-gray-100">{{ __('Different time range rates') }}</span>
                                                                 <input type="radio" wire:model.live="dayGroups.{{ $gIndex }}.sameRateThroughoutDay" value="no" class="h-4 w-4 text-indigo-600 border-gray-300">
                                                             </label>
                                                         </div>
                                                     </div>

                                                     <!-- Inputs depending on rate type -->
                                                     @if ($group['sameRateThroughoutDay'] === 'yes')
                                                         <div class="ps-1 max-w-xs space-y-1.5">
                                                             <label class="text-[9px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Flat Rate/Hr (₹)') }}</label>
                                                             <div class="relative rounded-xl shadow-sm">
                                                                 <div class="absolute inset-y-0 start-0 ps-3 flex items-center pointer-events-none">
                                                                     <span class="text-gray-500 dark:text-gray-400 text-xs font-semibold">₹</span>
                                                                 </div>
                                                                 <input type="number" wire:model="dayGroups.{{ $gIndex }}.flatRate" required class="block w-full ps-7 pe-3 py-2 text-xs font-mono font-bold rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600 transition" placeholder="600">
                                                             </div>
                                                         </div>
                                                     @else
                                                         <!-- Dynamic time ranges per day group -->
                                                         <div class="space-y-3 pt-2">
                                                             <div class="flex items-center justify-between pb-1 border-b border-gray-100 dark:border-gray-800/50">
                                                                 <span class="text-[9px] font-bold text-gray-400 dark:text-gray-500 uppercase">{{ __('Time Slices') }}</span>
                                                                 <button type="button" wire:click="addTimeRange('group', {{ $gIndex }})" class="text-[9px] font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 flex items-center gap-1 cursor-pointer">
                                                                     <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                                         <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                                                     </svg>
                                                                     {{ __('Add Row') }}
                                                                 </button>
                                                             </div>

                                                             <div class="space-y-3 max-h-[220px] overflow-y-auto custom-scrollbar pr-1">
                                                                 @foreach ($group['timeRanges'] as $tIdx => $range)
                                                                     <div wire:key="time-range-{{ $gIndex }}-{{ $tIdx }}" class="flex flex-col sm:flex-row items-center gap-3 bg-white dark:bg-gray-900 p-3 rounded-xl border border-gray-200/40 dark:border-gray-800/60 shadow-sm relative">
                                                                         <div class="w-full">
                                                                             <label class="block text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">{{ __('From') }}</label>
                                                                             <input type="time" wire:model="dayGroups.{{ $gIndex }}.timeRanges.{{ $tIdx }}.from" required class="block w-full py-1.5 px-2 text-xs font-mono font-semibold rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600">
                                                                         </div>
                                                                         <div class="w-full">
                                                                             <label class="block text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">{{ __('To') }}</label>
                                                                             <input type="time" wire:model="dayGroups.{{ $gIndex }}.timeRanges.{{ $tIdx }}.to" required class="block w-full py-1.5 px-2 text-xs font-mono font-semibold rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600">
                                                                         </div>
                                                                         <div class="w-full">
                                                                             <label class="block text-[8px] font-bold text-gray-400 dark:text-gray-500 uppercase mb-1">{{ __('Rate/Hr (₹)') }}</label>
                                                                             <input type="number" wire:model="dayGroups.{{ $gIndex }}.timeRanges.{{ $tIdx }}.rate" required class="block w-full py-1.5 px-2 text-xs font-mono font-bold rounded-lg bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white focus:ring-indigo-500/20 focus:border-indigo-600" placeholder="600">
                                                                         </div>
                                                                         @if (count($group['timeRanges']) > 1)
                                                                             <button type="button" wire:click="removeTimeRange('group', {{ $tIdx }}, {{ $gIndex }})" class="sm:mt-4 p-1.5 text-gray-400 hover:text-red-500 transition cursor-pointer">
                                                                                 <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                                                     <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                                 </svg>
                                                                             </button>
                                                                         @endif
                                                                     </div>
                                                                 @endforeach
                                                             </div>
                                                         </div>
                                                     @endif
                                                 </div>
                                             @endforeach
                                         </div>
                                     </div>
                                 @endif

                                <!-- Bottom actions for Step 2 -->
                                <div class="flex justify-between items-center pt-4 border-t border-gray-200 dark:border-gray-700/40">
                                    <button type="button" wire:click="prevStep" class="px-4 py-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl transition cursor-pointer border border-gray-200 dark:border-gray-600 flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                        </svg>
                                        {{ __('Back') }}
                                    </button>
                                    
                                    <button type="button" wire:click="applyPricing" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ __('Apply & Save Pricing') }}
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Slots List showing pricing -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
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
                                            <div class="flex items-center gap-2 pb-1.5 border-b border-gray-100 dark:border-gray-800/60">
                                                <span class="text-sm shrink-0">{{ $category->icon ?: '⏰' }}</span>
                                                <h4 class="text-[10px] font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">{{ $category->name }}</h4>
                                            </div>

                                            <!-- Slots Grid -->
                                            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                                                @foreach ($categorySlots as $slot)
                                                    <div class="p-4 rounded-2xl border border-gray-200/40 bg-gray-50/20 dark:border-gray-700/50 dark:bg-gray-800/30 flex flex-col justify-between">
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
                                                                    <span class="text-[8px] font-bold text-gray-500 dark:text-gray-400 uppercase">{{ $dayName }}</span>
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
