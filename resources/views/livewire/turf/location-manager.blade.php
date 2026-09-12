<?php

use App\Models\Location;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Search filter
    public $search = '';

    // Form inputs
    public $name = '';
    public $address = '';
    public $latitude = '';
    public $longitude = '';
    public $contact_number = '';
    public $email = '';

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
            $this->deleteLocation($this->deletingId);
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
        $this->reset(['name', 'address', 'latitude', 'longitude', 'contact_number', 'email', 'editingId']);
        $this->resetErrorBag();
        $this->showModal = false;
    }

    public function editLocation($id)
    {
        $this->resetForm();
        $location = Location::manageable()->findOrFail($id);
        $this->editingId = $location->id;
        $this->name = $location->name;
        $this->address = $location->address;
        $this->latitude = $location->latitude;
        $this->longitude = $location->longitude;
        $this->contact_number = $location->contact_number;
        $this->email = $location->email;
        $this->showModal = true;
    }

    public function updated($propertyName)
    {
        $rules = [
            'name' => 'required|string|max:150',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
        ];

        $this->validateOnly($propertyName, $rules);
    }

    public function saveLocation()
    {
        $rules = [
            'name' => 'required|string|max:150',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
        ];

        $this->validate($rules);

        $data = [
            'user_id' => auth()->user()->getOwnerId(),
            'name' => $this->name,
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'contact_number' => $this->contact_number ?: null,
            'email' => $this->email ?: null,
        ];

        if ($this->editingId) {
            $location = Location::manageable()->findOrFail($this->editingId);
            $location->update($data);
            session()->flash('status', 'Location updated successfully.');
        } else {
            Location::create($data);
            session()->flash('status', 'Location created successfully.');
        }

        $this->resetForm();
        $this->dispatch('locations-updated');
    }

    public function useOwnDetails()
    {
        $this->contact_number = auth()->user()->mobile;
        $this->email = auth()->user()->email;
    }

    public function deleteLocation($id)
    {
        $location = Location::manageable()->findOrFail($id);
        $location->delete();
        session()->flash('status', 'Location deleted successfully.');
        $this->dispatch('locations-updated');
    }

    public function with()
    {
        $query = Location::manageable();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('address', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_number', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            });
        }

        return [
            'locations' => $query->orderBy('name', 'asc')->paginate(9),
        ];
    }
}; ?>

