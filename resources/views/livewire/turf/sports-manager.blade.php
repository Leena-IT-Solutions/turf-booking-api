<?php

use App\Models\Turf;
use App\Models\Sport;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public $selectedSportIds = [];

    #[On('global-context-updated')]
    public function refreshSports()
    {
        $this->loadSelectedSports();
    }

    public function mount()
    {
        $this->loadSelectedSports();
    }

    public function loadSelectedSports()
    {
        $activeTurfId = session('active_turf_id');
        if ($activeTurfId) {
            $turf = Turf::whereHas('location', function ($q) {
                $q->where('user_id', auth()->id());
            })->find($activeTurfId);

            if ($turf) {
                $this->selectedSportIds = $turf->sports->pluck('id')->map(fn($id) => (string)$id)->toArray();
            } else {
                $this->selectedSportIds = [];
            }
        } else {
            $this->selectedSportIds = [];
        }
    }

    public function saveSports()
    {
        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) {
            return;
        }

        $turf = Turf::whereHas('location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($activeTurfId);

        // Filter selected IDs to ensure they are valid active sports
        $validIds = Sport::where('is_active', true)
            ->whereIn('id', $this->selectedSportIds)
            ->pluck('id')
            ->toArray();

        $turf->sports()->sync($validIds);

        session()->flash('status', 'Sports updated successfully.');
    }

    public function with()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;

        if ($activeTurfId) {
            $turf = Turf::whereHas('location', function ($q) {
                $q->where('user_id', auth()->id());
            })->find($activeTurfId);
        }

        // Fetch all active global master sports
        $allSports = Sport::where('is_active', true)->orderBy('name', 'asc')->get();

        return [
            'turf' => $turf,
            'allSports' => $allSports,
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Status Flash Message -->
        @if (session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 text-emerald-800 dark:text-emerald-400 text-sm font-medium shadow-sm transition">
                {{ session('status') }}
            </div>
        @endif

        @if (!$turf)
            <!-- Warning Alert when Turf is not selected/found -->
            <div class="bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-500 dark:text-amber-400 flex items-center justify-center mx-auto mb-4 border border-amber-100/50 dark:border-amber-950/50">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ __('No Turf Selected') }}</h3>
                <p class="text-sm text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to select sports.') }}</p>
            </div>
        @else
            <!-- Form Card Wrapper -->
            <form wire:submit="saveSports" class="space-y-6">
                <!-- Header Section -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Sports for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Select what sports categories or match formats are supported at this turf court.') }}</p>
                    </div>
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ __('Save Sports') }}
                    </button>
                </div>

                @if ($allSports->isEmpty())
                    <!-- Empty State -->
                    <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-750/50 shadow-sm text-center">
                        <div class="h-16 w-16 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/30 dark:border-indigo-950/20">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h2a2.5 2.5 0 002.5-2.5V10a2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Global Sports Configured') }}</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto leading-relaxed">{{ __('Please ask the SaaS Administrator to add sports to the global repository first.') }}</p>
                    </div>
                @else
                    <!-- Checkbox Grid -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($allSports as $sportItem)
                                <label class="relative flex items-center p-4 rounded-2xl border transition duration-150 cursor-pointer {{ in_array((string)$sportItem->id, $selectedSportIds) ? 'bg-indigo-50/20 border-indigo-250 dark:bg-indigo-950/10 dark:border-indigo-900/50' : 'bg-transparent border-gray-100 hover:bg-gray-50/50 dark:border-gray-700 dark:hover:bg-gray-800/30' }}">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" wire:model="selectedSportIds" value="{{ $sportItem->id }}" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500/20 dark:border-gray-750 dark:bg-gray-900 dark:checked:bg-indigo-600 transition">
                                    </div>
                                    <div class="ms-3 flex items-center gap-3">
                                        <div class="h-9 w-9 bg-gray-50 dark:bg-gray-900 flex items-center justify-center rounded-xl text-gray-500 dark:text-gray-400 border border-gray-200/50 dark:border-gray-750 shrink-0">
                                            <x-icon name="{{ $sportItem->icon }}" class="h-5 w-5" />
                                        </div>
                                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $sportItem->name }}</span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </form>
        @endif

    </div>
</div>
