<?php

use App\Models\Turf;
use App\Models\TurfPhoto;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    public $photoFile;
    public $showUploadModal = false;

    // Delete confirmation state
    public $deletingId = null;
    public $showDeleteConfirm = false;

    #[On('global-context-updated')]
    public function refreshPhotos()
    {
        // Dynamic re-render on active context change
    }

    public function openUploadModal()
    {
        $this->reset('photoFile');
        $this->resetErrorBag();
        $this->showUploadModal = true;
    }

    public function closeUploadModal()
    {
        $this->showUploadModal = false;
    }

    public function uploadPhoto()
    {
        $this->validate([
            'photoFile' => 'required|image|max:5120', // 5MB max
        ]);

        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) {
            $this->closeUploadModal();
            return;
        }

        // Verify user owns the turf
        $turf = Turf::manageable()->findOrFail($activeTurfId);

        $path = $this->photoFile->store('turf_photos', 'public');

        TurfPhoto::create([
            'turf_id' => $turf->id,
            'photo' => $path,
            'is_active' => true,
        ]);

        $this->closeUploadModal();
        session()->flash('status', 'Photo uploaded successfully.');
    }

    public function toggleActive($id)
    {
        $photo = TurfPhoto::whereHas('turf.location', function ($q) {
            $q->where('user_id', auth()->id());
        })->findOrFail($id);

        $photo->update([
            'is_active' => !$photo->is_active
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
            $photo = TurfPhoto::whereHas('turf.location', function ($q) {
                $q->where('user_id', auth()->id());
            })->findOrFail($this->deletingId);

            if (Storage::disk('public')->exists($photo->photo)) {
                Storage::disk('public')->delete($photo->photo);
            }

            $photo->delete();
            session()->flash('status', 'Photo deleted successfully.');
        }

        $this->cancelDelete();
    }

    public function with()
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;
        $photos = collect();

        if ($activeTurfId) {
            $turf = Turf::manageable()->find($activeTurfId);

            if ($turf) {
                $photos = TurfPhoto::where('turf_id', $turf->id)->orderBy('created_at', 'desc')->get();
            }
        }

        return [
            'turf' => $turf,
            'photos' => $photos,
        ];
    }
}; ?>

