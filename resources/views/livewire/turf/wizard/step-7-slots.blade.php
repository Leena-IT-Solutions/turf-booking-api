<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                Step 7 of 12 · Required (Min 1 Active Slot)
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Operating Hours & Active Time Slots') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Use the quick opening-hours selector below or toggle individual time slots across Morning, Afternoon, Evening, and Midnight.') }}</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button"
                    wire:click="selectAllSlots"
                    class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition cursor-pointer">
                {{ __('Select All (24 Hours)') }}
            </button>
            <button type="button"
                    wire:click="deselectAllSlots"
                    class="px-3.5 py-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition cursor-pointer">
                {{ __('Clear All') }}
            </button>
        </div>
    </div>

    @error('slotsStep')
        <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
            {{ $message }}
        </div>
    @enderror

    <!-- Quick Opening-Hours Window Selector -->
    <div class="p-4 sm:p-5 rounded-2xl bg-indigo-50/50 border border-indigo-100 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="space-y-0.5">
            <div class="text-xs font-black uppercase tracking-wider text-indigo-900">⚡ {{ __('Quick Operating Window Selector') }}</div>
            <p class="text-[11px] text-indigo-700">{{ __('Pick your daily opening and closing time to automatically tick all matching master slots.') }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-gray-700">{{ __('Open:') }}</label>
                <input type="time"
                       wire:model="slotWindowFrom"
                       class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-mono font-bold text-gray-900 bg-white" />
            </div>
            <div class="flex items-center gap-2">
                <label class="text-xs font-bold text-gray-700">{{ __('Close:') }}</label>
                <input type="time"
                       wire:model="slotWindowTo"
                       class="px-3 py-1.5 rounded-xl border border-gray-200 text-xs font-mono font-bold text-gray-900 bg-white" />
            </div>
            <button type="button"
                    wire:click="selectSlotsByTimeWindow"
                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider shadow-xs transition cursor-pointer">
                {{ __('Apply Time Window') }}
            </button>
        </div>
    </div>

    @if ($allSlots->isEmpty())
        <div class="p-10 rounded-2xl bg-amber-50 border border-amber-200 text-center space-y-2">
            <p class="text-sm font-bold text-amber-900">{{ __('No master time slots have been created by the platform administrator yet.') }}</p>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($availableCategories as $category)
                @php
                    $categorySlots = $allSlots->where('slot_category_id', $category->id);
                @endphp
                @if ($categorySlots->isNotEmpty())
                    <div class="p-5 rounded-2xl border border-gray-100 bg-gray-50/40 space-y-3.5">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="text-sm font-black text-gray-900">{{ $category->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ $categorySlots->filter(fn($s) => in_array((string) $s->id, $selectedSlotIds, true))->count() }} / {{ $categorySlots->count() }} {{ __('selected') }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        wire:click="selectCategorySlots({{ $category->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-white hover:bg-indigo-50 text-indigo-600 border border-gray-200 text-[11px] font-bold cursor-pointer">
                                    {{ __('Select Category') }}
                                </button>
                                <button type="button"
                                        wire:click="deselectCategorySlots({{ $category->id }})"
                                        class="px-2.5 py-1 rounded-lg bg-white hover:bg-gray-100 text-gray-600 border border-gray-200 text-[11px] font-bold cursor-pointer">
                                    {{ __('Clear') }}
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-2.5">
                            @foreach ($categorySlots as $slot)
                                @php
                                    $isSlotSelected = in_array((string) $slot->id, $selectedSlotIds, true);
                                @endphp
                                <label class="flex items-center justify-between px-3 py-2.5 rounded-xl border text-xs font-mono font-bold cursor-pointer transition select-none {{ $isSlotSelected ? 'border-indigo-600 bg-indigo-600 text-white shadow-2xs' : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300' }}">
                                    <span class="truncate">{{ date('h:i A', strtotime($slot->from_time)) }} – {{ date('h:i A', strtotime($slot->to_time)) }}</span>
                                    <input type="checkbox"
                                           value="{{ $slot->id }}"
                                           wire:model.live="selectedSlotIds"
                                           class="hidden" />
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
