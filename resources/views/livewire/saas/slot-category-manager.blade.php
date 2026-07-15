<?php

use App\Models\SlotCategory;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filter and search
    public $search = '';

    // Form inputs
    public $name = '';
    public $is_active = true;

    // Editing state
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
            $this->deleteCategory($this->deletingId);
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
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset(['name', 'is_active', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = false;
    }

    public function editCategory($id)
    {
        $this->resetForm();
        $category = SlotCategory::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->is_active = $category->is_active;
        $this->showModal = true;
    }

    public function updated($propertyName)
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('slot_categories')->ignore($this->editingId),
            ],
            'is_active' => 'boolean',
        ];

        $this->validateOnly($propertyName, $rules);
    }

    public function saveCategory()
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('slot_categories')->ignore($this->editingId),
            ],
            'is_active' => 'boolean',
        ];

        $this->validate($rules);

        if ($this->editingId) {
            $category = SlotCategory::findOrFail($this->editingId);
            $category->update([
                'name' => $this->name,
                'is_active' => $this->is_active,
            ]);

            session()->flash('status', 'Slot category updated successfully.');
        } else {
            SlotCategory::create([
                'name' => $this->name,
                'is_active' => $this->is_active,
            ]);

            session()->flash('status', 'Slot category created successfully.');
        }

        $this->resetForm();
    }

    public function toggleStatus($id)
    {
        $category = SlotCategory::findOrFail($id);
        $category->update([
            'is_active' => !$category->is_active,
        ]);

        session()->flash('status', 'Category status toggled successfully.');
    }

    public function deleteCategory($id)
    {
        $category = SlotCategory::findOrFail($id);
        $category->delete();

        session()->flash('status', 'Slot category deleted successfully.');
    }

    public function with()
    {
        $query = SlotCategory::query();

        if ($this->search) {
            $query->where('name', 'like', '%' . $this->search . '%');
        }

        return [
            'categories' => $query->orderBy('name', 'asc')->paginate(9),
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Card -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Slot Categories') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Configure and organize sports/activity slot classifications and active status settings.') }}</p>
            </div>
            <div>
                <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Category') }}
                </button>
            </div>
        </div>

        @if (session()->has('status'))
            <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Filters Section -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
            <div class="relative w-full md:max-w-xs">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search categories..." class="w-full pl-10 pr-4 py-2 border border-gray-200 dark:border-gray-700 rounded-xl text-xs bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                <div class="absolute left-3.5 top-3.5 text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-2 2xl:grid-cols-3 gap-6">
            @forelse ($categories as $category)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between relative">
                    <div>
                        <!-- Header with tag avatar & actions -->
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="h-11 w-11 shrink-0 rounded-2xl bg-gradient-to-tr from-indigo-500 to-indigo-600 text-white flex items-center justify-center shadow-md">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                        {{ $category->name }}
                                    </h3>
                                    <p class="text-[10px] text-gray-400 dark:text-gray-500 font-medium mt-0.5">{{ __('Registered') }} {{ $category->created_at->format('M d, Y') }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <!-- Edit Button -->
                                <button wire:click="editCategory({{ $category->id }})" class="p-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 text-indigo-600 dark:text-indigo-400 rounded-xl transition cursor-pointer border border-gray-100 dark:border-gray-600 flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                
                                <!-- Delete Button -->
                                <button wire:click="confirmDelete({{ $category->id }})" class="p-2 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 rounded-xl transition cursor-pointer flex items-center justify-center border border-red-100/10 dark:border-red-900/10">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Status Toggle Section -->
                        <div class="mt-6 flex items-center justify-between border-t border-gray-50 dark:border-gray-700/40 pt-4">
                            <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">{{ __('Category Availability') }}</span>
                            <div class="flex items-center gap-3">
                                <span class="text-[10px] font-black uppercase tracking-wider {{ $category->is_active ? 'text-emerald-500' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ $category->is_active ? __('Active') : __('Inactive') }}
                                </span>
                                <!-- Custom Toggle Button -->
                                <button wire:click="toggleStatus({{ $category->id }})" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $category->is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $category->is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 p-12 text-center text-xs text-gray-500 dark:text-gray-400 rounded-3xl border border-gray-100 dark:border-gray-700/50">
                    {{ __('No slot categories configured.') }}
                </div>
            @endforelse
        </div>

        @if ($categories->hasPages())
            <div class="bg-white dark:bg-gray-800 px-6 py-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm mt-6">
                {{ $categories->links() }}
            </div>
        @endif

        <!-- Create/Edit Modal -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="resetForm"></div>

                <!-- Modal Container -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-md z-50 border border-gray-100 dark:border-gray-700">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50 mb-6">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ $editingId ? __('Edit Slot Category') : __('Create Slot Category') }}
                            </h3>
                            <button wire:click="resetForm" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveCategory" class="space-y-5">
                            
                            <!-- Name Field -->
                            <div>
                                <x-input-label for="categoryName" :value="__('Category Name')" />
                                <x-text-input wire:model.live.debounce.250ms="name" id="categoryName" type="text" class="mt-1.5 block w-full" placeholder="Football, Badminton, etc." />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Is Active Field -->
                            <div class="flex items-center justify-between bg-gray-50/50 dark:bg-gray-900/30 border border-gray-200 dark:border-gray-700/60 p-4 rounded-2xl">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Set Category Active') }}</span>
                                <button type="button" wire:click="$toggle('is_active')" class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $is_active ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700' }}">
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $is_active ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" wire:click="resetForm" class="px-5 py-2.5 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 rounded-xl font-bold text-xs uppercase text-gray-700 dark:text-gray-300 transition duration-150 cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                    {{ __('Save Category') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        <!-- Delete Confirmation Modal -->
        @if ($showDeleteConfirm)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="cancelDelete"></div>

                <!-- Modal Container -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-md z-50 border border-gray-100 dark:border-gray-700">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center gap-4 text-red-600 dark:text-red-400 mb-4">
                            <div class="h-12 w-12 rounded-2xl bg-red-50 dark:bg-red-950/30 flex items-center justify-center border border-red-100/50 dark:border-red-950/50 shrink-0">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                    {{ __('Confirm Delete') }}
                                </h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ __('This action cannot be undone.') }}
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-600 dark:text-gray-300 mb-6 leading-relaxed">
                            {{ __('Are you sure you want to delete this category?') }}
                        </p>

                        <!-- Form Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                            <button type="button" wire:click="cancelDelete" class="px-5 py-2.5 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 rounded-xl font-bold text-xs uppercase text-gray-700 dark:text-gray-300 transition duration-150 cursor-pointer">
                                {{ __('Cancel') }}
                            </button>
                            <button type="button" wire:click="performDelete" class="px-5 py-2.5 bg-red-600 hover:bg-red-750 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                {{ __('Yes, Delete') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
