@php
    $isStep4 = $currentStep === 4;
    $isStep5 = $currentStep === 5;
    $isStep6 = $currentStep === 6;

    $stepBadge = $isStep4
        ? 'Step 4 of 12 · Optional'
        : ($isStep5 ? 'Step 5 of 12 · Optional' : 'Step 6 of 12 · Required (Min 1 Sport)');
    $stepHeading = $isStep4
        ? __('Venue Facilities & Amenities')
        : ($isStep5 ? __('Rental Gear & Equipments') : __('Supported Sports & Match Formats'));
    $stepSubtitle = $isStep4
        ? __('Select all physical amenities available at this venue (e.g., Floodlights, Parking, Washroom, Drinking Water).')
        : ($isStep5
            ? __('Select any equipment or playing gear provided at the venue (optional — you can skip if none).')
            : __('Select at least 1 sport that players can book on this turf court.'));

    $items = $isStep4 ? $allFacilities : ($isStep5 ? $allEquipments : $allSports);
    $modelProperty = $isStep4 ? 'selectedFacilityIds' : ($isStep5 ? 'selectedEquipmentIds' : 'selectedSportIds');
    $selectedArray = $isStep4 ? $selectedFacilityIds : ($isStep5 ? $selectedEquipmentIds : $selectedSportIds);
    $selectAllAction = $isStep4 ? "selectAllCatalog('facilities')" : ($isStep5 ? "selectAllCatalog('equipments')" : "selectAllCatalog('sports')");
    $clearAllAction = $isStep4 ? "clearAllCatalog('facilities')" : ($isStep5 ? "clearAllCatalog('equipments')" : "clearAllCatalog('sports')");
    $masterRoute = $isStep4 ? route('saas.facilities') : ($isStep5 ? route('saas.equipments') : route('saas.sports'));
@endphp

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider {{ $isStep6 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200/60' : 'bg-gray-100 text-gray-700' }}">
                {{ $stepBadge }}
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ $stepHeading }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ $stepSubtitle }}</p>
        </div>

        @if ($items->isNotEmpty())
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        wire:click="{{ $selectAllAction }}"
                        class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 text-xs font-bold transition cursor-pointer">
                    {{ __('Select All') }}
                </button>
                <button type="button"
                        wire:click="{{ $clearAllAction }}"
                        class="px-3 py-1.5 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition cursor-pointer">
                    {{ __('Clear') }}
                </button>
            </div>
        @endif
    </div>

    @error('sportsStep')
        <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
            {{ $message }}
        </div>
    @enderror

    @if ($items->isEmpty())
        <div class="p-10 rounded-2xl bg-amber-50/60 border border-amber-200 text-center space-y-3">
            <p class="text-sm font-bold text-amber-900">{{ __('No master items have been configured on the platform yet.') }}</p>
            @if (session()->has('impersonator_id') || auth()->user()->hasRole('saas-admin') || auth()->user()->hasRole('marketing'))
                <a href="{{ $masterRoute }}" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white text-xs font-black uppercase tracking-wider">
                    <span>Open Master Setup in New Tab ↗</span>
                </a>
            @endif
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
            @foreach ($items as $item)
                @php
                    $isChecked = in_array((string) $item->id, $selectedArray, true);
                @endphp
                <label class="group relative flex items-center justify-between p-4 rounded-2xl border-2 cursor-pointer transition select-none {{ $isChecked ? 'border-indigo-600 bg-indigo-50/40 shadow-2xs' : 'border-gray-100 bg-white hover:border-indigo-200' }}">
                    <div class="flex items-center gap-3 min-w-0 pr-2">
                        <div class="h-10 w-10 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-center shrink-0 overflow-hidden">
                            @if ($item->icon)
                                <img src="{{ Storage::url($item->icon) }}" alt="{{ $item->name }}" class="h-6 w-6 object-contain" />
                            @else
                                <span class="text-sm font-black text-indigo-600">{{ strtoupper(substr($item->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <span class="text-xs font-bold text-gray-900 truncate">{{ $item->name }}</span>
                    </div>
                    <input type="checkbox"
                           value="{{ $item->id }}"
                           wire:model.live="{{ $modelProperty }}"
                           class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                </label>
            @endforeach
        </div>
    @endif
</div>