<div class="w-full space-y-6">
        
        <!-- Header Card -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 shadow-sm rounded-3xl border border-gray-100">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('Locations') }}</h2>
                <p class="text-xs text-gray-500 mt-1.5">{{ __('Manage physical grounds, contact info, coordinates, and center profiles.') }}</p>
            </div>
            <div>
                <button wire:click="openCreateModal" class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow transition duration-150 ease-in-out cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Add Location') }}
                </button>
            </div>
        </div>

        @if (session()->has('status'))
            <div class="bg-emerald-50 border border-emerald-100 text-emerald-800 px-5 py-3.5 rounded-xl text-xs font-bold uppercase tracking-wider flex items-center gap-3">
                <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Filters Section -->
        <div class="bg-white p-4 rounded-3xl border border-gray-100 flex flex-col md:flex-row gap-4 items-center justify-between shadow-sm">
            <div class="relative w-full md:max-w-xs">
                <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search locations..." class="w-full pl-10 pr-4 py-2 border border-gray-200 rounded-xl text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                <div class="absolute left-3.5 top-3.5 text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Cards Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @forelse ($locations as $loc)
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between relative group">
                    <div>
                        <div class="flex items-start justify-between gap-4 min-w-0 w-full">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="h-11 w-11 shrink-0 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center border border-indigo-100/50">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-gray-900 truncate">
                                        {{ $loc->name }}
                                    </h3>
                                    <span class="text-[10px] text-gray-400 font-medium">Location ID: #{{ $loc->id }}</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5 shrink-0 opacity-80 group-hover:opacity-100 transition">
                                <button wire:click="editLocation({{ $loc->id }})" class="p-2 bg-gray-50 hover:bg-gray-100 text-indigo-600 rounded-xl transition cursor-pointer border border-gray-100 flex items-center justify-center">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </button>
                                
                                <button wire:click="confirmDelete({{ $loc->id }})" class="p-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl transition cursor-pointer flex items-center justify-center border border-red-100/10">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Card Body details -->
                        <div class="mt-4 space-y-3.5">
                            <!-- Address -->
                            <div class="flex items-start gap-2.5">
                                <svg class="h-4 w-4 text-gray-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span class="text-xs text-gray-600 leading-relaxed font-medium">
                                    {{ $loc->address }}
                                </span>
                            </div>

                            <!-- Lat/Long -->
                            @if ($loc->latitude && $loc->longitude)
                                <div class="flex items-center gap-2.5">
                                    <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L16 4m0 13V4m0 0L9 7" />
                                    </svg>
                                    <span class="text-[11px] text-gray-500 font-bold bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-100">
                                        {{ $loc->latitude }}, {{ $loc->longitude }}
                                    </span>
                                </div>
                            @endif

                            <!-- Contact details -->
                            <div class="grid grid-cols-1 gap-2 pt-3.5 border-t border-gray-50">
                                @if ($loc->contact_number)
                                    <div class="flex items-center gap-2.5">
                                        <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.94.725l.548 2.2a1 1 0 01-.321.988l-1.305.98a10.582 10.582 0 004.872 4.872l.98-1.305a1 1 0 01.988-.321l2.2.548a1 1 0 01.725.94V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <a href="tel:{{ $loc->contact_number }}" class="text-xs text-indigo-600 hover:underline font-semibold">
                                            {{ $loc->contact_number }}
                                        </a>
                                    </div>
                                @endif

                                @if ($loc->email)
                                    <div class="flex items-center gap-2.5">
                                        <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <a href="mailto:{{ $loc->email }}" class="text-xs text-indigo-600 hover:underline font-semibold truncate">
                                            {{ $loc->email }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full bg-white p-12 text-center text-xs text-gray-500 rounded-3xl border border-gray-100 shadow-sm">
                    {{ __('No turf locations configured.') }}
                </div>
            @endforelse
        </div>

        @if ($locations->hasPages())
            <div class="bg-white px-6 py-4 rounded-3xl border border-gray-100 shadow-sm mt-6">
                {{ $locations->links() }}
            </div>
        @endif

        <!-- Create/Edit Modal -->
        @if ($showModal)
            <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="resetForm"></div>

                <!-- Modal Container -->
                <div 
                    x-data="{
                        mapOpen: true,
                        isLocating: false,
                        locError: '',
                        locSuccess: '',
                        searchQuery: '',
                        isSearching: false,
                        map: null,
                        marker: null,

                        init() {
                            this.$nextTick(() => {
                                this.ensureLeaflet(() => {
                                    this.setupMap();
                                });
                            });
                        },

                        ensureLeaflet(cb) {
                            if (typeof window.L !== 'undefined') {
                                cb();
                                return;
                            }
                            if (!document.getElementById('leaflet-css')) {
                                const link = document.createElement('link');
                                link.id = 'leaflet-css';
                                link.rel = 'stylesheet';
                                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                document.head.appendChild(link);
                            }
                            if (!document.getElementById('leaflet-js')) {
                                const script = document.createElement('script');
                                script.id = 'leaflet-js';
                                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                script.onload = () => {
                                    delete window.L.Icon.Default.prototype._getIconUrl;
                                    window.L.Icon.Default.mergeOptions({
                                        iconRetinaUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png',
                                        iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                                        shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                                    });
                                    cb();
                                };
                                document.body.appendChild(script);
                            } else {
                                const checkInterval = setInterval(() => {
                                    if (typeof window.L !== 'undefined') {
                                        clearInterval(checkInterval);
                                        cb();
                                    }
                                }, 50);
                            }
                        },

                        setupMap() {
                            const container = this.$refs.mapContainer;
                            if (!container || typeof window.L === 'undefined') return;

                            let curLat = parseFloat($wire.latitude);
                            let curLng = parseFloat($wire.longitude);
                            const hasCoords = !isNaN(curLat) && !isNaN(curLng) && curLat !== 0 && curLng !== 0;
                            
                            const startLat = hasCoords ? curLat : 19.0760;
                            const startLng = hasCoords ? curLng : 72.8777;
                            const zoom = hasCoords ? 15 : 12;

                            if (!this.map) {
                                this.map = window.L.map(container, {
                                    zoomControl: true,
                                    scrollWheelZoom: false
                                }).setView([startLat, startLng], zoom);

                                window.L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    maxZoom: 19,
                                    attribution: '&copy; OpenStreetMap contributors'
                                }).addTo(this.map);

                                this.marker = window.L.marker([startLat, startLng], {
                                    draggable: true
                                }).addTo(this.map);

                                this.marker.on('dragend', (e) => {
                                    const pos = e.target.getLatLng();
                                    this.updateCoords(pos.lat, pos.lng);
                                });

                                this.map.on('click', (e) => {
                                    this.marker.setLatLng(e.latlng);
                                    this.updateCoords(e.latlng.lat, e.latlng.lng);
                                });
                            } else {
                                this.marker.setLatLng([startLat, startLng]);
                                this.map.setView([startLat, startLng], zoom);
                                this.map.invalidateSize();
                            }

                            setTimeout(() => {
                                if (this.map) this.map.invalidateSize();
                            }, 350);
                        },

                        toggleMap() {
                            this.mapOpen = !this.mapOpen;
                            if (this.mapOpen) {
                                this.$nextTick(() => {
                                    this.ensureLeaflet(() => {
                                        this.setupMap();
                                    });
                                });
                            }
                        },

                        updateCoords(lat, lng) {
                            const fLat = parseFloat(lat).toFixed(6);
                            const fLng = parseFloat(lng).toFixed(6);
                            $wire.set('latitude', fLat);
                            $wire.set('longitude', fLng);
                            this.locError = '';
                        },

                        useCurrentLocation() {
                            if (!navigator.geolocation) {
                                this.locError = 'Geolocation is not supported by your browser.';
                                return;
                            }

                            this.isLocating = true;
                            this.locError = '';
                            this.locSuccess = '';

                            navigator.geolocation.getCurrentPosition(
                                (position) => {
                                    this.isLocating = false;
                                    const lat = position.coords.latitude;
                                    const lng = position.coords.longitude;
                                    this.updateCoords(lat, lng);
                                    this.locSuccess = 'GPS Location detected (' + lat.toFixed(4) + ', ' + lng.toFixed(4) + ')';
                                    this.mapOpen = true;

                                    this.$nextTick(() => {
                                        this.ensureLeaflet(() => {
                                            if (!this.map) {
                                                this.setupMap();
                                            } else {
                                                this.marker.setLatLng([lat, lng]);
                                                this.map.setView([lat, lng], 16);
                                                this.map.invalidateSize();
                                            }
                                        });
                                    });
                                },
                                (error) => {
                                    this.isLocating = false;
                                    if (error.code === error.PERMISSION_DENIED) {
                                        this.locError = 'Location access was denied. Please allow GPS permissions in your browser or click on the map to set coordinates.';
                                    } else if (error.code === error.POSITION_UNAVAILABLE) {
                                        this.locError = 'Location information is currently unavailable.';
                                    } else {
                                        this.locError = 'Could not get current location (' + error.message + ').';
                                    }
                                },
                                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
                            );
                        },

                        locateAddress() {
                            const query = (this.searchQuery || '').trim() || ($wire.address || '').trim() || ($wire.name || '').trim();
                            if (!query) {
                                this.locError = 'Please enter an address or search term first.';
                                return;
                            }

                            this.isSearching = true;
                            this.locError = '';
                            this.locSuccess = '';

                            fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query))
                                .then(res => res.json())
                                .then(data => {
                                    this.isSearching = false;
                                    if (data && data.length > 0) {
                                        const lat = parseFloat(data[0].lat);
                                        const lon = parseFloat(data[0].lon);
                                        this.updateCoords(lat, lon);
                                        this.locSuccess = 'Pinned to: ' + (data[0].display_name.split(',').slice(0, 3).join(', '));
                                        this.mapOpen = true;
                                        this.$nextTick(() => {
                                            this.ensureLeaflet(() => {
                                                if (!this.map) {
                                                    this.setupMap();
                                                } else {
                                                    this.marker.setLatLng([lat, lon]);
                                                    this.map.setView([lat, lon], 16);
                                                    this.map.invalidateSize();
                                                }
                                            });
                                        });
                                    } else {
                                        this.locError = 'Could not find coordinates for: \'' + query + '\'. You can click directly on the map to pinpoint.';
                                    }
                                })
                                .catch(err => {
                                    this.isSearching = false;
                                    this.locError = 'Address search failed. Please click on the map to pinpoint your location.';
                                });
                        },

                        onCoordInput() {
                            const lat = parseFloat($wire.latitude);
                            const lng = parseFloat($wire.longitude);
                            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                                if (this.map && this.marker) {
                                    this.marker.setLatLng([lat, lng]);
                                    this.map.panTo([lat, lng]);
                                }
                            }
                        }
                    }"
                    class="bg-white rounded-3xl overflow-hidden shadow-2xl transform transition-all w-full max-w-2xl z-50 border border-gray-100 max-h-[90vh] flex flex-col my-auto"
                >
                    <div class="p-6 sm:p-8 overflow-y-auto">
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                            <h3 class="text-base font-bold text-gray-900">
                                {{ $editingId ? __('Edit Location') : __('Create Location') }}
                            </h3>
                            <button wire:click="resetForm" class="p-1 rounded-lg text-gray-400 hover:bg-gray-100 focus:outline-none">
                                <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form wire:submit="saveLocation" class="space-y-5">
                            
                            <!-- Location Name -->
                            <div>
                                <x-input-label for="locName" :value="__('Location Name')" />
                                <x-text-input wire:model.live.debounce.250ms="name" id="locName" type="text" class="mt-1.5 block w-full" placeholder="e.g. Bandra Turf Complex" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>

                            <!-- Address -->
                            <div>
                                <x-input-label for="locAddress" :value="__('Address')" />
                                <textarea wire:model.live.debounce.250ms="address" id="locAddress" rows="3" class="mt-1.5 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl shadow-sm text-sm" placeholder="Full street address..."></textarea>
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            </div>

                            <!-- Geographic Coordinates & Map Picker -->
                            <div class="space-y-3 pt-1">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div>
                                        <x-input-label :value="__('Geographic Coordinates & Map')" class="font-bold text-gray-800" />
                                        <p class="text-[11px] text-gray-500 mt-0.5">{{ __('Select position from map, fetch device GPS, or enter manually.') }}</p>
                                    </div>

                                    <div class="flex items-center gap-2 flex-wrap">
                                        <!-- Current Location Button -->
                                        <button 
                                            type="button" 
                                            @click="useCurrentLocation" 
                                            :disabled="isLocating"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs border border-emerald-200 transition shadow-xs cursor-pointer disabled:opacity-50"
                                            title="Detect your device GPS coordinates"
                                        >
                                            <template x-if="!isLocating">
                                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <circle cx="12" cy="12" r="3" stroke-width="2" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m0 14v3m10-10h-3M5 12H2" />
                                                </svg>
                                            </template>
                                            <template x-if="isLocating">
                                                <svg class="w-3.5 h-3.5 animate-spin text-emerald-600" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                                </svg>
                                            </template>
                                            <span x-text="isLocating ? 'Detecting GPS...' : 'Current Location'"></span>
                                        </button>

                                        <!-- Locate on Map / Toggle Map Button -->
                                        <button 
                                            type="button" 
                                            @click="toggleMap" 
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs border border-indigo-200 transition shadow-xs cursor-pointer"
                                        >
                                            <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                            </svg>
                                            <span x-text="mapOpen ? 'Hide Map' : 'Locate on Map'"></span>
                                        </button>
                                    </div>
                                </div>

                                <!-- Feedback message -->
                                <div x-show="locError" x-cloak class="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center justify-between gap-2">
                                    <span x-text="locError"></span>
                                    <button type="button" @click="locError = ''" class="text-red-500 hover:text-red-700 font-bold">✕</button>
                                </div>

                                <div x-show="locSuccess" x-cloak class="p-2.5 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center justify-between gap-2">
                                    <span x-text="locSuccess"></span>
                                    <button type="button" @click="locSuccess = ''" class="text-emerald-600 hover:text-emerald-800 font-bold">✕</button>
                                </div>

                                <!-- Map Container Box -->
                                <div x-show="mapOpen" x-transition class="space-y-2">
                                    <!-- Search / Geocode Address input -->
                                    <div class="flex items-center gap-2">
                                        <div class="relative flex-1">
                                            <input 
                                                type="text" 
                                                x-model="searchQuery" 
                                                @keydown.enter.prevent="locateAddress"
                                                placeholder="Search landmark, street, or city (or use address above)..." 
                                                class="w-full text-xs pl-8 pr-3 py-2 border border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 bg-gray-50/60"
                                            />
                                            <svg class="w-3.5 h-3.5 text-gray-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <button 
                                            type="button" 
                                            @click="locateAddress" 
                                            :disabled="isSearching"
                                            class="px-3 py-2 bg-gray-800 hover:bg-gray-900 text-white rounded-xl text-xs font-bold transition disabled:opacity-50 cursor-pointer flex items-center gap-1 shrink-0"
                                        >
                                            <span x-show="isSearching" class="animate-spin text-[10px]">⏳</span>
                                            <span>Locate on Map</span>
                                        </button>
                                        <button 
                                            type="button" 
                                            @click="searchQuery = $wire.address; locateAddress()" 
                                            title="Search using the address text above"
                                            class="px-2.5 py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-semibold transition cursor-pointer shrink-0"
                                        >
                                            Use Address
                                        </button>
                                    </div>

                                    <!-- Leaflet Canvas -->
                                    <div class="relative rounded-2xl overflow-hidden border border-gray-200 shadow-inner z-0 isolate">
                                        <div x-ref="mapContainer" class="h-60 sm:h-64 w-full bg-slate-100"></div>
                                        <div class="absolute bottom-2 left-2 z-[400] bg-white/95 backdrop-blur-xs px-2.5 py-1 rounded-lg text-[10px] font-bold text-gray-700 shadow border border-gray-200/70 pointer-events-none flex items-center gap-1.5">
                                            <span>📍</span>
                                            <span>Click map or drag marker to set precise entrance</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Coordinates Inputs Grid -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="locLat" :value="__('Latitude')" />
                                        <x-text-input 
                                            wire:model.live.debounce.250ms="latitude" 
                                            @input="onCoordInput" 
                                            id="locLat" 
                                            type="text" 
                                            class="mt-1.5 block w-full text-xs font-mono" 
                                            placeholder="19.068200" 
                                        />
                                        <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="locLng" :value="__('Longitude')" />
                                        <x-text-input 
                                            wire:model.live.debounce.250ms="longitude" 
                                            @input="onCoordInput" 
                                            id="locLng" 
                                            type="text" 
                                            class="mt-1.5 block w-full text-xs font-mono" 
                                            placeholder="72.870300" 
                                        />
                                        <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                                    </div>
                                </div>
                            </div>

                            <!-- Contact details Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <div class="flex justify-between items-center">
                                        <x-input-label for="locPhone" :value="__('Contact Number')" />
                                        <button type="button" wire:click="useOwnDetails" class="text-[10px] text-indigo-600 hover:text-indigo-700 font-semibold transition duration-150 cursor-pointer">
                                            {{ __('Use my details') }}
                                        </button>
                                    </div>
                                    <x-text-input wire:model.live.debounce.250ms="contact_number" id="locPhone" type="text" class="mt-1.5 block w-full" placeholder="+91 XXXXXXXXXX" />
                                    <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                                </div>
                                <div>
                                    <div class="flex justify-between items-center">
                                        <x-input-label for="locEmail" :value="__('Email Address')" />
                                    </div>
                                    <x-text-input wire:model.live.debounce.250ms="email" id="locEmail" type="email" class="mt-1.5 block w-full" placeholder="contact@domain.com" />
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button" wire:click="resetForm" class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 transition duration-150 cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                                <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase shadow transition duration-150 cursor-pointer">
                                    {{ __('Save Location') }}
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
                <div class="bg-white rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-md z-50 border border-gray-100">
                    <div class="p-6 sm:p-8">
                        <div class="flex items-center gap-4 text-red-600 mb-4">
                            <div class="h-12 w-12 rounded-2xl bg-red-50 flex items-center justify-center border border-red-100/50 shrink-0">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-gray-900">
                                    {{ __('Confirm Delete') }}
                                </h3>
                                <p class="text-xs text-gray-500 mt-1">
                                    {{ __('This action cannot be undone.') }}
                                </p>
                            </div>
                        </div>

                        <p class="text-xs text-gray-600 mb-6 leading-relaxed">
                            {{ __('Are you sure you want to delete this location?') }}
                        </p>

                        <!-- Form Actions -->
                        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                            <button type="button" wire:click="cancelDelete" class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 transition duration-150 cursor-pointer">
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
