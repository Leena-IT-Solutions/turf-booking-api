<?php

use App\Models\Equipment;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Search and filter
    public $search = '';

    // Form inputs
    public $equipmentId = null;
    public $name = '';
    public $icon = '';
    public $is_active = true;

    // States
    public $showFormModal = false;
    public $isEditing = false;
    public $deletingId = null;
    public $showDeleteConfirm = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function openCreateModal()
    {
        $this->reset(['equipmentId', 'name', 'icon', 'is_active']);
        $this->resetErrorBag();
        $this->isEditing = false;
        $this->showFormModal = true;
    }

    public function openEditModal($id)
    {
        $this->resetErrorBag();
        $this->isEditing = true;
        
        $equipment = Equipment::findOrFail($id);
        $this->equipmentId = $equipment->id;
        $this->name = $equipment->name;
        $this->icon = $equipment->icon;
        $this->is_active = $equipment->is_active;

        $this->showFormModal = true;
    }

    public function closeFormModal()
    {
        $this->showFormModal = false;
    }

    public function saveEquipment()
    {
        $this->validate([
            'name' => 'required|string|max:100|unique:equipments,name,' . ($this->equipmentId ?? 'NULL'),
            'icon' => 'nullable|string|max:50',
            'is_active' => 'required|boolean',
        ]);

        if ($this->isEditing) {
            $equipment = Equipment::findOrFail($this->equipmentId);
            $equipment->update([
                'name' => $this->name,
                'icon' => $this->icon,
                'is_active' => $this->is_active,
            ]);
            session()->flash('status', 'Equipment updated successfully.');
        } else {
            Equipment::create([
                'name' => $this->name,
                'icon' => $this->icon,
                'is_active' => $this->is_active,
            ]);
            session()->flash('status', 'Equipment created successfully.');
        }

        $this->closeFormModal();
    }

    public function toggleActive($id)
    {
        $equipment = Equipment::findOrFail($id);
        $equipment->update([
            'is_active' => !$equipment->is_active
        ]);
    }

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
            $equipment = Equipment::findOrFail($this->deletingId);
            $equipment->delete();
            session()->flash('status', 'Equipment deleted successfully.');
        }
        $this->cancelDelete();
    }

    public function with()
    {
        $equipments = Equipment::where('name', 'like', '%' . $this->search . '%')
            ->orderBy('name', 'asc')
            ->paginate(12);

        return [
            'equipments' => $equipments,
        ];
    }
}; ?>

