<?php

use App\Models\Turf;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'All';

    // Modal verification states
    public $selectedTurfId = null;
    public $showVerifyModal = false;
    public $isLocationVerified = false;
    public $isDetailsVerified = false;
    public $isPhotosVerified = false;
    public $isFacilitiesVerified = false;
    public $isEquipmentsVerified = false;
    public $isSportsVerified = false;
    public $isSlotsVerified = false;
    public $isPricingVerified = false;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updateStatus($turfId, $status)
    {
        $turf = Turf::findOrFail($turfId);
        $turf->update(['status' => $status]);
        session()->flash('status', __('Turf status updated to :status successfully.', ['status' => $status]));
        if ($this->selectedTurfId == $turfId) {
            $this->showVerifyModal = false;
        }
    }

    public function openVerifyModal($turfId)
    {
        $turf = Turf::findOrFail($turfId);
        $this->selectedTurfId = $turf->id;
        $this->isLocationVerified = (bool)$turf->is_location_verified;
        $this->isDetailsVerified = (bool)$turf->is_details_verified;
        $this->isPhotosVerified = (bool)$turf->is_photos_verified;
        $this->isFacilitiesVerified = (bool)$turf->is_facilities_verified;
        $this->isEquipmentsVerified = (bool)$turf->is_equipments_verified;
        $this->isSportsVerified = (bool)$turf->is_sports_verified;
        $this->isSlotsVerified = (bool)$turf->is_slots_verified;
        $this->isPricingVerified = (bool)$turf->is_pricing_verified;
        $this->showVerifyModal = true;
    }

    public function saveVerification()
    {
        $turf = Turf::findOrFail($this->selectedTurfId);
        $allChecked = $this->isLocationVerified && 
                      $this->isDetailsVerified && 
                      $this->isPhotosVerified && 
                      $this->isFacilitiesVerified && 
                      $this->isEquipmentsVerified && 
                      $this->isSportsVerified && 
                      $this->isSlotsVerified && 
                      $this->isPricingVerified;

        $status = $allChecked ? 'Approved' : 'Review';

        $turf->update([
            'is_location_verified' => $this->isLocationVerified,
            'is_details_verified' => $this->isDetailsVerified,
            'is_photos_verified' => $this->isPhotosVerified,
            'is_facilities_verified' => $this->isFacilitiesVerified,
            'is_equipments_verified' => $this->isEquipmentsVerified,
            'is_sports_verified' => $this->isSportsVerified,
            'is_slots_verified' => $this->isSlotsVerified,
            'is_pricing_verified' => $this->isPricingVerified,
            'status' => $status
        ]);

        $this->showVerifyModal = false;
        session()->flash('status', __('Verification saved. Status updated to :status.', ['status' => $status]));
    }

    public function with()
    {
        $query = Turf::with(['location.user']);

        if ($this->statusFilter !== 'All') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('type', 'like', '%' . $this->search . '%')
                  ->orWhereHas('location', function ($l) {
                      $l->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('address', 'like', '%' . $this->search . '%')
                        ->orWhereHas('user', function ($u) {
                            $u->where('name', 'like', '%' . $this->search . '%')
                              ->orWhere('email', 'like', '%' . $this->search . '%')
                              ->orWhere('mobile', 'like', '%' . $this->search . '%');
                        });
                  });
            });
        }

        // Get status counts for the tabs
        $counts = [
            'All' => Turf::count(),
            'Draft' => Turf::where('status', 'Draft')->count(),
            'Pending' => Turf::where('status', 'Pending')->count(),
            'Approved' => Turf::where('status', 'Approved')->count(),
            'Review' => Turf::where('status', 'Review')->count(),
            'Rejected' => Turf::where('status', 'Rejected')->count(),
            'Hold' => Turf::where('status', 'Hold')->count(),
        ];

        // Active turf for the modal details
        $activeModalTurf = $this->selectedTurfId 
            ? Turf::with(['location.user', 'photos', 'facilities', 'turfEquipments', 'sports', 'slots.category'])->find($this->selectedTurfId)
            : null;

        return [
            'turfs' => $query->latest()->paginate(10),
            'counts' => $counts,
            'activeModalTurf' => $activeModalTurf,
        ];
    }
}; ?>

