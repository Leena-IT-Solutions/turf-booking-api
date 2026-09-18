<?php

use App\Models\Location;
use App\Models\Sport;
use App\Models\Turf;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'All'; // 'All', 'Approved', 'Pending', 'Suspended'
    public string $sportFilter = 'All';
    public int $perPage = 12;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSportFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'All';
        $this->sportFilter = 'All';
        $this->resetPage();
    }

    public function with(): array
    {
        // High-level statistics for SaaS Admin
        $totalTurfsCount = Turf::count();
        $approvedTurfsCount = Turf::where('status', 'Approved')->count();
        $pendingTurfsCount = Turf::where('status', 'Pending')->count();
        $totalLocationsCount = Location::count();
        $totalOwnersCount = User::whereHas('roles', fn($q) => $q->where('name', 'turf-admin'))->count();

        // Main Query
        $query = Turf::with(['location.user', 'photos', 'sports', 'setting']);

        if (trim($this->search) !== '') {
            $searchTerm = trim($this->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('type', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('location', function ($lq) use ($searchTerm) {
                      $lq->where('name', 'LIKE', "%{$searchTerm}%")
                         ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                         ->orWhereHas('user', function ($uq) use ($searchTerm) {
                             $uq->where('name', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('email', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('mobile', 'LIKE', "%{$searchTerm}%");
                         });
                  });
            });
        }

        if ($this->statusFilter !== 'All') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->sportFilter !== 'All') {
            $query->where(function ($q) {
                $q->where('type', $this->sportFilter)
                  ->orWhereHas('sports', fn($sq) => $sq->where('sports.name', $this->sportFilter));
            });
        }

        $turfs = $query->orderBy('id', 'desc')->paginate($this->perPage);

        $availableSports = Sport::orderBy('name')->pluck('name')->unique()->values();

        return [
            'turfs' => $turfs,
            'totalTurfsCount' => $totalTurfsCount,
            'approvedTurfsCount' => $approvedTurfsCount,
            'pendingTurfsCount' => $pendingTurfsCount,
            'totalLocationsCount' => $totalLocationsCount,
            'totalOwnersCount' => $totalOwnersCount,
            'availableSports' => $availableSports,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header Banner -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold border border-emerald-500/30 mb-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    SaaS Superadmin Hub
                </span>
                <h1 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                    Turfs & Locations Directory
                </h1>
                <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl font-medium">
                    View all venues and turfs across India. Click <span class="text-emerald-400 font-bold">Access as Owner</span> to open any turf in a new tab logged in as that venue's owner with full workspace access.
                </p>
            </div>
            <div class="shrink-0 flex items-center gap-2">
                <a href="{{ route('saas.turf-verification') }}" wire:navigate class="px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-white/15">
                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Turf Verification Checklist</span>
                </a>
            </div>
        </div>

        <!-- Metric Counters -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-6 mt-6 border-t border-white/10">
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Total Turfs</span>
                <span class="text-xl sm:text-2xl font-black text-white block mt-0.5">{{ number_format($totalTurfsCount) }}</span>
            </div>
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-400 block">Approved & Active</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-400 block mt-0.5">{{ number_format($approvedTurfsCount) }}</span>
            </div>
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-400 block">Pending Verification</span>
                <span class="text-xl sm:text-2xl font-black text-amber-400 block mt-0.5">{{ number_format($pendingTurfsCount) }}</span>
            </div>
            <div class="bg-white/5 rounded-2xl p-3 border border-white/5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-400 block">Turf Owners</span>
                <span class="text-xl sm:text-2xl font-black text-indigo-400 block mt-0.5">{{ number_format($totalOwnersCount) }}</span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-white rounded-2xl border border-gray-200/90 p-4 sm:p-5 shadow-xs space-y-4">
        <div class="flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <!-- Search input -->
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search by turf name, location, address, owner name, email or mobile..." 
                    class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs sm:text-sm font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                @if ($search !== '')
                    <button wire:click="$set('search', '')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                @endif
            </div>

            <!-- Filters -->
            <div class="flex items-center gap-2 flex-wrap">
                <!-- Status Filter -->
                <select wire:model.live="statusFilter" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:bg-white focus:ring-indigo-500">
                    <option value="All">All Statuses</option>
                    <option value="Approved">Approved</option>
                    <option value="Pending">Pending</option>
                    <option value="Suspended">Suspended</option>
                </select>

                <!-- Sport Filter -->
                <select wire:model.live="sportFilter" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs font-bold text-gray-700 focus:bg-white focus:ring-indigo-500">
                    <option value="All">All Sports</option>
                    @foreach ($availableSports as $sport)
                        <option value="{{ $sport }}">{{ $sport }}</option>
                    @endforeach
                </select>

                @if ($search !== '' || $statusFilter !== 'All' || $sportFilter !== 'All')
                    <button wire:click="clearFilters" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-xs rounded-xl transition border border-rose-200">
                        Reset Filters
                    </button>
                @endif
            </div>
        </div>
    </div>

    <!-- Turf Cards Grid -->
    @if ($turfs->isEmpty())
        <div class="bg-white rounded-3xl border border-gray-200 p-12 text-center space-y-4 shadow-xs">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto text-gray-400">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h3 class="text-lg font-black text-gray-800">No Turfs Found</h3>
            <p class="text-xs text-gray-500 max-w-sm mx-auto font-medium">
                No turfs match your search criteria. Try modifying your search term or clearing the filters.
            </p>
            <button wire:click="clearFilters" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl transition shadow-xs">
                Show All Turfs
            </button>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($turfs as $turf)
                @php
                    $owner = $turf->location?->user;
                    $ownerInitials = $owner ? strtoupper(substr($owner->name, 0, 1) . (str_contains($owner->name, ' ') ? substr(explode(' ', $owner->name)[1], 0, 1) : '')) : 'NA';
                    $firstPhoto = $turf->photos->first()?->photo_path;
                    $statusColor = match($turf->status) {
                        'Approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                        'Pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                        default => 'bg-rose-50 text-rose-700 border-rose-200',
                    };
                @endphp

                <div class="bg-white rounded-3xl border border-gray-200/90 shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden flex flex-col justify-between">
                    <!-- Top Section -->
                    <div>
                        <!-- Turf Photo or Header Graphic -->
                        <div class="relative h-40 bg-gradient-to-br from-slate-800 to-slate-900 overflow-hidden">
                            @if ($firstPhoto)
                                <img src="{{ asset('storage/' . $firstPhoto) }}" alt="{{ $turf->name }}" class="w-full h-full object-cover">
                            @else
                                <div class="w-full h-full flex flex-col items-center justify-center text-slate-500 bg-slate-850">
                                    <svg class="w-12 h-12 text-slate-600 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <rect x="3" y="4" width="18" height="16" rx="2" stroke-width="1.5" />
                                        <line x1="12" y1="4" x2="12" y2="20" stroke-width="1.5" />
                                        <circle cx="12" cy="12" r="3" stroke-width="1.5" />
                                    </svg>
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Turf Ground</span>
                                </div>
                            @endif

                            <!-- Sport Type Pill -->
                            <div class="absolute top-3 left-3">
                                <span class="px-2.5 py-1 rounded-xl bg-black/60 backdrop-blur-md text-white text-[11px] font-extrabold border border-white/20">
                                    {{ $turf->type }}
                                </span>
                            </div>

                            <!-- Status Pill -->
                            <div class="absolute top-3 right-3">
                                <span class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase tracking-wider border {{ $statusColor }}">
                                    {{ $turf->status }}
                                </span>
                            </div>
                        </div>

                        <!-- Content Details -->
                        <div class="p-5 space-y-3.5">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 block">
                                    #TURF-{{ str_pad($turf->id, 4, '0', STR_PAD_LEFT) }}
                                </span>
                                <h3 class="text-base font-black text-gray-900 leading-snug">
                                    {{ $turf->name }}
                                </h3>
                                @if ($turf->location)
                                    <p class="text-xs text-gray-500 font-medium flex items-center gap-1 mt-1">
                                        <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="truncate">{{ $turf->location->name }} &bull; {{ $turf->address ?: $turf->location->address }}</span>
                                    </p>
                                @endif
                            </div>

                            <!-- Owner Info Card -->
                            <div class="bg-slate-50/80 rounded-2xl p-3 border border-slate-100 space-y-1.5">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">
                                    Turf Owner Account
                                </span>
                                @if ($owner)
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-black text-xs flex items-center justify-center shrink-0">
                                            {{ $ownerInitials }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <span class="font-bold text-xs text-gray-900 block truncate">{{ $owner->name }}</span>
                                            <span class="text-[11px] text-gray-500 block truncate font-medium">{{ $owner->mobile ?: $owner->email }}</span>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-rose-500 font-bold">No owner linked to this venue</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer: Action Button -->
                    <div class="px-5 pb-5 pt-2 border-t border-gray-100 flex items-center justify-between gap-3">
                        <span class="text-[11px] text-gray-400 font-medium">
                            Slots: <strong class="text-gray-700">{{ $turf->is_slots_verified ? 'Configured' : 'Pending' }}</strong>
                        </span>

                        @if ($owner)
                            <a 
                                href="{{ route('saas.turfs.impersonate', $turf->id) }}" 
                                target="_blank" 
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-black rounded-xl shadow-sm hover:shadow-md transition cursor-pointer"
                                title="Open this turf in a new tab logged in as {{ $owner->name }}"
                            >
                                <span>Access as Owner</span>
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                            </a>
                        @else
                            <button disabled class="px-3 py-1.5 bg-gray-100 text-gray-400 text-xs font-bold rounded-xl cursor-not-allowed">
                                Owner Missing
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="pt-4">
            {{ $turfs->links() }}
        </div>
    @endif
</div>
