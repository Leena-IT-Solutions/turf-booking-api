<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                Step 3 of 12 · Required (Min 1 Active Photo)
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Turf Gallery & Cover Photo') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Upload clear daytime and floodlight photos. The first photo (#1) is displayed as your main cover photo in the mobile app.') }}</p>
        </div>
        <div class="text-xs font-bold text-gray-600">
            {{ $turfPhotos->where('is_active', true)->count() }} {{ __('active') }} / {{ $turfPhotos->count() }} {{ __('total (max 10)') }}
        </div>
    </div>

    @error('photosStep')
        <div class="p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-bold">
            {{ $message }}
        </div>
    @enderror

    <!-- Multi-Photo Upload Box -->
    <div class="p-6 rounded-2xl border-2 border-dashed border-indigo-200 bg-indigo-50/30 text-center space-y-3">
        <div class="w-12 h-12 rounded-2xl bg-white text-indigo-600 flex items-center justify-center mx-auto shadow-xs border border-indigo-100">
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <div>
            <label for="wizardPhotoFiles" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider shadow-xs cursor-pointer transition">
                <span>📤 Select Photos to Upload</span>
                <input id="wizardPhotoFiles" type="file" wire:model="photoFiles" multiple accept="image/*" class="hidden" />
            </label>
            <p class="text-[11px] text-gray-500 mt-2">{{ __('JPG, PNG, WEBP up to 2 MB each (up to 10 photos per turf).') }}</p>
        </div>

        <div wire:loading wire:target="photoFiles,uploadWizardPhotos" class="text-xs font-bold text-indigo-600 animate-pulse">
            {{ __('Uploading & processing photos...') }}
        </div>

        @error('photoFiles') <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
        @error('photoFiles.*') <p class="text-xs text-rose-600 font-semibold">{{ $message }}</p> @enderror
    </div>

    <!-- Uploaded Photos Grid with Cover Badge & Reordering -->
    @if ($turfPhotos->isEmpty())
        <div class="p-8 rounded-2xl bg-gray-50 border border-gray-100 text-center text-xs text-gray-500">
            {{ __('No photos uploaded yet for this turf. Upload at least 1 photo above to continue.') }}
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            @foreach ($turfPhotos as $index => $photo)
                <div class="rounded-2xl border {{ $index === 0 ? 'border-emerald-400 ring-2 ring-emerald-500/20' : 'border-gray-200' }} bg-white overflow-hidden shadow-2xs flex flex-col justify-between">
                    <div class="relative h-44 bg-gray-100">
                        <img src="{{ Storage::url($photo->photo) }}" alt="Turf Photo" class="w-full h-full object-cover {{ !$photo->is_active ? 'opacity-40 grayscale' : '' }}" />
                        <div class="absolute top-2.5 left-2.5 flex items-center gap-1.5">
                            @if ($index === 0)
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-600 text-white shadow">
                                    ★ Cover Photo (#1)
                                </span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-gray-900/75 text-white">
                                    #{{ $index + 1 }}
                                </span>
                            @endif
                        </div>
                        <div class="absolute top-2.5 right-2.5">
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase {{ $photo->is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-800 text-gray-200' }}">
                                {{ $photo->is_active ? 'Visible' : 'Hidden' }}
                            </span>
                        </div>
                    </div>

                    <div class="p-3 bg-gray-50/70 border-t border-gray-100 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1">
                            @if ($index > 0)
                                <button type="button"
                                        wire:click="setAsCoverPhoto({{ $photo->id }})"
                                        title="Make Cover Photo (#1)"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-wider border border-emerald-200 cursor-pointer">
                                    ★ Make Cover
                                </button>
                                <button type="button"
                                        wire:click="movePhotoUp({{ $photo->id }})"
                                        title="Move Earlier"
                                        class="p-1 rounded-lg bg-white hover:bg-gray-100 text-gray-600 border border-gray-200 text-xs font-bold cursor-pointer">
                                    ←
                                </button>
                            @endif
                            @if ($index < $turfPhotos->count() - 1)
                                <button type="button"
                                        wire:click="movePhotoDown({{ $photo->id }})"
                                        title="Move Later"
                                        class="p-1 rounded-lg bg-white hover:bg-gray-100 text-gray-600 border border-gray-200 text-xs font-bold cursor-pointer">
                                    →
                                </button>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button type="button"
                                    wire:click="toggleWizardPhotoActive({{ $photo->id }})"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-bold border transition cursor-pointer {{ $photo->is_active ? 'bg-white text-gray-700 border-gray-200 hover:bg-gray-100' : 'bg-indigo-50 text-indigo-700 border-indigo-200' }}">
                                {{ $photo->is_active ? 'Hide' : 'Show' }}
                            </button>
                            <button type="button"
                                    wire:click="deleteWizardPhoto({{ $photo->id }})"
                                    class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-100 transition cursor-pointer"
                                    title="Delete Photo">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