<div class="py-6" x-data="{ verifyModal: @entangle('showVerifyModal') }">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Turf Verification') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ __('Review and manage the verification status of sports turfs submitted by administrators.') }}</p>
            </div>
        </div>

        <!-- Status Flash Message -->
        @if (session('status'))
            <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30 text-emerald-800 dark:text-emerald-400 text-sm font-medium shadow-sm transition">
                {{ session('status') }}
            </div>
        @endif

        <!-- Filter Tab Buttons -->
        <div class="flex flex-wrap gap-2 pb-1">
            @foreach(['All', 'Draft', 'Pending', 'Approved', 'Review', 'Rejected', 'Hold'] as $filter)
                <button 
                    type="button" 
                    wire:click="$set('statusFilter', '{{ $filter }}')" 
                    class="px-4 py-2 text-xs font-bold uppercase tracking-wider rounded-xl border transition-all cursor-pointer flex items-center gap-2 {{
                        $statusFilter === $filter 
                            ? 'bg-indigo-600 border-indigo-650 text-white shadow-md shadow-indigo-500/10' 
                            : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-705'
                    }}"
                >
                    {{ __($filter) }}
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-[9px] font-black rounded-full {{
                        $statusFilter === $filter
                            ? 'bg-white/20 text-white'
                            : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400'
                    }}">
                        {{ $counts[$filter] }}
                    </span>
                </button>
            @endforeach
        </div>

        <!-- Search Bar -->
        <div class="relative bg-white dark:bg-gray-800 p-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
            <div class="absolute inset-y-0 start-4 flex items-center pointer-events-none">
                <svg class="h-4 w-4 text-gray-400 dark:text-gray-500 ms-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input 
                type="text" 
                wire:model.live.debounce.300ms="search" 
                placeholder="{{ __('Search turfs by name, type, area, location, or owner name/email/mobile...') }}"
                class="w-full pl-12 pr-4 py-2.5 bg-gray-50 dark:bg-gray-900/40 text-xs font-semibold text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-700 rounded-2xl focus:outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500/80 transition duration-150"
            />
        </div>

        <!-- Turfs Card Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
            @forelse ($turfs as $turf)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between relative group">
                    <div>
                        <!-- Turf Info Header -->
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
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[8px] font-black uppercase tracking-wider {{
                                            $turf->type === 'Synthetic' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : (
                                            $turf->type === 'Hard' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' :
                                            'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400')
                                        }}">
                                            {{ $turf->type }}
                                        </span>
                                        @if($turf->area)
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold">{{ $turf->area }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Current Status Badge -->
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-wider {{
                                $turf->status === 'Approved' ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400' : (
                                $turf->status === 'Pending' ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400' : (
                                $turf->status === 'Review' ? 'bg-indigo-100 dark:bg-indigo-950/20 text-indigo-700 dark:text-indigo-400' : (
                                $turf->status === 'Rejected' ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : (
                                $turf->status === 'Hold' ? 'bg-slate-100 dark:bg-slate-900/30 text-slate-700 dark:text-slate-400' :
                                'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'))))
                            }}">
                                {{ __($turf->status ?: 'Draft') }}
                            </span>
                        </div>

                        <!-- Location Section -->
                        <div class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-700/40 space-y-1">
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider block">{{ __('Location & Address') }}</span>
                            <div class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $turf->location->name }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400 leading-relaxed font-semibold">{{ $turf->location->address }}</div>
                        </div>

                        <!-- Owner Section -->
                        <div class="mt-3 pt-3 border-t border-gray-50 dark:border-gray-700/40 space-y-1">
                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider block">{{ __('Owner Details') }}</span>
                            <div class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ $turf->location->user->name }}</div>
                            <div class="text-[10px] text-gray-500 dark:text-gray-400 font-semibold flex flex-col gap-0.5">
                                <span>{{ $turf->location->user->email }}</span>
                                <span class="text-indigo-500 dark:text-indigo-400 font-bold">{{ $turf->location->user->mobile }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Footer -->
                    <div class="mt-6 pt-4 border-t border-gray-50 dark:border-gray-700/40">
                        <button 
                            type="button" 
                            wire:click="openVerifyModal({{ $turf->id }})" 
                            class="w-full px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-[10px] font-bold rounded-xl shadow-md shadow-indigo-500/10 hover:shadow-lg hover:shadow-indigo-500/20 transition flex items-center justify-center gap-2 cursor-pointer"
                        >
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            {{ __('Verify Details') }}
                        </button>
                    </div>
                </div>
            @empty
                <div class="md:col-span-2 xl:col-span-3 bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-100 dark:border-gray-700/50 text-center">
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                        {{ __('No turfs found matching criteria.') }}
                    </p>
                </div>
            @endforelse
        </div>

        <!-- Pagination Links -->
        @if ($turfs->hasPages())
            <div class="bg-white dark:bg-gray-800 px-6 py-4 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
                {{ $turfs->links() }}
            </div>
        @endif

    </div>

    <!-- Verify Details Modal Dialog -->
    <div 
        x-show="verifyModal" 
        class="fixed inset-0 z-50 overflow-hidden" 
        style="display: none;"
    >
        <!-- Backdrop -->
        <div 
            x-show="verifyModal"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-900/60 dark:bg-gray-950/80 backdrop-blur-sm"
            @click="verifyModal = false"
        ></div>

        <!-- Modal Wrapper -->
        <div class="flex h-full w-full items-center justify-center p-4 sm:p-6 md:p-8 overflow-hidden">
            <div 
                x-show="verifyModal"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-200 dark:border-gray-700 w-full max-w-6xl h-[85vh] max-h-[85vh] overflow-hidden grid grid-cols-1 md:grid-cols-3"
            >
                @if($activeModalTurf)
                    @php
                        $turfTiming = 'Not Available';
                        if ($activeModalTurf->slots && $activeModalTurf->slots->isNotEmpty()) {
                            $minTime = $activeModalTurf->slots->min('from_time');
                            $maxTime = $activeModalTurf->slots->max('to_time');
                            
                            $minHour = (int)substr($minTime, 0, 2);
                            $maxHour = (int)substr($maxTime, 0, 2);
                            
                            if (($minHour === 0 && $maxHour === 23) || $activeModalTurf->slots->count() >= 24) {
                                $turfTiming = '24 Hours';
                            } else {
                                $formattedMin = \Carbon\Carbon::parse($minTime)->format('h:i A');
                                $formattedMax = \Carbon\Carbon::parse($maxTime)->format('h:i A');
                                $turfTiming = "{$formattedMin} to {$formattedMax}";
                            }
                        }
                    @endphp
                    <!-- Left Panel: Details Display (Scrollable) -->
                    <div class="md:col-span-2 p-6 md:p-8 h-full overflow-y-auto space-y-6 border-b md:border-b-0 md:border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                        <div>
                            <span class="text-[9px] bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 px-2 py-0.5 rounded-lg font-bold uppercase tracking-wider">{{ $activeModalTurf->type }}</span>
                            <h3 class="text-xl font-extrabold text-gray-900 dark:text-white mt-1">{{ $activeModalTurf->name }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ __('Location: ') }}<span class="font-bold text-gray-800 dark:text-gray-200">{{ $activeModalTurf->location->name }}</span></p>
                        </div>

                        <!-- Location & Coordinates Section -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Location & Contacts') }}</h4>
                            <div class="p-4 bg-gray-50 dark:bg-gray-900/20 border border-gray-100 dark:border-gray-800 rounded-2xl space-y-2">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Address') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $activeModalTurf->location->address }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Coordinates') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $activeModalTurf->location->latitude }}, {{ $activeModalTurf->location->longitude }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Contact Number') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $activeModalTurf->location->contact_number ?: '-' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Contact Email') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $activeModalTurf->location->email ?: '-' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Turf Details Section -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Turf Configuration') }}</h4>
                            <div class="p-4 bg-gray-50 dark:bg-gray-900/20 border border-gray-100 dark:border-gray-800 rounded-2xl space-y-4">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Dimensions') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $activeModalTurf->area ?: '-' }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Timing') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $turfTiming }}</p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Booking Open') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ $activeModalTurf->is_booking_open ? __('Next :days Days', ['days' => $activeModalTurf->booking_open_days]) : __('Closed') }}
                                        </p>
                                    </div>
                                    <div>
                                        <span class="text-gray-400 dark:text-gray-500 block mb-0.5">{{ __('Cancellation Policy') }}</span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-200">
                                            {{ $activeModalTurf->is_cancellation_active ? __('Active (:hours Hrs / Fee: ₹:fee)', ['hours' => $activeModalTurf->cancellation_hours, 'fee' => $activeModalTurf->cancellation_fee]) : __('No Cancellation') }}
                                        </p>
                                    </div>
                                </div>
                                @if($activeModalTurf->description)
                                    <div class="text-xs pt-1 border-t border-gray-200/40 dark:border-gray-700/40">
                                        <span class="text-gray-400 dark:text-gray-500 block mb-1">{{ __('Description') }}</span>
                                        <p class="text-gray-700 dark:text-gray-300 leading-relaxed font-semibold">{{ $activeModalTurf->description }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Photos Section -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Uploaded Photos') }}</h4>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @forelse($activeModalTurf->photos as $photo)
                                    <div class="relative rounded-2xl overflow-hidden aspect-video border border-gray-150 dark:border-gray-800 bg-gray-50 dark:bg-gray-900">
                                        <img src="{{ Storage::url($photo->photo) }}" class="w-full h-full object-cover">
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 dark:text-gray-500 font-semibold col-span-3">{{ __('No photos uploaded.') }}</p>
                                @endforelse
                            </div>
                        </div>

                        <!-- Facilities, Equipments, Sports -->
                        <div class="space-y-4">
                            <!-- Facilities -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Facilities') }}</h4>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($activeModalTurf->facilities as $fac)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-[10px] font-bold bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800">
                                            {{ $fac->name }}
                                        </span>
                                    @empty
                                        <p class="text-xs text-gray-400 dark:text-gray-500 font-semibold">{{ __('None.') }}</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Equipments -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Equipments') }}</h4>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($activeModalTurf->turfEquipments as $eq)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-[10px] font-bold bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800">
                                            {{ $eq->name }}
                                        </span>
                                    @empty
                                        <p class="text-xs text-gray-400 dark:text-gray-500 font-semibold">{{ __('None.') }}</p>
                                    @endforelse
                                </div>
                            </div>

                            <!-- Sports -->
                            <div class="space-y-2">
                                <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Sports') }}</h4>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse($activeModalTurf->sports as $sp)
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-[10px] font-bold bg-gray-50 dark:bg-gray-900/40 text-gray-700 dark:text-gray-300 border border-gray-100 dark:border-gray-800">
                                            @if($sp->icon)
                                                <span class="h-3 w-3 shrink-0 flex items-center justify-center text-indigo-500">{!! $sp->icon !!}</span>
                                            @endif
                                            {{ $sp->name }}
                                        </span>
                                    @empty
                                        <p class="text-xs text-gray-400 dark:text-gray-500 font-semibold">{{ __('None.') }}</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <!-- Slots & Pricing Section -->
                        <div class="space-y-2">
                            <h4 class="text-xs font-black text-gray-400 dark:text-gray-500 uppercase tracking-widest">{{ __('Slots & Pricing') }}</h4>
                            @php
                                $groupedSlots = [];
                                if ($activeModalTurf && $activeModalTurf->slots) {
                                    $groupedSlots = $activeModalTurf->slots->groupBy(function($slot) {
                                        return $slot->category?->name ?? 'Uncategorized';
                                    })->map(function($categorySlots) {
                                        return $categorySlots->map(function($slot) {
                                            return [
                                                'time' => substr($slot->from_time, 0, 5) . ' - ' . substr($slot->to_time, 0, 5),
                                                'pricing' => [
                                                    'Mon' => (float)($slot->pivot->mon ?: 0),
                                                    'Tue' => (float)($slot->pivot->tue ?: 0),
                                                    'Wed' => (float)($slot->pivot->wed ?: 0),
                                                    'Thu' => (float)($slot->pivot->thu ?: 0),
                                                    'Fri' => (float)($slot->pivot->fri ?: 0),
                                                    'Sat' => (float)($slot->pivot->sat ?: 0),
                                                    'Sun' => (float)($slot->pivot->sun ?: 0),
                                                ]
                                            ];
                                        });
                                    });
                                }
                            @endphp

                            <div class="text-xs">
                                <pre class="text-[10px] leading-relaxed font-semibold bg-gray-50 dark:bg-gray-900/20 p-4 rounded-2xl border border-gray-100 dark:border-gray-800 text-gray-700 dark:text-gray-300 overflow-x-auto whitespace-pre-wrap max-h-[300px] overflow-y-auto custom-scrollbar font-mono">{{ json_encode($groupedSlots, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                            
                            @if($activeModalTurf->pricing_wizard_data)
                                <div class="text-xs pt-2">
                                    <span class="text-gray-400 dark:text-gray-500 font-medium block mb-1">{{ __('Pricing Wizard Config:') }}</span>
                                    <pre class="text-[10px] leading-relaxed font-semibold bg-gray-50 dark:bg-gray-900/20 p-3 rounded-2xl border border-gray-100 dark:border-gray-800 text-gray-650 dark:text-gray-350 overflow-x-auto font-mono">{{ json_encode($activeModalTurf->pricing_wizard_data, JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right Panel: Checklist & Verification Controls -->
                    <div class="md:col-span-1 p-6 md:p-8 flex flex-col justify-between h-full overflow-hidden bg-gray-50 dark:bg-gray-900 border-t md:border-t-0 border-gray-150 dark:border-gray-800">
                        <div class="space-y-4 flex flex-col flex-1 min-h-0">
                            <div>
                                <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-wider">{{ __('Verification Checklist') }}</h3>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-1">{{ __('Mark all specifications as verified and correct. Unchecked items indicate review feedback.') }}</p>
                            </div>

                            <!-- Checkboxes List -->
                            <div class="space-y-3 overflow-y-auto flex-1 pr-1.5 custom-scrollbar min-h-0">
                                <!-- Location -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isLocationVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Location') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify ground coordinates, address and contact details.') }}</p>
                                    </div>
                                </label>

                                <!-- Turf Details -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isDetailsVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Turf Details') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify turf name, surface type, dimensions and policy details.') }}</p>
                                    </div>
                                </label>

                                <!-- Photos -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isPhotosVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Photos') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify photo attachments are appropriate and realistic.') }}</p>
                                    </div>
                                </label>

                                <!-- Facilities -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isFacilitiesVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Facilities') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify listed ground amenities and amenities configurations.') }}</p>
                                    </div>
                                </label>

                                <!-- Equipments -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isEquipmentsVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Equipments') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify inventory checklist and configurations.') }}</p>
                                    </div>
                                </label>

                                <!-- Sports -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isSportsVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Sports') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify assigned game configurations matches coordinates.') }}</p>
                                    </div>
                                </label>

                                <!-- Slots -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isSlotsVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Slots') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify time slots allocations and duration values.') }}</p>
                                    </div>
                                </label>

                                <!-- Pricing -->
                                <label class="flex items-start gap-3 p-3 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 rounded-2xl border border-gray-200/50 dark:border-gray-700/60 transition duration-150 cursor-pointer">
                                    <input type="checkbox" wire:model="isPricingVerified" class="mt-0.5 h-4.5 w-4.5 text-indigo-600 border-gray-300 rounded-lg focus:ring-indigo-500 cursor-pointer">
                                    <div>
                                        <span class="text-xs font-bold text-gray-800 dark:text-gray-200">{{ __('Pricing') }}</span>
                                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Verify weekday pricing rates, part-payments settings.') }}</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Buttons & Controls -->
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700 space-y-4 shrink-0">
                            <!-- Direct Status Actions -->
                            <div class="grid grid-cols-2 gap-2">
                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $activeModalTurf->id }}, 'Approved')" 
                                    class="px-2 py-2 {{ $activeModalTurf->status === 'Approved' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 border border-emerald-100/50 dark:border-emerald-900/30' }} rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ __('Approve') }}
                                </button>

                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $activeModalTurf->id }}, 'Review')" 
                                    class="px-2 py-2 {{ $activeModalTurf->status === 'Review' ? 'bg-indigo-600 text-white' : 'bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 border border-indigo-100/50 dark:border-indigo-900/30' }} rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    {{ __('Review') }}
                                </button>

                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $activeModalTurf->id }}, 'Hold')" 
                                    class="px-2 py-2 {{ $activeModalTurf->status === 'Hold' ? 'bg-slate-600 text-white' : 'bg-slate-50 dark:bg-slate-900/40 text-slate-600 dark:text-slate-400 border border-slate-200/50 dark:border-slate-700/30' }} rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ __('Hold') }}
                                </button>

                                <button 
                                    type="button" 
                                    wire:click="updateStatus({{ $activeModalTurf->id }}, 'Rejected')" 
                                    class="px-2 py-2 {{ $activeModalTurf->status === 'Rejected' ? 'bg-red-600 text-white' : 'bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-100/50 dark:border-red-900/30' }} rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer text-[10px] font-extrabold uppercase tracking-wide"
                                >
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                    {{ __('Reject') }}
                                </button>
                            </div>

                            <!-- Submit Verification Checklist -->
                            <div class="flex items-center gap-2">
                                <button 
                                    type="button" 
                                    @click="verifyModal = false" 
                                    class="flex-1 px-4 py-2.5 bg-gray-250 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-650 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-xl transition flex items-center justify-center cursor-pointer"
                                >
                                    {{ __('Close') }}
                                </button>
                                <button 
                                    type="button" 
                                    wire:click="saveVerification" 
                                    class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-755 text-white text-xs font-bold rounded-xl shadow-md shadow-indigo-500/10 hover:shadow-lg hover:shadow-indigo-500/20 transition flex items-center justify-center cursor-pointer"
                                >
                                    {{ __('Save Checklist') }}
                                </button>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