<div class="py-6" x-data="{ uploadModal: @entangle('showUploadModal'), deleteConfirm: @entangle('showDeleteConfirm') }">
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
                <p class="text-sm text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('Please add a Location and Turf first, or choose one from the selector in the top bar to manage photos.') }}</p>
            </div>
        @else
            <!-- Header Section -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Photos for') }} <span class="text-indigo-600 dark:text-indigo-400">{{ $turf->name }}</span></h2>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage your turf field image gallery, activate visual showcases, or upload new high-resolution court photos.') }}</p>
                </div>
                <button type="button" wire:click="openUploadModal" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/20 hover:shadow-lg hover:shadow-indigo-500/30 transition flex items-center justify-center gap-2 cursor-pointer shrink-0">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    {{ __('Upload Photo') }}
                </button>
            </div>

            @if ($photos->isEmpty())
                <!-- Empty State -->
                <div class="bg-white dark:bg-gray-800 p-16 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
                    <div class="h-16 w-16 rounded-2xl bg-indigo-50/50 dark:bg-indigo-950/10 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/30 dark:border-indigo-950/20">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('No Photos Uploaded') }}</h3>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2 max-w-xs mx-auto leading-relaxed">{{ __('Start uploading gallery photos to show customers the look and feel of this turf.') }}</p>
                </div>
            @else
                <!-- Photos Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($photos as $photo)
                        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm overflow-hidden flex flex-col group relative">
                            <!-- Image Wrapper -->
                            <div class="aspect-video w-full bg-gray-100 dark:bg-gray-900 relative overflow-hidden">
                                <img src="{{ Storage::url($photo->photo) }}" alt="Turf Photo" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                
                                <!-- Floating Controls (Glassmorphism overlay on hover) -->
                                <div class="absolute inset-0 bg-gray-950/30 opacity-0 group-hover:opacity-100 transition duration-300 flex items-center justify-center gap-3">
                                    <!-- Toggle Active -->
                                    <button type="button" wire:click="toggleActive({{ $photo->id }})" class="p-2.5 rounded-xl bg-white/90 hover:bg-white dark:bg-gray-850/90 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 shadow-lg cursor-pointer transition" title="{{ $photo->is_active ? __('Deactivate') : __('Activate') }}">
                                        @if ($photo->is_active)
                                            <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        @else
                                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        @endif
                                    </button>

                                    <!-- Delete Button -->
                                    <button type="button" wire:click="confirmDelete({{ $photo->id }})" class="p-2.5 rounded-xl bg-white/90 hover:bg-white dark:bg-gray-850/90 dark:hover:bg-gray-800 text-red-500 hover:text-red-600 shadow-lg cursor-pointer transition" title="{{ __('Delete') }}">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Status Badge (Visible always) -->
                                <div class="absolute top-3 left-3">
                                    @if ($photo->is_active)
                                        <span class="px-2.5 py-1 rounded-lg bg-emerald-500/90 text-white text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm shadow-sm">
                                            {{ __('Active') }}
                                        </span>
                                    @else
                                        <span class="px-2.5 py-1 rounded-lg bg-gray-500/90 text-white text-[10px] font-bold uppercase tracking-wider backdrop-blur-sm shadow-sm">
                                            {{ __('Inactive') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

        <!-- Image Upload Modal -->
        <div x-show="uploadModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="uploadModal" @click="uploadModal = false" class="fixed inset-0 transition-opacity bg-gray-950/40 dark:bg-gray-950/60 backdrop-blur-sm"></div>

                <!-- Center element helper -->
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

                <div x-show="uploadModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-3xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-gray-100 dark:border-gray-700/50">
                    <div class="p-6">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50">
                            <h3 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                {{ __('Upload Turf Photo') }}
                            </h3>
                            <button type="button" @click="uploadModal = false" class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 cursor-pointer">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="uploadPhoto" class="space-y-4 mt-4">
                            <!-- File Upload Drag/Drop Box -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                    {{ __('Select Field Image') }}
                                </label>
                                <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-dashed {{ $errors->has('photoFile') ? 'border-red-500 dark:border-red-500 bg-red-50/10 dark:bg-red-950/5' : 'border-gray-300 dark:border-gray-650 bg-gray-50/50 dark:bg-gray-900/10' }} rounded-2xl relative">
                                    <!-- Loading Indicator Overlay -->
                                    <div wire:loading wire:target="photoFile" class="absolute inset-0 bg-white/90 dark:bg-gray-800/90 backdrop-blur-xs flex flex-col items-center justify-center rounded-2xl z-20">
                                        <svg class="animate-spin h-8 w-8 text-indigo-650 dark:text-indigo-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 mt-2">{{ __('Uploading image...') }}</span>
                                    </div>

                                    <div class="space-y-1 text-center">
                                        <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-gray-500" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <div class="flex text-xs text-gray-600 dark:text-gray-400">
                                            <label for="photoFile" class="relative cursor-pointer rounded-md font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                                <span>{{ __('Upload a file') }}</span>
                                                <input id="photoFile" wire:model="photoFile" type="file" class="sr-only">
                                            </label>
                                            <p class="pl-1">{{ __('or drag and drop') }}</p>
                                        </div>
                                        <p class="text-[10px] text-gray-400 dark:text-gray-500">
                                            {{ __('PNG, JPG, JPEG up to 5MB') }}
                                        </p>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('photoFile')" class="mt-2" />
                            </div>

                            <!-- Upload Preview -->
                            @if ($photoFile && !$errors->has('photoFile'))
                                <div class="mt-4">
                                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-2">
                                        {{ __('Image Preview') }}
                                    </label>
                                    <div class="aspect-video w-full rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                        <img src="{{ $photoFile->temporaryUrl() }}" class="w-full h-full object-cover">
                                    </div>
                                </div>
                            @endif

                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700/50">
                                <button type="button" @click="uploadModal = false" class="px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 border border-gray-250 dark:border-gray-650 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" wire:loading.attr="disabled" @if(!$photoFile) disabled @endif class="px-4 py-2 text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-750 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl shadow-md shadow-indigo-500/20 transition cursor-pointer">
                                    {{ __('Upload') }}
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
                                    {{ __('Delete Photo') }}
                                </h3>
                                <p class="text-xs text-gray-400 mt-1">
                                    {{ __('Are you sure you want to permanently delete this photo? This will remove the image file and cannot be undone.') }}
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
