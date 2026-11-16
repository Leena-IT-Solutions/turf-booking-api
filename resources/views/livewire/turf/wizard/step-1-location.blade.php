<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                Step 1 of 12 · Required
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Venue Location & Map Pin') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Select an existing physical venue location or create a new one with an exact map pin so players can find you.') }}</p>
        </div>

        @if ($manageableLocations->isNotEmpty())
            <div class="flex items-center gap-2 shrink-0">
                <button type="button"
                        wire:click="switchLocationMode('existing')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer {{ $locationMode === 'existing' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    {{ __('Existing Location') }} ({{ $manageableLocations->count() }})
                </button>
                <button type="button"
                        wire:click="switchLocationMode('create')"
                        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer {{ $locationMode === 'create' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                    + {{ __('New Location') }}
                </button>
            </div>
        @endif
    </div>

    @if ($locationMode === 'existing' && $manageableLocations->isNotEmpty())
        <div class="space-y-3">
            <label class="block text-xs font-bold uppercase tracking-wider text-gray-600">{{ __('Choose a Location to Edit or Continue') }}</label>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach ($manageableLocations as $loc)
                    @php
                        $isSelectedLoc = (int) $locationId === (int) $loc->id;
                        $hasPin = $loc->latitude !== null && $loc->longitude !== null;
                    @endphp
                    <div wire:click="selectExistingLocation({{ $loc->id }})"
                         class="p-4 rounded-2xl border-2 transition cursor-pointer flex items-start justify-between gap-3 {{ $isSelectedLoc ? 'border-indigo-600 bg-indigo-50/40 shadow-xs' : 'border-gray-100 hover:border-indigo-200 bg-white' }}">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-black text-gray-900">{{ $loc->name }}</span>
                                @if ($hasPin)
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        📍 Pin Set
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                        ⚠️ Pin Required
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $loc->address }}</p>
                        </div>
                        <div class="h-5 w-5 rounded-full border-2 flex items-center justify-center shrink-0 mt-0.5 {{ $isSelectedLoc ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300' }}">
                            @if ($isSelectedLoc)
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Location Details & Map Pin Form -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 pt-2">
        <div class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Venue / Location Name') }} <span class="text-rose-500">*</span></label>
                <input type="text"
                       wire:model="locationName"
                       placeholder="e.g. Wankhede Sports Arena"
                       class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
                @error('locationName') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">{{ __('Complete Street Address & Landmark') }} <span class="text-rose-500">*</span></label>
                <textarea wire:model="locationAddress"
                          rows="3"
                          placeholder="Plot No, Road, Near Landmark, Area, City - Pincode"
                          class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                @error('locationAddress') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center justify-between pt-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __('Venue Contact Info') }}</span>
                <button type="button"
                        wire:click="useOwnerContactForLocation"
                        class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 underline cursor-pointer">
                    {{ __('Use Owner Phone & Email') }}
                </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('Contact Number') }}</label>
                    <input type="text"
                           wire:model="locationContactNumber"
                           placeholder="9876543210"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm font-mono text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('locationContactNumber') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">{{ __('Contact Email') }}</label>
                    <input type="email"
                           wire:model="locationEmail"
                           placeholder="venue@example.com"
                           class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('locationEmail') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <!-- Map Pin & Coordinates Column -->
        <div class="space-y-3"
             x-data="{
                map: null,
                marker: null,
                lat: @entangle('locationLatitude').live,
                lng: @entangle('locationLongitude').live,
                gpsStatus: '',
                initMap() {
                    const startLat = parseFloat(this.lat) || 19.0760;
                    const startLng = parseFloat(this.lng) || 72.8777;
                    const tryInit = () => {
                        if (typeof window.L === 'undefined' || !this.$refs.wizardMap) {
                            setTimeout(tryInit, 120);
                            return;
                        }
                        if (this.map) {
                            this.map.invalidateSize();
                            return;
                        }
                        this.map = window.L.map(this.$refs.wizardMap).setView([startLat, startLng], (this.lat && this.lng) ? 15 : 11);
                        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '© OpenStreetMap'
                        }).addTo(this.map);

                        this.marker = window.L.marker([startLat, startLng], { draggable: true }).addTo(this.map);

                        this.marker.on('dragend', (e) => {
                            const pos = e.target.getLatLng();
                            this.lat = pos.lat.toFixed(6);
                            this.lng = pos.lng.toFixed(6);
                        });

                        this.map.on('click', (e) => {
                            this.marker.setLatLng(e.latlng);
                            this.lat = e.latlng.lat.toFixed(6);
                            this.lng = e.latlng.lng.toFixed(6);
                        });

                        this.$watch('lat', (val) => {
                            const nLat = parseFloat(val);
                            const nLng = parseFloat(this.lng);
                            if (!isNaN(nLat) && !isNaN(nLng) && this.marker) {
                                this.marker.setLatLng([nLat, nLng]);
                                this.map.panTo([nLat, nLng]);
                            }
                        });
                    };
                    tryInit();
                },
                detectGps() {
                    if (!navigator.geolocation) {
                        this.gpsStatus = 'Geolocation is not supported by your browser.';
                        return;
                    }
                    this.gpsStatus = 'Detecting current GPS coordinates...';
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.lat = pos.coords.latitude.toFixed(6);
                            this.lng = pos.coords.longitude.toFixed(6);
                            if (this.marker && this.map) {
                                this.marker.setLatLng([pos.coords.latitude, pos.coords.longitude]);
                                this.map.setView([pos.coords.latitude, pos.coords.longitude], 16);
                            }
                            this.gpsStatus = 'GPS coordinates locked!';
                        },
                        () => {
                            this.gpsStatus = 'Unable to retrieve GPS. Click on the map or enter coordinates manually.';
                        },
                        { enableHighAccuracy: true, timeout: 8000 }
                    );
                }
             }"
             x-init="initMap()">
            <div class="flex items-center justify-between gap-2">
                <label class="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                    📍 {{ __('Pin Exact Venue Location on Map') }} <span class="text-rose-500">*</span>
                </label>
                <button type="button"
                        @click="detectGps()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[11px] font-black uppercase tracking-wider transition cursor-pointer">
                    <span>🎯 Use My Current Location</span>
                </button>
            </div>

            <template x-if="gpsStatus">
                <p class="text-[11px] font-semibold text-indigo-600" x-text="gpsStatus"></p>
            </template>

            <div wire:ignore class="w-full h-56 rounded-2xl overflow-hidden border border-gray-200 shadow-inner bg-gray-100 relative z-0">
                <div x-ref="wizardMap" class="w-full h-full"></div>
            </div>
            <p class="text-[11px] text-gray-400">{{ __('Click anywhere on the map or drag the blue pin to set your exact latitude & longitude.') }}</p>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">{{ __('Latitude') }} <span class="text-rose-500">*</span></label>
                    <input type="number"
                           step="any"
                           wire:model.live="locationLatitude"
                           placeholder="19.076000"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-mono text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('locationLatitude') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-600 uppercase tracking-wider mb-1">{{ __('Longitude') }} <span class="text-rose-500">*</span></label>
                    <input type="number"
                           step="any"
                           wire:model.live="locationLongitude"
                           placeholder="72.877700"
                           class="w-full px-3 py-2 rounded-xl border border-gray-200 text-xs font-mono text-gray-900 focus:border-indigo-500 focus:ring-indigo-500" />
                    @error('locationLongitude') <p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>
</div>

