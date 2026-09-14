<?php

use App\Models\Location;
use App\Models\Turf;
use Livewire\Volt\Component;
use Livewire\Attributes\On;

new class extends Component
{
    public $selectedLocationId = null;
    public $selectedTurfId = null;

    public function mount()
    {
        $this->initializeContext();
    }

    #[On('locations-updated')]
    #[On('turfs-updated')]
    public function initializeContext()
    {
        $locations = Location::manageable()->orderBy('name', 'asc')->get();

        if ($locations->isEmpty()) {
            $this->selectedLocationId = null;
            $this->selectedTurfId = null;
            session()->forget(['active_location_id', 'active_turf_id']);
            return;
        }

        // 1. Resolve Location ID
        $sessionLocId = session('active_location_id');
        if ($sessionLocId && $locations->contains('id', $sessionLocId)) {
            $this->selectedLocationId = $sessionLocId;
        } else {
            $this->selectedLocationId = $locations->first()->id;
            session(['active_location_id' => $this->selectedLocationId]);
        }

        // 2. Resolve Turf ID
        $turfs = Turf::manageable()->where('location_id', $this->selectedLocationId)->orderBy('name', 'asc')->get();
        if ($turfs->isEmpty()) {
            $this->selectedTurfId = null;
            session()->forget('active_turf_id');
            return;
        }

        $sessionTurfId = session('active_turf_id');
        if ($sessionTurfId && $turfs->contains('id', $sessionTurfId)) {
            $this->selectedTurfId = $sessionTurfId;
        } else {
            $this->selectedTurfId = $turfs->first()->id;
            session(['active_turf_id' => $this->selectedTurfId]);
        }
    }

    public function updatedSelectedLocationId($value)
    {
        session(['active_location_id' => $value]);
        
        $firstTurf = Turf::manageable()->where('location_id', $value)->orderBy('name', 'asc')->first();
        if ($firstTurf) {
            $this->selectedTurfId = $firstTurf->id;
            session(['active_turf_id' => $firstTurf->id]);
        } else {
            $this->selectedTurfId = null;
            session()->forget('active_turf_id');
        }

        $this->dispatch('global-context-updated');
    }

    public function updatedSelectedTurfId($value)
    {
        session(['active_turf_id' => $value]);
        $this->dispatch('global-context-updated');
    }

    public function with()
    {
        $locations = Location::manageable()->orderBy('name', 'asc')->get();
        $turfs = $this->selectedLocationId 
            ? Turf::manageable()->where('location_id', $this->selectedLocationId)->orderBy('name', 'asc')->get() 
            : collect();

        return [
            'locations' => $locations,
            'turfs' => $turfs,
        ];
    }
}; ?>

<div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-2.5 w-full sm:w-auto">
    <!-- Location Selector -->
    @if ($locations->isEmpty())
        <div class="inline-flex items-center gap-2 bg-amber-50 border border-amber-200/80 rounded-xl px-3 py-1.5 text-xs font-semibold text-amber-700">
            <svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ __('No locations found') }}</span>
        </div>
    @else
        <div class="relative group flex items-center bg-white border border-gray-200/90 hover:border-indigo-300 rounded-xl px-2.5 py-1 shadow-2xs hover:shadow-xs transition-all duration-150">
            <!-- Location Icon Badge -->
            <div class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0 border border-indigo-100/60 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-150">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
            </div>

            <!-- Label + Select -->
            <div class="flex items-center pl-2">
                <label for="global_location_id" class="text-[10px] font-black uppercase tracking-wider text-gray-400 select-none mr-1.5 whitespace-nowrap">
                    {{ __('Location:') }}
                </label>
                <select 
                    id="global_location_id" 
                    wire:model.live="selectedLocationId" 
                    class="appearance-none bg-transparent border-none py-1 pl-0 pr-7 text-xs font-bold text-gray-800 hover:text-indigo-600 focus:text-indigo-600 focus:outline-none focus:ring-0 cursor-pointer transition-colors duration-150"
                    style="background-image: none;"
                >
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}" class="text-gray-900 bg-white font-medium py-1">{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Custom Chevron Down -->
            <div class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 group-hover:text-indigo-600 transition-colors duration-150">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
    @endif

    <!-- Breadcrumb Divider (visible on sm+) -->
    <div class="hidden sm:flex items-center text-gray-300">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
    </div>

    <!-- Turf Selector -->
    @if ($locations->isEmpty())
        <div class="inline-flex items-center gap-2 bg-gray-50 border border-gray-200/80 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-400">
            <span>{{ __('Select location first') }}</span>
        </div>
    @elseif ($turfs->isEmpty())
        <div class="inline-flex items-center gap-2 bg-gray-50 border border-gray-200/80 rounded-xl px-3 py-1.5 text-xs font-semibold text-gray-500">
            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="9" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01" />
            </svg>
            <span>{{ __('No turfs found') }}</span>
        </div>
    @else
        <div class="relative group flex items-center bg-white border border-gray-200/90 hover:border-emerald-300 rounded-xl px-2.5 py-1 shadow-2xs hover:shadow-xs transition-all duration-150">
            <!-- Turf Pitch Icon Badge -->
            <div class="w-6 h-6 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100/60 group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-150">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="2" />
                    <line x1="12" y1="4" x2="12" y2="20" stroke-width="1.75" />
                    <circle cx="12" cy="12" r="3" stroke-width="1.75" />
                </svg>
            </div>

            <!-- Label + Select -->
            <div class="flex items-center pl-2">
                <label for="global_turf_id" class="text-[10px] font-black uppercase tracking-wider text-gray-400 select-none mr-1.5 whitespace-nowrap">
                    {{ __('Turf:') }}
                </label>
                <select 
                    id="global_turf_id" 
                    wire:model.live="selectedTurfId" 
                    class="appearance-none bg-transparent border-none py-1 pl-0 pr-7 text-xs font-bold text-gray-800 hover:text-emerald-600 focus:text-emerald-600 focus:outline-none focus:ring-0 cursor-pointer transition-colors duration-150"
                    style="background-image: none;"
                >
                    @foreach ($turfs as $tf)
                        <option value="{{ $tf->id }}" class="text-gray-900 bg-white font-medium py-1">{{ $tf->name }} ({{ $tf->type }})</option>
                    @endforeach
                </select>
            </div>

            <!-- Custom Chevron Down -->
            <div class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 group-hover:text-emerald-600 transition-colors duration-150">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
    @endif
</div>
