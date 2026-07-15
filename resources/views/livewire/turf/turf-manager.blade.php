<?php

use App\Models\Location;
use App\Models\Turf;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;
use Illuminate\Validation\Rule;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Search filter
    public $search = '';

    // Form inputs
    public $location_id = '';
    public $name = '';
    public $type = 'Synthetic';
    public $description = '';
    public $area = '';
    public $is_active = true;
    public $equipments = '';

    // Modal state
    public $editingId = null;
    public $showModal = false;

    // Delete confirmation state
    public $deletingId = null;
    public $showDeleteConfirm = false;

    public function confirmDelete($id)
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function cancelDelete()
    {
        $this->deletingId = null;
        $this->showDeleteConfirm = false;
    }

    public function performDelete()
    {
        if ($this->deletingId) {
            $this->deleteTurf($this->deletingId);
        }
        $this->cancelDelete();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        // Pre-select first location if available
        $firstLocation = Location::where('user_id', auth()->id())->first();
        if ($firstLocation) {
            $this->location_id = $firstLocation->id;
        }
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset(['location_id', 'name', 'type', 'description', 'area', 'is_active', 'equipments', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = false;
    }

    public function editTurf($id)
    {
        $this->resetForm();
        $turf = Turf::whereHas('location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);

        $this->editingId = $turf->id;
        $this->location_id = $turf->location_id;
        $this->name = $turf->name;
        $this->type = $turf->type;
        $this->description = $turf->description;
        $this->area = $turf->area;
        $this->is_active = $turf->is_active;
        $this->equipments = $turf->equipments;
        $this->showModal = true;
    }

    public function updated($propertyName)
    {
        $rules = [
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                })
            ],
            'name' => 'required|string|max:150',
            'type' => 'required|in:Synthetic,Hard,Other',
            'description' => 'nullable|string|max:1000',
            'area' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'equipments' => 'nullable|string|max:500',
        ];

        $this->validateOnly($propertyName, $rules);
    }

    public function saveTurf()
    {
        $rules = [
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                })
            ],
            'name' => 'required|string|max:150',
            'type' => 'required|in:Synthetic,Hard,Other',
            'description' => 'nullable|string|max:1000',
            'area' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'equipments' => 'nullable|string|max:500',
        ];

        $this->validate($rules);

        $data = [
            'location_id' => $this->location_id,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description ?: null,
            'area' => $this->area ?: null,
            'is_active' => $this->is_active,
            'equipments' => $this->equipments ?: null,
        ];

        if ($this->editingId) {
            $turf = Turf::whereHas('location', function ($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($this->editingId);
            $turf->update($data);
            session()->flash('status', 'Turf updated successfully.');
        } else {
            Turf::create($data);
            session()->flash('status', 'Turf created successfully.');
        }

        $this->resetForm();
    }

    public function deleteTurf($id)
    {
        $turf = Turf::whereHas('location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);
        
        $turf->delete();
        session()->flash('status', 'Turf deleted successfully.');
    }

    public function with()
    {
        $query = Turf::whereHas('location', function ($q) {
            $q->where('user_id', auth()->id());
        });

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%')
                  ->orWhere('area', 'like', '%' . $this->search . '%')
                  ->orWhere('equipments', 'like', '%' . $this->search . '%')
                  ->orWhereHas('location', function ($l) {
                      $l->where('name', 'like', '%' . $this->search . '%');
                  });
            });
        }

        return [
            'turfs' => $query->orderBy('name', 'asc')->paginate(8),
            'availableLocations' => Location::where('user_id', auth()->id())->orderBy('name', 'asc')->get(),
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Card -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Turfs') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Configure playing courts, surface specifications, dimensions, and included sport equipment.') }}</p>
            </div>
            <div>
                @if ($availableLocations->isEmpty())
                    <a href="{{ route('turf.locations') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ __('Add Location First') }}
                    </a>
                @else
                    <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ __('Add Turf') }}
                    </button>
                @endif
            </div>
        </div>

        <!-- Session Status Flash Messages -->
        @if (session()->has('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-4 rounded-2xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Filter Search Bar -->
        <div class="relative bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
            <div class="absolute inset-y-0 start-4 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400 dark:text-gray-500 ms-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Search turfs by name, type, area, or location...') }}"
                class="w-full pl-12 pr-4 py-2.5 bg-gray-50 dark:bg-gray-900/40 text-xs font-semibold text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/80 transition duration-150"
            />
        </div>

        <!-- Turfs Grid (2 Columns Max) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @forelse ($turfs as $turf)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between relative group">
                    <div>
                        <div class="flex items-start justify-between gap-4 min-w-0 w-full">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 truncate">
                                        {{ $turf->name }}
                                    </h3>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-1 flex items-center gap-1">
                                        <svg class="h-3 w-3 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate">{{ $turf->location->name }}</span>
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0">
                                <!-- Edit Button -->
                                <button wire:click="editTurf({{ $turf->id }})" class="p-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 text-indigo-600 dark:text-indigo-400 rounded-xl transition cursor-pointer border border-gray-100 dark:border-gray-600 flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                
                                <!-- Delete Button -->
                                <button wire:click="confirmDelete({{ $turf->id }})" class="p-2 bg-gray-50 hover:bg-red-50 dark:bg-gray-700/50 dark:hover:bg-red-950/20 text-red-500 dark:text-red-400 rounded-xl transition cursor-pointer border border-gray-100 dark:border-gray-600 flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Turf Details -->
                        <div class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-700/40 space-y-3">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-400 dark:text-gray-500 font-medium">{{ __('Surface Type:') }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{
                                    $turf->type === 'Synthetic' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : (
                                    $turf->type === 'Hard' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' :
                                    'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400')
                                }}">
                                    {{ $turf->type }}
                                </span>
                            </div>

                            @if($turf->area)
                                <div class="flex items-center justify-between text-xs">
                                    <span class="text-gray-400 dark:text-gray-500 font-medium">{{ __('Dimensions/Area:') }}</span>
                                    <span class="text-gray-600 dark:text-gray-300 font-semibold">{{ $turf->area }}</span>
                                </div>
                            @endif

                            @if($turf->description)
                                <div class="text-xs pt-1">
                                    <span class="text-gray-400 dark:text-gray-500 font-medium block mb-1">{{ __('Description:') }}</span>
                                    <p class="text-gray-600 dark:text-gray-450 leading-relaxed font-medium bg-gray-50 dark:bg-gray-900/20 p-2.5 rounded-2xl border border-gray-100/50 dark:border-gray-800">
                                        {{ $turf->description }}
                                    </p>
                                </div>
                            @endif

                            <!-- Equipments Badge List -->
                            @if($turf->equipments)
                                <div class="pt-1">
                                    <span class="text-gray-400 dark:text-gray-500 font-medium block mb-1.5 text-xs">{{ __('Included Equipments:') }}</span>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach(array_filter(array_map('trim', explode(',', $turf->equipments))) as $equip)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[9px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200/10">
                                                {{ $equip }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Footer Status Indicators -->
                    <div class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-700/40 flex items-center justify-between">
                        <span class="text-[9px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">
                            {{ __('Turf status') }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full {{ $turf->is_active ? 'bg-emerald-500 shadow-emerald-500/30' : 'bg-red-500 shadow-red-500/30' }} shadow-md"></span>
                            <span class="text-[10px] font-extrabold uppercase tracking-wide {{ $turf->is_active ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                {{ $turf->is_active ? __('Active') : __('Inactive') }}
                            </span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-100 dark:border-gray-700/50 text-center">
                    <div class="h-12 w-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Turfs Found') }}</h3>
                    <p class="text-xs text-gray-400 mt-2">{{ __('Try searching with different terms or add a new playing turf profile.') }}</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination Links -->
        <div class="pt-4">
            {{ $turfs->links() }}
        </div>
    </div>

    <!-- Create/Edit Modal Dialog -->
    <div 
        x-data="{ open: @entangle('showModal') }" 
        x-show="open" 
        style="display: none;"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
    >
        <!-- Backdrop -->
        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="@this.call('resetForm')"
            class="fixed inset-0 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-sm transition-opacity"
        ></div>

        <!-- Modal Content Box -->
        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-xl overflow-hidden w-full max-w-lg border border-gray-100 dark:border-gray-700/50 p-6 z-10 transition-all flex flex-col gap-4"
        >
            <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700/50">
                <h3 class="text-md font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">
                    {{ $editingId ? __('Edit Turf') : __('Add New Turf') }}
                </h3>
                <button @click="@this.call('resetForm')" class="text-gray-400 hover:text-gray-500 dark:text-gray-500 dark:hover:text-gray-400 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l18 18" />
                    </svg>
                </button>
            </div>

            <!-- Form -->
            <form wire:submit="saveTurf" class="space-y-4">
                <!-- Location ID selection -->
                <div>
                    <x-input-label for="location_id" :value="__('Select Location')" />
                    <select 
                        id="location_id" 
                        wire:model="location_id" 
                        class="w-full mt-1.5 px-4 py-2.5 text-xs font-semibold bg-gray-50 dark:bg-gray-900/50 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/80 transition duration-150"
                    >
                        @foreach ($availableLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('location_id')" class="mt-2" />
                </div>

                <!-- Turf Name -->
                <div>
                    <x-input-label for="name" :value="__('Turf Name')" />
                    <x-text-input 
                        id="name" 
                        type="text" 
                        wire:model="name" 
                        placeholder="{{ __('e.g., BKC Main Turf A') }}" 
                        class="w-full mt-1.5" 
                    />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <!-- Turf Type -->
                <div>
                    <x-input-label for="type" :value="__('Turf Type')" />
                    <select 
                        id="type" 
                        wire:model="type" 
                        class="w-full mt-1.5 px-4 py-2.5 text-xs font-semibold bg-gray-50 dark:bg-gray-900/50 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/80 transition duration-150"
                    >
                        <option value="Synthetic">{{ __('Synthetic') }}</option>
                        <option value="Hard">{{ __('Hard') }}</option>
                        <option value="Other">{{ __('Other') }}</option>
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>

                <!-- Area Size -->
                <div>
                    <x-input-label for="area" :value="__('Dimensions / Area Size')" />
                    <x-text-input 
                        id="area" 
                        type="text" 
                        wire:model="area" 
                        placeholder="{{ __('e.g., 8,000 sq ft or 120 x 80 ft') }}" 
                        class="w-full mt-1.5" 
                    />
                    <x-input-error :messages="$errors->get('area')" class="mt-2" />
                </div>

                <!-- Description -->
                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea 
                        id="description" 
                        wire:model="description" 
                        placeholder="{{ __('Describe details about this playing surface...') }}" 
                        rows="3"
                        class="w-full mt-1.5 px-4 py-2.5 text-xs font-semibold bg-gray-50 dark:bg-gray-900/50 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/80 transition duration-150 resize-none"
                    ></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <!-- Equipments list -->
                <div>
                    <x-input-label for="equipments" :value="__('Included Equipments (comma-separated)')" />
                    <x-text-input 
                        id="equipments" 
                        type="text" 
                        wire:model="equipments" 
                        placeholder="{{ __('e.g., Football, Goals, Cricket Bat, Stumps') }}" 
                        class="w-full mt-1.5" 
                    />
                    <x-input-error :messages="$errors->get('equipments')" class="mt-2" />
                </div>

                <!-- Active Status Toggle -->
                <div class="flex items-center gap-3 py-1">
                    <input 
                        id="is_active" 
                        type="checkbox" 
                        wire:model="is_active" 
                        class="h-4.5 w-4.5 rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500/20 dark:focus:ring-offset-gray-800 transition cursor-pointer"
                    />
                    <label for="is_active" class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider cursor-pointer">
                        {{ __('Active & Available for Booking') }}
                    </label>
                    <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
                </div>

                <!-- Submit and Cancel Buttons -->
                <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                    <button 
                        type="button" 
                        @click="@this.call('resetForm')" 
                        class="px-4 py-2 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-700 dark:text-gray-300 font-bold text-[10px] uppercase tracking-wider rounded-xl transition duration-150 cursor-pointer"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        type="submit" 
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow cursor-pointer"
                    >
                        {{ $editingId ? __('Save Changes') : __('Create Turf') }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Custom Alpine.js Delete Confirmation Modal -->
    <div 
        x-data="{ open: @entangle('showDeleteConfirm') }" 
        x-show="open" 
        style="display: none;"
        class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4"
    >
        <!-- Backdrop -->
        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="@this.call('cancelDelete')"
            class="fixed inset-0 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-sm transition-opacity"
        ></div>

        <!-- Modal Dialog -->
        <div 
            x-show="open" 
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-xl overflow-hidden w-full max-w-sm border border-gray-100 dark:border-gray-700/50 p-6 z-10 transition-all flex flex-col gap-4 text-center"
        >
            <div class="h-12 w-12 rounded-2xl bg-red-50 dark:bg-red-950/20 text-red-500 dark:text-red-400 flex items-center justify-center mx-auto">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            
            <div>
                <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider">
                    {{ __('Delete Turf') }}
                </h3>
                <p class="text-xs text-gray-400 mt-2">
                    {{ __('Are you sure you want to permanently delete this Turf? All associated slot setups and transaction records will be permanently removed. This action cannot be undone.') }}
                </p>
            </div>

            <div class="flex items-center justify-center gap-2.5 pt-2">
                <button 
                    type="button" 
                    @click="@this.call('cancelDelete')" 
                    class="px-4 py-2 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 text-gray-700 dark:text-gray-300 font-bold text-[10px] uppercase tracking-wider rounded-xl transition duration-150 cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>
                <button 
                    type="button" 
                    wire:click="performDelete" 
                    class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white font-bold text-[10px] uppercase tracking-wider rounded-xl shadow cursor-pointer"
                >
                    {{ __('Confirm Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
