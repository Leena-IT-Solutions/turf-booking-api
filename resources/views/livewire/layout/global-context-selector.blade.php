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
        $locations = Location::where('user_id', auth()->id())->orderBy('name', 'asc')->get();

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
        $turfs = Turf::where('location_id', $this->selectedLocationId)->orderBy('name', 'asc')->get();
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
        
        $firstTurf = Turf::where('location_id', $value)->orderBy('name', 'asc')->first();
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
        $locations = Location::where('user_id', auth()->id())->orderBy('name', 'asc')->get();
        $turfs = $this->selectedLocationId 
            ? Turf::where('location_id', $this->selectedLocationId)->orderBy('name', 'asc')->get() 
            : collect();

        return [
            'locations' => $locations,
            'turfs' => $turfs,
        ];
    }
}; ?>

<div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full sm:w-auto">
    <!-- Location Selector -->
    <div class="flex items-center gap-2 w-full sm:w-auto">
        <label for="global_location_id" class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-wider whitespace-nowrap">
            {{ __('Location:') }}
        </label>
        @if ($locations->isEmpty())
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 italic">
                {{ __('No locations found') }}
            </span>
        @else
            <select 
                id="global_location_id" 
                wire:model.live="selectedLocationId" 
                class="px-3.5 py-1.5 text-xs font-bold bg-gray-50 dark:bg-gray-900/50 text-indigo-600 dark:text-indigo-400 border border-gray-100 dark:border-gray-700/80 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition duration-150 cursor-pointer"
            >
                @foreach ($locations as $loc)
                    <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <!-- Turf Selector -->
    <div class="flex items-center gap-2 w-full sm:w-auto">
        <label for="global_turf_id" class="text-[10px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-wider whitespace-nowrap">
            {{ __('Turf:') }}
        </label>
        @if ($locations->isEmpty())
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 italic">
                {{ __('Select location first') }}
            </span>
        @elseif ($turfs->isEmpty())
            <span class="text-xs font-semibold text-gray-400 dark:text-gray-500 italic">
                {{ __('No turfs found') }}
            </span>
        @else
            <select 
                id="global_turf_id" 
                wire:model.live="selectedTurfId" 
                class="px-3.5 py-1.5 text-xs font-bold bg-gray-50 dark:bg-gray-900/50 text-indigo-600 dark:text-indigo-400 border border-gray-100 dark:border-gray-700/80 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition duration-150 cursor-pointer"
            >
                @foreach ($turfs as $tf)
                    <option value="{{ $tf->id }}">{{ $tf->name }} ({{ $tf->type }})</option>
                @endforeach
            </select>
        @endif
    </div>
</div>
