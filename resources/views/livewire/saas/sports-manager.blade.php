<?php

use App\Models\Sport;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Search and filter
    public $search = '';

    // Form inputs
    public $sportId = null;
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
        $this->reset(['sportId', 'name', 'icon', 'is_active']);
        $this->resetErrorBag();
        $this->isEditing = false;
        $this->showFormModal = true;
    }

    public function openEditModal($id)
    {
        $this->resetErrorBag();
        $this->isEditing = true;
        
        $sport = Sport::findOrFail($id);
        $this->sportId = $sport->id;
        $this->name = $sport->name;
        $this->icon = $sport->icon;
        $this->is_active = $sport->is_active;

        $this->showFormModal = true;
    }

    public function closeFormModal()
    {
        $this->showFormModal = false;
    }

    public $aiGenerating = false;

    public function generateIcon()
    {
        $this->validateOnly('name', [
            'name' => 'required|string|max:100',
        ]);

        $this->aiGenerating = true;
        $this->resetErrorBag('icon');

        try {
            $this->icon = \App\Services\GeminiService::generateSvgIcon($this->name);
            session()->flash('status', __('AI SVG Icon generated successfully. Preview it below.'));
        } catch (\Exception $e) {
            $this->addError('icon', $e->getMessage());
        } finally {
            $this->aiGenerating = false;
        }
    }

    public function saveSport()
    {
        $this->validate([
            'name' => 'required|string|max:100|unique:sports,name,' . ($this->sportId ?? 'NULL'),
            'icon' => 'nullable|string|max:5000',
            'is_active' => 'required|boolean',
        ]);

        if ($this->isEditing) {
            $sport = Sport::findOrFail($this->sportId);
            $sport->update([
                'name' => $this->name,
                'icon' => $this->icon,
                'is_active' => $this->is_active,
            ]);
            session()->flash('status', 'Sport updated successfully.');
        } else {
            Sport::create([
                'name' => $this->name,
                'icon' => $this->icon,
                'is_active' => $this->is_active,
            ]);
            session()->flash('status', 'Sport created successfully.');
        }

        $this->closeFormModal();
    }

    public function toggleActive($id)
    {
        $sport = Sport::findOrFail($id);
        $sport->update([
            'is_active' => !$sport->is_active
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
            $sport = Sport::findOrFail($this->deletingId);
            $sport->delete();
            session()->flash('status', 'Sport deleted successfully.');
        }
        $this->cancelDelete();
    }

    public function with()
    {
        $sports = Sport::where('name', 'like', '%' . $this->search . '%')
            ->orderBy('name', 'asc')
            ->paginate(12);

        return [
            'sports' => $sports,
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

        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Global Sports Repository') }}</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage master sports categories (e.g. Football 5-a-side, Box Cricket) available for select configurations at the individual turf field level.') }}</p>
            </div>
            <button type="button" wire:click="openCreateModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Add Sport Option') }}
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
                <input type="text" wire:model.live.debounce.300ms="search" class="block w-full pl-10 pr-4 py-2.5 text-xs rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="{{ __('Search global sports names...') }}">
            </div>
        </div>

        @if ($sports->isEmpty())
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                <div class="h-16 w-16 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/30 dark:border-indigo-950/20">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h2a2.5 2.5 0 002.5-2.5V10a2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Sports Options Found') }}</h3>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto leading-relaxed">{{ __('Add sports options to populate the global selector repository.') }}</p>
            </div>
        @else
            <!-- Grid list of Sport Options (Card Design) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @foreach ($sports as $sportItem)
                    <div class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col justify-between hover:shadow-md transition duration-200">
                        <div class="flex items-start justify-between gap-4">
                            <!-- Icon display -->
                            <div class="h-12 w-12 rounded-2xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center border border-indigo-100/50 dark:border-indigo-950/50 shrink-0">
                                <x-icon name="{{ $sportItem->icon }}" class="h-6 w-6" />
                            </div>
                            <!-- Card Actions -->
                            <div class="inline-flex items-center gap-1">
                                <button type="button" wire:click="openEditModal({{ $sportItem->id }})" class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-450 transition cursor-pointer" title="{{ __('Edit') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </button>
                                <button type="button" wire:click="confirmDelete({{ $sportItem->id }})" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition cursor-pointer" title="{{ __('Delete') }}">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 line-clamp-2">
                                {{ $sportItem->name }}
                            </h3>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100 dark:border-gray-700/50 flex items-center justify-between">
                            <!-- Toggle Active Button -->
                            <button type="button" wire:click="toggleActive({{ $sportItem->id }})" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[10px] font-bold cursor-pointer transition {{ $sportItem->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400 border border-emerald-100/50 dark:border-emerald-900/30' : 'bg-gray-50 text-gray-500 dark:bg-gray-900 dark:text-gray-400 border border-gray-200 dark:border-gray-800' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $sportItem->is_active ? 'bg-emerald-500' : 'bg-gray-400' }}"></span>
                                {{ $sportItem->is_active ? __('Active') : __('Inactive') }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Pagination -->
            @if ($sports->hasPages())
                <div class="mt-6 bg-white dark:bg-gray-800 px-6 py-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                    {{ $sports->links() }}
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
                                {{ $isEditing ? __('Edit Global Sport Option') : __('Add Global Sport Option') }}
                            </h3>
                            <button type="button" @click="formModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 cursor-pointer">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveSport" class="space-y-4 mt-4">
                            <!-- Sport Name -->
                            <div>
                                <label for="name" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Sport Name') }}
                                </label>
                                <input type="text" id="name" wire:model="name" class="block w-full px-4 py-3 text-sm rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g. Football (5-a-side), Box Cricket">
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Icon Name, Emoji or SVG -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label for="icon" class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">
                                        {{ __('Icon Name, Emoji, or SVG') }}
                                    </label>
                                    <button type="button" wire:click="generateIcon" class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 flex items-center gap-1 transition cursor-pointer" wire:loading.attr="disabled" wire:target="generateIcon">
                                        <svg class="h-3 w-3 animate-spin text-indigo-600 dark:text-indigo-400" wire:loading wire:target="generateIcon" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <svg wire:loading.remove wire:target="generateIcon" class="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 21m0 0l-.813-5.096L3 15m6 6l6-6m-9-9V3m0 0l-.813 5.096L3 9m6-6l6 6M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span wire:loading.remove wire:target="generateIcon">{{ __('Generate SVG with AI') }}</span>
                                        <span wire:loading wire:target="generateIcon">{{ __('Generating SVG...') }}</span>
                                    </button>
                                </div>
                                <div class="flex gap-2">
                                    <div class="relative flex-grow">
                                        <input type="text" id="icon" wire:model="icon" class="block w-full px-4 py-3 text-xs font-mono rounded-xl bg-gray-55 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition duration-150 ease-in-out" placeholder="e.g. football, or paste raw <svg>...</svg>">
                                    </div>
                                    @if ($icon)
                                        <div class="h-[46px] w-[46px] bg-gray-55 dark:bg-gray-900 border border-gray-250 dark:border-gray-700 rounded-2xl flex items-center justify-center shrink-0">
                                            <x-icon name="{{ $icon }}" class="h-5 w-5 text-gray-700 dark:text-gray-300" />
                                        </div>
                                    @endif
                                </div>
                                <x-input-error :messages="$errors->get('icon')" class="mt-2" />
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-2 leading-relaxed">
                                    Supported pre-compiled names: <code class="bg-gray-55 dark:bg-gray-900 px-1 py-0.5 rounded text-indigo-500 font-mono text-[9px]">wifi, parking, shower, water, light, first-aid, coffee, seating, key, football, cricket, tennis, basketball, sun, sunset, moon</code>. Find emojis at <a href="https://emojipedia.org" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline">Emojipedia</a>, or click the AI button to generate a custom SVG vector instantly using Gemini.
                                </p>
                            </div>

                            <!-- Is Active status toggle -->
                            <div class="flex items-center justify-between p-3 rounded-2xl bg-gray-55 dark:bg-gray-900 border border-gray-250 dark:border-gray-700">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-gray-850 dark:text-gray-200">{{ __('Sport Active Status') }}</span>
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
                                    {{ __('Delete Sport Option') }}
                                </h3>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ __('Are you sure you want to permanently delete this master sport option? This will delete all linkage and cannot be undone.') }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                            <button type="button" @click="deleteConfirm = false" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 border border-gray-255 dark:border-gray-655 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer">
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
