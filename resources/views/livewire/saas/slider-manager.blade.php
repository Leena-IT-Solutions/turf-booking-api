<?php

use App\Models\SliderImage;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $sliderImages;

    // Form fields
    public $title = '';
    public $link_url = '';
    public $order = 0;
    public $is_active = true;
    public $image; // file upload object

    // Editing state
    public $editingId = null;
    public $existingImagePath = null;

    // View states
    public $showModal = false;

    public function mount()
    {
        $this->loadSlides();
    }

    public function loadSlides()
    {
        $this->sliderImages = SliderImage::orderBy('order', 'asc')->get();
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function resetForm()
    {
        $this->reset(['title', 'link_url', 'order', 'is_active', 'image', 'editingId', 'existingImagePath']);
        $this->showModal = false;
        $this->resetErrorBag();
    }

    public function updated($propertyName)
    {
        $rules = [
            'title' => 'nullable|string|max:100',
            'link_url' => 'nullable|url',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];

        if ($this->editingId) {
            $rules['image'] = 'nullable|image|max:2048';
        } else {
            $rules['image'] = 'required|image|max:2048';
        }

        $this->validateOnly($propertyName, $rules);
    }

    public function saveSlide()
    {
        $rules = [
            'title' => 'nullable|string|max:100',
            'link_url' => 'nullable|url',
            'order' => 'required|integer|min:0',
            'is_active' => 'boolean',
        ];

        if ($this->editingId) {
            $rules['image'] = 'nullable|image|max:2048'; // Image optional when editing
        } else {
            $rules['image'] = 'required|image|max:2048'; // Image required for new slide
        }

        $validated = $this->validate($rules);

        if ($this->editingId) {
            $slide = SliderImage::findOrFail($this->editingId);
            $path = $slide->image_path;

            if ($this->image) {
                // Delete old image
                if (Storage::disk('public')->exists($slide->image_path)) {
                    Storage::disk('public')->delete($slide->image_path);
                }
                // Save new image
                $path = $this->image->store('sliders', 'public');
            }

            $slide->update([
                'title' => $this->title,
                'link_url' => $this->link_url,
                'order' => $this->order,
                'is_active' => $this->is_active,
                'image_path' => $path,
            ]);
        } else {
            // Save image
            $path = $this->image->store('sliders', 'public');

            $orderValue = $this->order > 0 ? $this->order : ((SliderImage::max('order') ?? -1) + 1);

            SliderImage::create([
                'title' => $this->title,
                'link_url' => $this->link_url,
                'order' => $orderValue,
                'is_active' => $this->is_active,
                'image_path' => $path,
            ]);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->loadSlides();

        session()->flash('status', 'Slide saved successfully.');
    }

    public function editSlide($id)
    {
        $this->resetForm();
        $slide = SliderImage::findOrFail($id);
        $this->editingId = $slide->id;
        $this->title = $slide->title;
        $this->link_url = $slide->link_url;
        $this->order = $slide->order;
        $this->is_active = $slide->is_active;
        $this->existingImagePath = $slide->image_path;
        $this->showModal = true;
    }

    public function toggleActive($id)
    {
        $slide = SliderImage::findOrFail($id);
        $slide->is_active = !$slide->is_active;
        $slide->save();
        $this->loadSlides();
    }

    public function deleteSlide($id)
    {
        $slide = SliderImage::findOrFail($id);
        
        // Delete file from disk
        if (Storage::disk('public')->exists($slide->image_path)) {
            Storage::disk('public')->delete($slide->image_path);
        }

        $slide->delete();
        $this->loadSlides();

        session()->flash('status', 'Slide deleted successfully.');
    }

    public function reorderSlides($draggedId, $targetId)
    {
        $dragged = SliderImage::findOrFail($draggedId);
        $target = SliderImage::findOrFail($targetId);

        $draggedOrder = $dragged->order;
        $targetOrder = $target->order;

        if ($draggedOrder === $targetOrder) {
            $slides = SliderImage::orderBy('order', 'asc')->get();
            foreach ($slides as $index => $slide) {
                $slide->update(['order' => $index * 10]);
            }
            $dragged = $dragged->fresh();
            $target = $target->fresh();
            $draggedOrder = $dragged->order;
            $targetOrder = $target->order;
        }

        if ($draggedOrder > $targetOrder) {
            SliderImage::where('order', '>=', $targetOrder)
                ->where('order', '<', $draggedOrder)
                ->increment('order');
        } else {
            SliderImage::where('order', '>', $draggedOrder)
                ->where('order', '<=', $targetOrder)
                ->decrement('order');
        }

        $dragged->update(['order' => $targetOrder]);

        $this->normalizeOrders();
        $this->loadSlides();
    }

    protected function normalizeOrders()
    {
        $slides = SliderImage::orderBy('order', 'asc')->orderBy('updated_at', 'desc')->get();
        foreach ($slides as $index => $slide) {
            $slide->update(['order' => $index]);
        }
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Image Slider Management') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Configure and order homepage image slides. Recommends a 16:9 aspect ratio image size.') }}</p>
            </div>
            <div>
                <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add New Slide') }}
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

        <!-- Grid Layout of Slides -->
        @if ($sliderImages->isEmpty())
            <div class="bg-white dark:bg-gray-800 rounded-3xl p-12 text-center border border-dashed border-gray-200 dark:border-gray-700 flex flex-col items-center justify-center">
                <div class="h-12 w-12 text-gray-400 dark:text-gray-500 mb-4">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H2.25A1.5 1.5 0 00.75 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-200">{{ __('No slides added') }}</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 max-w-xs">{{ __('Get started by creating your first homepage promotion slide.') }}</p>
                <button wire:click="openCreateModal" class="mt-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out">
                    {{ __('Create Slide') }}
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6"
                 x-data="{ draggingId: null, dragOverId: null }">
                @foreach ($sliderImages as $sliderImage)
                    <div class="relative aspect-[16/9] bg-gray-900 rounded-3xl overflow-hidden shadow-sm group border border-gray-100 dark:border-gray-800 cursor-move transition-all duration-300"
                         draggable="true"
                         x-on:dragstart="draggingId = {{ $sliderImage->id }}; event.dataTransfer.effectAllowed = 'move';"
                         x-on:dragover.prevent="dragOverId = {{ $sliderImage->id }}"
                         x-on:dragleave="if (dragOverId === {{ $sliderImage->id }}) dragOverId = null;"
                         x-on:drop="
                             if (draggingId && draggingId !== {{ $sliderImage->id }}) {
                                 $wire.reorderSlides(draggingId, {{ $sliderImage->id }});
                             }
                             draggingId = null;
                             dragOverId = null;
                         "
                         x-bind:class="{ 
                             'opacity-50 ring-2 ring-indigo-500 scale-95': draggingId === {{ $sliderImage->id }},
                             'ring-2 ring-indigo-400 scale-[1.01]': dragOverId === {{ $sliderImage->id }} && draggingId !== {{ $sliderImage->id }}
                         }">
                        <!-- Slide Image Fill -->
                        <img src="{{ Storage::url($sliderImage->image_path) }}" alt="{{ $sliderImage->title }}" class="absolute inset-0 h-full w-full object-cover opacity-75 group-hover:scale-102 transition duration-500">
                        
                        <!-- Top Dark Gradient Overlay -->
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-950/80 via-transparent to-gray-950/40"></div>

                        <!-- Top Badges & Controls Overlay -->
                        <div class="absolute top-4 inset-x-4 flex justify-between items-start z-10">
                            <!-- Active Status Trigger Badge -->
                            <div class="flex flex-col gap-1.5">
                                <button wire:click="toggleActive({{ $sliderImage->id }})" class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider shadow-md transition cursor-pointer {{ $sliderImage->is_active ? 'bg-emerald-500/90 text-white' : 'bg-gray-500/90 text-white' }}">
                                    {{ $sliderImage->is_active ? __('Active') : __('Inactive') }}
                                </button>
                                <span class="bg-gray-950/70 border border-gray-800 text-gray-300 px-2 py-0.5 rounded-lg text-[9px] font-mono font-bold w-max">
                                    Order: {{ $sliderImage->order }}
                                </span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2">
                                <button wire:click="editSlide({{ $sliderImage->id }})" class="p-2 bg-white hover:bg-gray-50 text-indigo-600 rounded-xl shadow-md transition transform hover:scale-105 cursor-pointer flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                <button onclick="confirm('Are you sure you want to delete this slide?') || event.stopImmediatePropagation()" wire:click="deleteSlide({{ $sliderImage->id }})" class="p-2 bg-red-600 hover:bg-red-700 text-white rounded-xl shadow-md transition transform hover:scale-105 cursor-pointer flex items-center justify-center" style="background-color: rgb(220, 38, 38);">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Bottom Text Info Overlay -->
                        <div class="absolute bottom-4 left-4 right-4 z-10">
                            <h4 class="text-sm font-bold text-white truncate drop-shadow-sm">{{ $sliderImage->title ?: __('Untitled Slide') }}</h4>
                            @if ($sliderImage->link_url)
                                <p class="text-[10px] text-gray-300 font-medium truncate mt-0.5 drop-shadow-sm">{{ $sliderImage->link_url }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <!-- Edit/Create Modal Backdrop & Overlay -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Modal backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="resetForm"></div>

                <!-- Modal dialog container -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-lg z-50 border border-gray-100 dark:border-gray-700">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50 mb-6">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ $editingId ? __('Edit Slide Details') : __('Create New Slide') }}
                            </h3>
                            <button wire:click="resetForm" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveSlide" class="space-y-5">
                            
                            <!-- Image Aspect Ratio Info Card -->
                            <div class="bg-indigo-50/50 dark:bg-indigo-950/15 border border-indigo-100/60 dark:border-indigo-900/40 rounded-2xl p-4 flex gap-3 text-xs leading-relaxed text-indigo-700 dark:text-indigo-400">
                                <svg class="h-5 w-5 shrink-0 text-indigo-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <div>
                                    <span class="font-bold block uppercase text-[10px] tracking-wider mb-0.5">Recommended Slide Dimensions</span>
                                    Aspect Ratio: **16:9**. We recommend uploading **1920x1080** pixels (Full HD) or **1280x720** pixels (HD) for crisp rendering. Max size limit: **2 MB**.
                                </div>
                            </div>

                            <!-- Image File Upload Input -->
                            <div>
                                <x-input-label for="image" :value="__('Select Slide Image')" />
                                
                                <!-- File Upload Trigger Area -->
                                <div class="mt-1.5 flex items-center justify-center w-full">
                                    <label class="flex flex-col items-center justify-center w-full aspect-[16/9] border-2 border-gray-300 dark:border-gray-700 border-dashed rounded-3xl cursor-pointer bg-gray-50 dark:bg-gray-900/40 hover:bg-gray-100 dark:hover:bg-gray-900/80 overflow-hidden relative group">
                                        
                                        <!-- Image Preview Block -->
                                        @if ($image)
                                            <img src="{{ $image->temporaryUrl() }}" class="absolute inset-0 h-full w-full object-cover">
                                        @elseif ($existingImagePath)
                                            <img src="{{ Storage::url($existingImagePath) }}" class="absolute inset-0 h-full w-full object-cover">
                                        @else
                                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                                <svg class="w-8 h-8 mb-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-wider">{{ __('Upload Image') }}</p>
                                            </div>
                                        @endif

                                        <input type="file" wire:model="image" id="image" class="hidden" accept="image/*" />
                                    </label>
                                </div>
                                <x-input-error :messages="$errors->get('image')" class="mt-2" />
                            </div>

                            <!-- Title Input -->
                            <div>
                                <x-input-label for="title" :value="__('Slide Title (Optional)')" />
                                <x-text-input wire:model="title" id="title" type="text" class="mt-1.5 block w-full" placeholder="Enter slide title..." />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>

                            <!-- Link URL Input -->
                            <div>
                                <x-input-label for="link_url" :value="__('Redirect Link URL (Optional)')" />
                                <x-text-input wire:model="link_url" id="link_url" type="text" class="mt-1.5 block w-full" placeholder="https://example.com/promo" />
                                <x-input-error :messages="$errors->get('link_url')" class="mt-2" />
                            </div>

                            <!-- Order Index and Status Inputs -->
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <x-input-label for="order" :value="__('Display Sort Order')" />
                                    <x-text-input wire:model="order" id="order" type="number" class="mt-1.5 block w-full" min="0" />
                                    <x-input-error :messages="$errors->get('order')" class="mt-2" />
                                </div>

                                <div class="flex items-center mt-6">
                                    <label class="relative inline-flex items-center cursor-pointer select-none">
                                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                                        <div class="w-10 h-5 bg-gray-200 dark:bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                                        <span class="ms-3 text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Active Status') }}</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Form Buttons -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" wire:click="resetForm" class="px-5 py-2.5 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/60 rounded-xl font-bold text-xs uppercase text-gray-700 dark:text-gray-300 transition duration-150 cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                    {{ __('Save Details') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

    </div>
</div>
