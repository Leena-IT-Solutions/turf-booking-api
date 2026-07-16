<?php

use App\Models\Turf;
use App\Models\Slot;
use App\Models\SlotCategory;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $selectedSlotIds = [];

    #[On('global-context-updated')]
    public function refreshSlots()
    {
        $this->loadSelectedSlots();
    }

    public function mount()
    {
        $this->loadSelectedSlots();
    }

    public function loadSelectedSlots()
    {
        $activeTurfId = session('active_turf_id');
        if ($activeTurfId) {
            $turf = Turf::manageable()->find($activeTurfId);

            if ($turf) {
                $this->selectedSlotIds = $turf->slots()
                    ->wherePivot('is_active', true)
                    ->pluck('slots.id')
                    ->map(fn($id) => (string)$id)
                    ->toArray();
            } else {
                $this->selectedSlotIds = [];
            }
        } else {
            $this->selectedSlotIds = [];
        }
    }

    public function saveSlots()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) {
            return;
        }

        $turf = Turf::manageable()->findOrFail($activeTurfId);

        // Fetch all active global master slots
        $allActiveSlots = Slot::where('is_active', true)->get();

        $syncData = [];
        foreach ($allActiveSlots as $slot) {
            $isSelected = in_array((string)$slot->id, $this->selectedSlotIds);
            $syncData[$slot->id] = ['is_active' => $isSelected];
        }

        $turf->slots()->sync($syncData);

        session()->flash('status', 'Slots configuration updated successfully.');
    }

    public function selectAll()
    {
        $ids = Slot::where('is_active', true)
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->toArray();
        sort($ids);
        $this->selectedSlotIds = $ids;
    }

    public function deselectAll()
    {
        $this->selectedSlotIds = [];
    }

    public function selectCategorySlots($categoryId)
    {
        $slotIds = Slot::where('is_active', true)
            ->where('slot_category_id', $categoryId)
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->toArray();

        $merged = array_unique(array_merge($this->selectedSlotIds, $slotIds));
        sort($merged);
        $this->selectedSlotIds = $merged;
    }

    public function deselectCategorySlots($categoryId)
    {
        $slotIds = Slot::where('is_active', true)
            ->where('slot_category_id', $categoryId)
            ->pluck('id')
            ->map(fn($id) => (string)$id)
            ->toArray();

        $diff = array_values(array_diff($this->selectedSlotIds, $slotIds));
        sort($diff);
        $this->selectedSlotIds = $diff;
    }

    public function with()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;

        if ($activeTurfId) {
            $turf = Turf::manageable()->find($activeTurfId);
        }

        $allSlots = Slot::where('is_active', true)->orderBy('from_time', 'asc')->get();
        $availableCategories = SlotCategory::where('is_active', true)->orderBy('sort_order', 'asc')->get();

        return [
            'turf' => $turf,
            'allSlots' => $allSlots,
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
                <h3 class="text-sm font-bold text-gray-750 dark:text-gray-250 uppercase tracking-wider">{{ __('No Turf Selected') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to configure slots.') }}</p>
            </div>
        @else
            <!-- Form Card Wrapper -->
            <form wire:submit="saveSlots" class="space-y-6">
                <!-- Header Section -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Time Slots for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Select what activity booking times are available for play at this turf court.') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <button type="button" wire:click="selectAll" class="px-4 py-2.5 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl transition cursor-pointer border border-gray-250 dark:border-gray-600 flex items-center justify-center gap-1.5 shadow-sm">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            {{ __('Select All') }}
                        </button>
                        <button type="button" wire:click="deselectAll" class="px-4 py-2.5 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 text-gray-500 hover:text-red-650 dark:text-gray-400 dark:hover:text-red-400 text-xs font-bold rounded-xl transition cursor-pointer border border-gray-250 dark:border-gray-600 flex items-center justify-center gap-1.5 shadow-sm">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            {{ __('Deselect All') }}
                        </button>
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ __('Save Slots') }}
                        </button>
                    </div>
                </div>

                @if ($allSlots->isEmpty())
                    <!-- Empty State -->
                    <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                        <div class="h-16 w-16 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/30 dark:border-indigo-950/20">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Global Slots Configured') }}</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto leading-relaxed">{{ __('Please ask the SaaS Administrator to add slots to the global repository first.') }}</p>
                    </div>
                @else
                    <!-- Grouped Categories & Slots -->
                    <div class="space-y-8">
                        @foreach ($availableCategories as $category)
                            @php
                                $categorySlots = $allSlots->where('slot_category_id', $category->id);
                            @endphp
                            @if ($categorySlots->isNotEmpty())
                                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-4">
                                    <!-- Category Group Header -->
                                    <div class="flex items-center justify-between border-b border-gray-50 dark:border-gray-700/40 pb-3 gap-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-9 w-9 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold text-base border border-indigo-100/30 dark:border-indigo-950/30 shrink-0">
                                                {{ $category->icon ?: '⏰' }}
                                            </div>
                                            <div>
                                                <h3 class="text-xs font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ $category->name }}</h3>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">
                                                    {{ $categorySlots->count() }} {{ __('Available Slots') }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2 text-[10px] font-bold">
                                            <button type="button" wire:click="selectCategorySlots({{ $category->id }})" class="text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 transition cursor-pointer">
                                                {{ __('Select Category') }}
                                            </button>
                                            <span class="text-gray-300 dark:text-gray-700">|</span>
                                            <button type="button" wire:click="deselectCategorySlots({{ $category->id }})" class="text-gray-500 hover:text-red-500 dark:text-gray-450 dark:hover:text-red-400 transition cursor-pointer">
                                                {{ __('Deselect Category') }}
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Checkbox Grid -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                                        @foreach ($categorySlots as $slot)
                                            <label class="relative flex items-center p-4 rounded-2xl border transition duration-150 cursor-pointer {{ in_array((string)$slot->id, $selectedSlotIds) ? 'bg-indigo-50/40 border-indigo-600 dark:bg-indigo-950/20 dark:border-indigo-500 ring-2 ring-indigo-600/10 dark:ring-indigo-500/10' : 'bg-transparent border-gray-100 hover:bg-gray-50/50 dark:border-gray-700 dark:hover:bg-gray-800/30' }}">
                                                <div class="flex items-center h-5">
                                                    <input type="checkbox" wire:model="selectedSlotIds" value="{{ $slot->id }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500/20 dark:border-gray-750 dark:bg-gray-900 dark:checked:bg-indigo-600 transition">
                                                </div>
                                                <div class="ms-3 flex flex-col">
                                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 font-mono">
                                                        {{ date('h:i A', strtotime($slot->from_time)) }}
                                                    </span>
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">
                                                        {{ date('h:i A', strtotime($slot->to_time)) }} ({{ $slot->duration }}m)
                                                    </span>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>

                    <!-- Bottom Save Section -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex items-center justify-end">
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ __('Save Slots') }}
                        </button>
                    </div>
                @endif
            </form>
        @endif

    </div>
</div>
