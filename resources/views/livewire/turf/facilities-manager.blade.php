<?php

use App\Models\Turf;
use App\Models\TurfFacility;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Form properties
    public $facilityId = null;
    public $facility = '';
    public $is_active = true;

    // Modals visibility state
    public $showFormModal = false;
    public $isEditing = false;
    public $deletingId = null;
    public $showDeleteConfirm = false;

    #[On('global-context-updated')]
    public function refreshFacilities()
    {
        // Dynamic re-render on active context change
    }

    public function openCreateModal()
    {
        $this->reset(['facilityId', 'facility', 'is_active']);
        $this->resetErrorBag();
        $this->isEditing = false;
        $this->showFormModal = true;
    }

    public function openEditModal($id)
    {
        $this->resetErrorBag();
        $this->isEditing = true;
        
        $item = TurfFacility::whereHas('turf.location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);

        $this->facilityId = $item->id;
        $this->facility = $item->facility;
        $this->is_active = $item->is_active;

        $this->showFormModal = true;
    }

    public function closeFormModal()
    {
        $this->showFormModal = false;
    }

    public function saveFacility()
    {
        $this->validate([
            'facility' => 'required|string|max:100',
            'is_active' => 'required|boolean',
        ]);

        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) {
            $this->closeFormModal();
            return;
        }

        // Verify user owns the turf
        $turf = Turf::whereHas('location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($activeTurfId);

        if ($this->isEditing) {
            $item = TurfFacility::whereHas('turf.location', function ($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($this->facilityId);

            $item->update([
                'facility' => $this->facility,
                'is_active' => $this->is_active,
            ]);

            session()->flash('status', 'Facility updated successfully.');
        } else {
            TurfFacility::create([
                'turf_id' => $turf->id,
                'facility' => $this->facility,
                'is_active' => $this->is_active,
            ]);

            session()->flash('status', 'Facility added successfully.');
        }

        $this->closeFormModal();
    }

    public function toggleActive($id)
    {
        $item = TurfFacility::whereHas('turf.location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);

        $item->update([
            'is_active' => !$item->is_active
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
            $item = TurfFacility::whereHas('turf.location', function ($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($this->deletingId);

            $item->delete();
            session()->flash('status', 'Facility deleted successfully.');
        }

        $this->cancelDelete();
    }

    public function with()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;
        $facilities = collect();

        if ($activeTurfId) {
            $turf = Turf::whereHas('location', function ($q) {
                $q->where('user_id', auth()->id());
            })->find($activeTurfId);

            if ($turf) {
                $facilities = TurfFacility::where('turf_id', $turf->id)->orderBy('facility', 'asc')->get();
            }
        }

        return [
            'turf' => $turf,
            'facilities' => $facilities,
        ];
    }
}; ?>

<div class="py-6" x-data="{ formModal: @entangle('showFormModal'), deleteConfirm: @entangle('showDeleteConfirm') }">
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
                <p class="text-sm text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to manage facilities.') }}</p>
            </div>
        @else
            <!-- Header Section -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Facilities for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage physical facilities, amenities, changing rooms, floodlights, or washrooms associated with this turf.') }}</p>
                </div>
                <button type="button" wire:click="openCreateModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Facility') }}
                </button>
            </div>

            @if ($facilities->isEmpty())
                <!-- Empty State -->
                <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                    <div class="h-16 w-16 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/30 dark:border-indigo-950/20">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Facilities Found') }}</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto leading-relaxed">{{ __('Add facilities to inform customers about the changing rooms, lights, or parking amenities available.') }}</p>
                </div>
            @else
                <!-- Facilities List Table -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-750">
                            <thead class="bg-gray-50/55 dark:bg-gray-900/10">
                                <tr>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Facility Name') }}</th>
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Status') }}</th>
                                    <th scope="col" class="px-6 py-4 class text-right text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider pr-8">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-750 bg-transparent">
                                @foreach ($facilities as $facilityItem)
                                    <tr class="hover:bg-gray-50/30 dark:hover:bg-gray-800/10 transition">
                                        <td class="px-6 py-4.5 whitespace-nowrap text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $facilityItem->facility }}
                                        </td>
                                        <td class="px-6 py-4.5 whitespace-nowrap">
                                            <button type="button" wire:click="toggleActive({{ $facilityItem->id }})" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold cursor-pointer transition {{ $facilityItem->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30' : 'bg-gray-55 text-gray-500 dark:bg-gray-900 dark:text-gray-400 border border-gray-200 dark:border-gray-800' }}">
                                                <span class="h-1.5 w-1.5 rounded-full {{ $facilityItem->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                                {{ $facilityItem->is_active ? __('Active') : __('Inactive') }}
                                            </button>
                                        </td>
                                        <td class="px-6 py-4.5 whitespace-nowrap text-right text-xs font-medium pr-8">
                                            <div class="inline-flex items-center gap-2">
                                                <button type="button" wire:click="openEditModal({{ $facilityItem->id }})" class="p-2 text-gray-500 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-400 transition cursor-pointer" title="{{ __('Edit') }}">
                                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button type="button" wire:click="confirmDelete({{ $facilityItem->id }})" class="p-2 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-400 transition cursor-pointer" title="{{ __('Delete') }}">
                                                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        @endif

        <!-- Form Dialog (Create / Edit) -->
        <div x-show="formModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="formModal" @click="formModal = false" class="fixed inset-0 transition-opacity bg-gray-950/40 dark:bg-gray-950/60 backdrop-blur-sm"></div>

                <!-- Center helper -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div x-show="formModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100 dark:border-gray-700/50">
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ $isEditing ? __('Edit Facility') : __('Add New Facility') }}
                            </h3>
                            <button type="button" @click="formModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 cursor-pointer">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveFacility" class="space-y-4 mt-4">
                            <!-- Facility Name -->
                            <div>
                                <label for="facility" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Facility Name') }}
                                </label>
                                <input type="text" id="facility" wire:model="facility" class="block w-full px-4 py-3 text-sm rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g. Locker Room, Free Parking">
                                <x-input-error :messages="$errors->get('facility')" class="mt-2" />
                            </div>

                            <!-- Is Active status toggle -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Facility Active Status') }}</span>
                                    <span class="text-[10px] text-gray-400 mt-0.5">{{ __('Active facilities are visible to end users during booking.') }}</span>
                                </div>
                                <button type="button" @click="@this.set('is_active', !@this.get('is_active'))" class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none" :class="@this.get('is_active') ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'">
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out" :class="@this.get('is_active') ? 'translate-x-5' : 'translate-x-0'"></span>
                                </button>
                            </div>

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" @click="formModal = false" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 border border-gray-255 dark:border-gray-655 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 rounded-xl shadow-md shadow-indigo-500/20 transition cursor-pointer">
                                    {{ $isEditing ? __('Save Changes') : __('Create Facility') }}
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

                <!-- Center element helper -->
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
                                    {{ __('Delete Facility') }}
                                </h3>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ __('Are you sure you want to permanently delete this facility? This will remove the facility linkage and cannot be undone.') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                            <button type="button" @click="deleteConfirm = false" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 border border-gray-250 dark:border-gray-650 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer">
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