<div class="py-6" x-data="{ formModal: @entangle('showFormModal'), deleteConfirm: @entangle('showDeleteConfirm') }">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Status Flash Message -->
        @if (session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 text-emerald-800 dark:text-emerald-400 text-sm font-medium shadow-sm transition">
                {{ session('status') }}
            </div>
        @endif

        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Global Equipments Repository') }}</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage master equipment and rental gear inventory available for select configurations at the individual turf field level.') }}</p>
            </div>
            <button type="button" wire:click="openCreateModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Add Equipment Option') }}
            </button>
        </div>

        <!-- Filter bar -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex items-center gap-4">
            <div class="flex-grow max-w-md relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="{{ __('Search global equipment names...') }}">
            </div>
        </div>

        @if ($equipments->isEmpty())
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/30 dark:border-indigo-950/20">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Equipments Options Found') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto leading-relaxed">{{ __('Add equipment options to populate the global selector repository.') }}</p>
            </div>
        @else
            <!-- Grid list of Equipment Options (Card Design) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($equipments as $equipmentItem)
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col justify-between hover:shadow-md transition duration-200">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Icon display -->
                            <div class="h-12 w-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100/50 dark:border-indigo-950/50 shrink-0">
                                <x-icon name="{{ $equipmentItem->icon }}" class="h-6 w-6" />
                            </div>
                            <!-- Card Actions -->
                            <div class="inline-flex items-center gap-1">
                                <button type="button" wire:click="openEditModal({{ $equipmentItem->id }})" class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-450 transition cursor-pointer" title="{{ __('Edit') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="confirmDelete({{ $equipmentItem->id }})" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition cursor-pointer" title="{{ __('Delete') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 line-clamp-2">
                                {{ $equipmentItem->name }}
                            </h3>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/50 flex items-center justify-between">
                            <!-- Toggle Active Button -->
                            <button type="button" wire:click="toggleActive({{ $equipmentItem->id }})" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[10px] font-bold cursor-pointer transition {{ $equipmentItem->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-100/50 dark:border-emerald-900/30' : 'bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-400 border border-gray-200 dark:border-gray-800' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $equipmentItem->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                {{ $equipmentItem->is_active ? __('Active') : __('Inactive') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if ($equipments->hasPages())
                <div class="mt-6 bg-white dark:bg-gray-800 px-6 py-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                    {{ $equipments->links() }}
                </div>
            @endif
        @endif

        <!-- Form Dialog (Create / Edit) -->
        <div x-show="formModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="formModal" @click="formModal = false" class="fixed inset-0 transition-opacity bg-gray-950/40 dark:bg-gray-950/60 backdrop-blur-sm"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div x-show="formModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100 dark:border-gray-700/50">
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ $isEditing ? __('Edit Global Equipment Option') : __('Add Global Equipment Option') }}
                            </h3>
                            <button type="button" @click="formModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 cursor-pointer">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveEquipment" class="space-y-4 mt-4">
                            <!-- Equipment Name -->
                            <div>
                                <label for="name" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Equipment Name') }}
                                </label>
                                <input type="text" id="name" wire:model="name" class="block w-full px-4 py-3 text-sm rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g. FIFA Pro Soccer Balls, Tennis Rackets">
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Icon Name or Emoji -->
                            <div>
                                <label for="icon" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Icon Name or Emoji') }}
                                </label>
                                <input type="text" id="icon" wire:model="icon" class="block w-full px-4 py-3 text-sm rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g. tennis-ball, soccer-ball, or 🎾, ⚽, 🥅">
                                <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2 leading-relaxed">
                                    Supported SVG names: <code class="bg-gray-55 dark:bg-gray-900 px-1 py-0.5 rounded text-indigo-500 font-mono text-[9px]">wifi, parking, shower, water, light, first-aid, coffee, seating, key, football, cricket, tennis, basketball, sun, sunset, moon</code>. Find emojis at <a href="https://emojipedia.org" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">Emojipedia</a>.
                                </p>
                            </div>

                            <!-- Is Active status toggle -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-55 dark:bg-gray-900 border border-gray-250 dark:border-gray-700">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-gray-850 dark:text-gray-200">{{ __('Equipment Active Status') }}</span>
                                    <span class="text-[10px] text-gray-400 mt-0.5">{{ __('Active options are visible to turf owners when configuration updates are made.') }}</span>
                                </div>
                                <button type="button" @click="@this.set('is_active', !@this.get('is_active'))" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none" :class="@this.get('is_active') ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'">
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="@this.get('is_active') ? 'translate-x-5' : 'translate-x-0'"></span>
                                </button>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" @click="formModal = false" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 border border-gray-250 dark:border-gray-655 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-500/20 transition cursor-pointer">
                                    {{ $isEditing ? __('Save Changes') : __('Create Option') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Dialog -->
        <div x-show="deleteConfirm" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="deleteConfirm" @click="deleteConfirm = false" class="fixed inset-0 transition-opacity bg-gray-950/40 dark:bg-gray-950/60 backdrop-blur-sm"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div x-show="deleteConfirm" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100 dark:border-gray-700/50">
                    <div class="p-6">
                        <div class="flex items-center gap-4">
                            <div class="h-10 w-10 shrink-0 rounded-xl bg-red-50 dark:bg-red-950/20 text-red-500 dark:text-red-400 flex items-center justify-center border border-red-100/50 dark:border-red-950/50">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                    {{ __('Delete Equipment Option') }}
                                </h3>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ __('Are you sure you want to permanently delete this master equipment option? This will delete all linkage and cannot be undone.') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                            <button type="button" @click="deleteConfirm = false" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 border border-gray-250 dark:border-gray-655 rounded-xl hover:bg-gray-55 dark:hover:bg-gray-700 transition cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" wire:click="performDelete" class="px-4 py-2 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl shadow-md shadow-red-500/20 transition cursor-pointer">
                                {{ __('Delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
