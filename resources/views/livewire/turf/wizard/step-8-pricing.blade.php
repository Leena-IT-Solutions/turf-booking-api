@php
    $dayLabels = [
        'mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu',
        'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun',
    ];
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                Step 8 of 12 · Required (All Active Slots Must Be Priced)
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Hourly Slot Pricing & 7-Day Preview') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Set your hourly rates across the week. Rates automatically scale to your slot duration (e.g. a 30-min slot is charged 50% of the hourly rate).') }}</p>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <span class="px-3 py-1.5 rounded-xl text-xs font-black {{ empty($unpricedSlots) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                {{ empty($unpricedSlots) ? '✓ 100% Slot Coverage' : count($unpricedSlots) . ' Slot(s) Missing Price' }}
            </span>
        </div>
    </div>

    @error('pricingStep')
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold space-y-1">
            <div>⚠️ {{ $message }}</div>
        </div>
    @enderror

    <!-- Rule Builder -->
    <div class="p-5 rounded-2xl bg-gray-50/70 border border-gray-200/80 space-y-5">
        <div>
            <label class="block text-xs font-black uppercase tracking-wider text-gray-700 mb-2">{{ __('1. Do all 7 days of the week have the same pricing?') }}</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="p-3.5 rounded-xl border-2 cursor-pointer flex items-center gap-3 {{ $sameRateThroughoutWeek === 'yes' ? 'border-indigo-600 bg-white shadow-2xs' : 'border-gray-200 bg-white/60' }}">
                    <input type="radio" wire:model.live="sameRateThroughoutWeek" value="yes" class="text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="block text-xs font-black text-gray-900">{{ __('Yes — Same Rate Mon to Sun') }}</span>
                        <span class="block text-[11px] text-gray-500">{{ __('Uniform pricing every day of the week') }}</span>
                    </div>
                </label>

                <label class="p-3.5 rounded-xl border-2 cursor-pointer flex items-center gap-3 {{ $sameRateThroughoutWeek === 'no' ? 'border-indigo-600 bg-white shadow-2xs' : 'border-gray-200 bg-white/60' }}">
                    <input type="radio" wire:model.live="sameRateThroughoutWeek" value="no" class="text-indigo-600 focus:ring-indigo-500" />
                    <div>
                        <span class="block text-xs font-black text-gray-900">{{ __('No — Different Weekday / Weekend Groups') }}</span>
                        <span class="block text-[11px] text-gray-500">{{ __('Set separate rates for Mon–Thu vs Fri–Sun') }}</span>
                    </div>
                </label>
            </div>
        </div>

        @if ($sameRateThroughoutWeek === 'yes')
            <div class="pt-3 border-t border-gray-200/70 space-y-4">
                <label class="block text-xs font-black uppercase tracking-wider text-gray-700">{{ __('2. Same hourly rate throughout the entire day?') }}</label>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border cursor-pointer {{ $sameRateThroughoutDayAll === 'yes' ? 'border-indigo-600 bg-white font-bold text-indigo-700' : 'border-gray-200 bg-white text-gray-700' }}">
                        <input type="radio" wire:model.live="sameRateThroughoutDayAll" value="yes" class="text-indigo-600" />
                        <span class="text-xs">{{ __('Yes — Flat Hourly Rate All Day') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border cursor-pointer {{ $sameRateThroughoutDayAll === 'no' ? 'border-indigo-600 bg-white font-bold text-indigo-700' : 'border-gray-200 bg-white text-gray-700' }}">
                        <input type="radio" wire:model.live="sameRateThroughoutDayAll" value="no" class="text-indigo-600" />
                        <span class="text-xs">{{ __('No — Day vs Evening Time Ranges') }}</span>
                    </label>
                </div>

                @if ($sameRateThroughoutDayAll === 'yes')
                    <div class="max-w-xs">
                        <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('Hourly Rate (₹ / Hour)') }} <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-3.5 top-2.5 text-sm font-black text-gray-400">₹</span>
                            <input type="number"
                                   min="1"
                                   step="any"
                                   wire:model.live="flatRateAll"
                                   placeholder="1000"
                                   class="w-full pl-8 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm font-mono font-bold text-gray-900 bg-white focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($timeRangesAll as $rIdx => $range)
                            <div class="flex flex-wrap items-center gap-2.5 bg-white p-3 rounded-xl border border-gray-200">
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-500">{{ __('From') }}</span>
                                    <input type="time" wire:model.live="timeRangesAll.{{ $rIdx }}.from" class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-500">{{ __('To') }}</span>
                                    <input type="time" wire:model.live="timeRangesAll.{{ $rIdx }}.to" class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-xs font-bold text-gray-500">{{ __('₹/hr') }}</span>
                                    <input type="number" min="1" step="any" wire:model.live="timeRangesAll.{{ $rIdx }}.rate" placeholder="1200" class="w-28 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono font-bold" />
                                </div>
                                @if (count($timeRangesAll) > 1)
                                    <button type="button" wire:click="removePricingTimeRange('all', {{ $rIdx }})" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg text-xs font-bold cursor-pointer">✕</button>
                                @endif
                            </div>
                        @endforeach
                        <button type="button" wire:click="addPricingTimeRange('all')" class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold cursor-pointer">
                            + {{ __('Add Time Range') }}
                        </button>
                    </div>
                @endif
            </div>
        @else
            <!-- Custom Day Groups -->
            <div class="pt-3 border-t border-gray-200/70 space-y-4">
                @foreach ($dayGroups as $gIdx => $group)
                    <div class="p-4 rounded-2xl bg-white border border-gray-200 space-y-3.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase tracking-wider text-indigo-700">{{ __('Day Group #') }}{{ $gIdx + 1 }}</span>
                            @if (count($dayGroups) > 1)
                                <button type="button" wire:click="removePricingDayGroup({{ $gIdx }})" class="text-xs font-bold text-rose-600 hover:underline cursor-pointer">
                                    {{ __('Remove Group') }}
                                </button>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            @foreach ($dayLabels as $dayKey => $dayLabel)
                                @php
                                    $inGroup = in_array($dayKey, $group['days'] ?? [], true);
                                @endphp
                                <button type="button"
                                        wire:click="toggleDayInGroup({{ $gIdx }}, '{{ $dayKey }}')"
                                        class="px-3 py-1.5 rounded-xl text-xs font-black uppercase border transition cursor-pointer {{ $inGroup ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-gray-50 text-gray-700 border-gray-200 hover:border-indigo-300' }}">
                                    {{ $dayLabel }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700 cursor-pointer">
                                <input type="radio" wire:model.live="dayGroups.{{ $gIdx }}.sameRateThroughoutDay" value="yes" class="text-indigo-600" />
                                <span>{{ __('Flat Hourly Rate All Day') }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-xs font-bold text-gray-700 cursor-pointer">
                                <input type="radio" wire:model.live="dayGroups.{{ $gIdx }}.sameRateThroughoutDay" value="no" class="text-indigo-600" />
                                <span>{{ __('Time Ranges') }}</span>
                            </label>
                        </div>

                        @if (($group['sameRateThroughoutDay'] ?? 'yes') === 'yes')
                            <div class="max-w-xs">
                                <input type="number" min="1" step="any" wire:model.live="dayGroups.{{ $gIdx }}.flatRate" placeholder="₹ / Hour (e.g. 1000)" class="w-full px-3.5 py-2 rounded-xl border border-gray-200 text-xs font-mono font-bold" />
                            </div>
                        @else
                            <div class="space-y-2">
                                @foreach (($group['timeRanges'] ?? []) as $rIdx => $range)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <input type="time" wire:model.live="dayGroups.{{ $gIdx }}.timeRanges.{{ $rIdx }}.from" class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                                        <span class="text-xs text-gray-400">→</span>
                                        <input type="time" wire:model.live="dayGroups.{{ $gIdx }}.timeRanges.{{ $rIdx }}.to" class="px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono" />
                                        <input type="number" min="1" step="any" wire:model.live="dayGroups.{{ $gIdx }}.timeRanges.{{ $rIdx }}.rate" placeholder="₹/hr" class="w-28 px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-mono font-bold" />
                                        @if (count($group['timeRanges'] ?? []) > 1)
                                            <button type="button" wire:click="removePricingTimeRange('group', {{ $rIdx }}, {{ $gIdx }})" class="p-1 text-rose-600 text-xs font-bold cursor-pointer">✕</button>
                                        @endif
                                    </div>
                                @endforeach
                                <button type="button" wire:click="addPricingTimeRange('group', {{ $gIdx }})" class="px-3 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-[11px] font-bold cursor-pointer">
                                    + {{ __('Add Time Range') }}
                                </button>
                            </div>
                        @endif
                    </div>
                @endforeach

                <button type="button" wire:click="addPricingDayGroup" class="px-4 py-2 rounded-xl bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-black uppercase tracking-wider cursor-pointer">
                    + {{ __('Add Another Day Group (e.g. Weekend Fri–Sun)') }}
                </button>
            </div>
        @endif
    </div>

    <!-- Unpriced Slots Alert -->
    @if (!empty($unpricedSlots))
        <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 space-y-1.5">
            <div class="text-xs font-black uppercase tracking-wider">⚠️ {{ count($unpricedSlots) }} {{ __('Active Slot(s) Still Need Pricing') }}</div>
            <p class="text-xs text-amber-800">{{ __('Every active slot must have a price greater than ₹0 for all 7 days before you can proceed to Step 9.') }}</p>
            <div class="flex flex-wrap gap-1.5 pt-1">
                @foreach (array_slice($unpricedSlots, 0, 8) as $up)
                    <span class="px-2 py-0.5 rounded-lg bg-white border border-amber-200 text-[10px] font-mono font-bold text-amber-900">
                        {{ $up['label'] }} ({{ implode(', ', $up['missing_days']) }})
                    </span>
                @endforeach
                @if (count($unpricedSlots) > 8)
                    <span class="px-2 py-0.5 text-[10px] font-bold text-amber-700">+{{ count($unpricedSlots) - 8 }} more</span>
                @endif
            </div>
        </div>
    @endif

    <!-- 7-Day Live Slot Pricing Preview Matrix -->
    <div class="space-y-2">
        <h4 class="text-xs font-black uppercase tracking-wider text-gray-600">{{ __('7-Day Slot Rate Preview (Scaled by Slot Duration)') }}</h4>
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white max-h-72">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="bg-gray-50 sticky top-0 border-b border-gray-200 text-[10px] font-black uppercase text-gray-500">
                    <tr>
                        <th class="py-2.5 px-3">{{ __('Slot Time') }}</th>
                        <th class="py-2.5 px-2">{{ __('Dur.') }}</th>
                        @foreach ($dayLabels as $dLabel)
                            <th class="py-2.5 px-2.5 text-right">{{ $dLabel }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-mono">
                    @forelse ($activeTurfSlots as $slot)
                        @php
                            $rowPrices = $computedPricingPreview[$slot->id] ?? [];
                        @endphp
                        <tr class="hover:bg-gray-50/70">
                            <td class="py-2 px-3 font-bold text-gray-900 whitespace-nowrap">
                                {{ date('h:i A', strtotime($slot->from_time)) }} – {{ date('h:i A', strtotime($slot->to_time)) }}
                            </td>
                            <td class="py-2 px-2 text-gray-500">{{ $slot->duration }}m</td>
                            @foreach (array_keys($dayLabels) as $dKey)
                                @php
                                    $pVal = $rowPrices[$dKey] ?? null;
                                @endphp
                                <td class="py-2 px-2.5 text-right font-bold {{ ($pVal !== null && $pVal > 0) ? 'text-emerald-700' : 'text-rose-600 bg-rose-50/50' }}">
                                    {{ ($pVal !== null && $pVal > 0) ? '₹' . number_format((float) $pVal, 0) : '—' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-gray-400 font-sans">{{ __('No active slots selected in Step 7.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

