<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                Step 2 of 12 · Required
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Turf / Court Details') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ __('Configure the playing surface and court details under location:') }}
                <strong class="text-indigo-600">{{ $currentLocation?->name ?? 'Selected Location' }}</strong>
            </p>
        </div>

        @if ($locationTurfs->isNotEmpty())
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        wire:click="switchTurfMode('existing')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer {{ $turfMode === 'existing' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ __('Existing Turf') }} ({{ $locationTurfs->count() }})
                </button>
                <button type="button"
                        wire:click="switchTurfMode('create')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer {{ $turfMode === 'create' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    + {{ __('New Turf / Court') }}
                </button>
            </div>
        @endif
    </div>

    @if ($turfMode === 'existing' && $locationTurfs->isNotEmpty())
        <div class="space-y-3">
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-600">{{ __('Select Turf at this Location') }}</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($locationTurfs as $t)
                    @php
                        $isSelectedTurf = (int) $turfId === (int) $t->id;
                    @endphp
                    <div wire:click="selectExistingTurf({{ $t->id }})"
                         class="p-4 rounded-2xl border-2 transition cursor-pointer flex items-start justify-between gap-3 {{ $isSelectedTurf ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-gray-100 hover:border-indigo-200 bg-white' }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-black text-gray-900">{{ $t->name }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-gray-100 text-gray-700">
                                    {{ $t->type }}
                                </span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase {{ $t->status === 'Approved' ? 'bg-emerald-50 text-emerald-700' : ($t->status === 'Pending' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}">
                                    {{ $t->status }}
                                </span>
                            </div>
                            @if ($t->area)
                                <p class="text-xs text-gray-500 mt-1">{{ __('Dimensions:') }} {{ $t->area }}</p>
                            @endif
                        </div>
                        <div class="h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 {{ $isSelectedTurf ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300' }}">
                            @if ($isSelectedTurf)
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Turf / Court Name') }} <span class="text-rose-500">*</span></label>
            <input type="text"
                   wire:model="turfName"
                   placeholder="e.g. Center Pitch (Court 1)"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
            @error('turfName') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Surface Type') }} <span class="text-rose-500">*</span></label>
            <select wire:model="turfType"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 bg-white focus:border-indigo-500 focus:ring-indigo-500">
                <option value="Synthetic">{{ __('Synthetic (AstroTurf / Artificial Grass)') }}</option>
                <option value="Hard">{{ __('Hard / Cement / Acrylic / Matting') }}</option>
                <option value="Other">{{ __('Other (Natural Grass / Wooden / Clay)') }}</option>
            </select>
            @error('turfType') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Playing Area / Dimensions') }}</label>
            <input type="text"
                   wire:model="turfArea"
                   placeholder="e.g. 100 x 60 ft (7v7 / Box Cricket)"
                   class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
            @error('turfArea') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center pt-5">
            <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                <input type="checkbox"
                       wire:model="turfIsActive"
                       class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                <div>
                    <span class="block text-sm font-bold text-gray-900">{{ __('Active & Visible to Players') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Keep enabled so players can book once verified.') }}</span>
                </div>
            </label>
        </div>

        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Turf Description & Highlights') }}</label>
            <textarea wire:model="turfDescription"
                      rows="3"
                      placeholder="Describe FIFA-grade grass quality, roof height, net enclosure, floodlights, etc."
                      class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('turfDescription') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

