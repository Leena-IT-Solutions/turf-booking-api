<?php

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CommissionInvoice;
use App\Models\CommissionWalletTransaction;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Sport;
use App\Models\Turf;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    public bool $showOwnerOnboardModal = false;
    public string $ownerSearch = '';

    // Delete Turf Confirmation Modal State (SaaS Admin Only)
    public ?int $deletingTurfId = null;
    public bool $showDeleteTurfModal = false;
    public bool $deleteWalletTransactions = true;
    public bool $deleteCommissionInvoices = true;
    public bool $deleteEmptyLocation = true;

    public function confirmDeleteTurf(int $turfId): void
    {
        if (!auth()->user()?->hasRole('saas-admin')) {
            abort(403, 'Only SaaS Admins can delete turfs.');
        }

        $turf = Turf::findOrFail($turfId);
        $this->deletingTurfId = $turf->id;
        $this->deleteWalletTransactions = true;
        $this->deleteCommissionInvoices = true;
        $this->deleteEmptyLocation = true;
        $this->showDeleteTurfModal = true;
    }

    public function cancelDeleteTurf(): void
    {
        $this->deletingTurfId = null;
        $this->showDeleteTurfModal = false;
    }

    public function performDeleteTurf(): void
    {
        if (!auth()->user()?->hasRole('saas-admin')) {
            abort(403, 'Only SaaS Admins can delete turfs.');
        }

        if (!$this->deletingTurfId) {
            return;
        }

        $turf = Turf::with(['location.user', 'photos'])->findOrFail($this->deletingTurfId);
        $turfName = $turf->name;
        $location = $turf->location;
        $owner = $location?->user;

        // 1. Delete physical photo files from public storage disk
        foreach ($turf->photos as $photo) {
            if ($photo->photo && Storage::disk('public')->exists($photo->photo)) {
                Storage::disk('public')->delete($photo->photo);
            }
        }

        DB::transaction(function () use ($turf, $location, $owner) {
            $bookingIds = Booking::where('turf_id', $turf->id)->pluck('id')->toArray();
            $paymentIds = !empty($bookingIds)
                ? Payment::whereIn('booking_id', $bookingIds)->pluck('id')->toArray()
                : [];
            $cancellationIds = !empty($bookingIds)
                ? BookingCancellation::whereIn('booking_id', $bookingIds)->pluck('id')->toArray()
                : [];

            // Option A: Delete Wallet Passbook Transactions for this turf's bookings & recalculate owner balance
            if ($this->deleteWalletTransactions && $owner && (!empty($bookingIds) || !empty($paymentIds) || !empty($cancellationIds))) {
                $txQuery = CommissionWalletTransaction::where('user_id', $owner->id)
                    ->where(function ($q) use ($bookingIds, $paymentIds, $cancellationIds) {
                        if (!empty($paymentIds)) {
                            $q->orWhere(function ($sq) use ($paymentIds) {
                                $sq->where('reference_type', Payment::class)
                                   ->whereIn('reference_id', $paymentIds);
                            });
                        }
                        if (!empty($bookingIds)) {
                            $q->orWhere(function ($sq) use ($bookingIds) {
                                $sq->where('reference_type', Booking::class)
                                   ->whereIn('reference_id', $bookingIds);
                            });
                        }
                        if (!empty($cancellationIds)) {
                            $q->orWhere(function ($sq) use ($cancellationIds) {
                                $sq->where('reference_type', BookingCancellation::class)
                                   ->whereIn('reference_id', $cancellationIds);
                            });
                        }
                    });

                $txIdsToDelete = $txQuery->pluck('id')->toArray();

                // Also check JSON meta->booking_id for any wallet entries linked via meta
                if (!empty($bookingIds)) {
                    $allOwnerTxs = CommissionWalletTransaction::where('user_id', $owner->id)->get(['id', 'meta']);
                    foreach ($allOwnerTxs as $tx) {
                        $metaBookingId = $tx->meta['booking_id'] ?? null;
                        if ($metaBookingId && in_array((int) $metaBookingId, array_map('intval', $bookingIds), true)) {
                            $txIdsToDelete[] = $tx->id;
                        }
                    }
                }

                $txIdsToDelete = array_values(array_unique($txIdsToDelete));
                if (!empty($txIdsToDelete)) {
                    CommissionWalletTransaction::whereIn('id', $txIdsToDelete)->delete();
                }

                // Recalculate rolling balance_after and owner's commission_wallet_balance
                $remainingTxs = CommissionWalletTransaction::where('user_id', $owner->id)
                    ->orderBy('id', 'asc')
                    ->get();

                $runningBalance = 0.0;
                foreach ($remainingTxs as $rTx) {
                    $runningBalance = round($runningBalance + (float) $rTx->amount, 2);
                    if ((float) $rTx->balance_after !== $runningBalance) {
                        $rTx->update(['balance_after' => $runningBalance]);
                    }
                }

                $owner->update([
                    'commission_wallet_balance' => $runningBalance,
                    'commission_due_since' => $runningBalance < 0 ? ($owner->commission_due_since ?: now()) : null,
                ]);
            }

            // Option B: Delete Monthly Commission Invoices for this turf
            if ($this->deleteCommissionInvoices) {
                CommissionInvoice::where('turf_id', $turf->id)->delete();
            }

            // Delete all bookings of this turf (cascades booking_dates, booking_slots, payments, cancellations, reschedules, coupon_usages)
            Booking::where('turf_id', $turf->id)->delete();

            // Delete the turf itself (cascades turf_photos, slot_turf, slot_locks, facility_turf, equipment_turf, sport_turf, turf_settings, coupons, reviews, staff_turf, turf_subscriptions)
            $turf->delete();

            // Option C: Delete parent Location if it has no other turfs left
            if ($this->deleteEmptyLocation && $location && $location->turfs()->count() === 0) {
                $location->delete();
            }
        });

        $this->cancelDeleteTurf();
        session()->flash('status', "Turf '{$turfName}' and all selected associated records have been permanently deleted.");
    }

    public function openOwnerOnboardModal(): void
    {
        $this->ownerSearch = '';
        $this->showOwnerOnboardModal = true;
    }

    public function closeOwnerOnboardModal(): void
    {
        $this->showOwnerOnboardModal = false;
    }

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

        $ownersQuery = User::whereHas('roles', fn ($q) => $q->where('name', 'turf-admin'))
            ->withCount('locations');

        if (trim($this->ownerSearch) !== '') {
            $os = trim($this->ownerSearch);
            $ownersQuery->where(function ($q) use ($os) {
                $q->where('name', 'LIKE', "%{$os}%")
                  ->orWhere('email', 'LIKE', "%{$os}%")
                  ->orWhere('mobile', 'LIKE', "%{$os}%");
            });
        }

        $turfOwners = $ownersQuery->orderBy('locations_count', 'asc')->orderBy('id', 'desc')->limit(25)->get();

        $deletingTurf = null;
        $deletingStats = [
            'bookings_count' => 0,
            'photos_count' => 0,
            'wallet_tx_count' => 0,
            'invoices_count' => 0,
            'other_turfs_in_location' => 0,
        ];

        if ($this->deletingTurfId) {
            $deletingTurf = Turf::with(['location.user', 'photos'])->find($this->deletingTurfId);
            if ($deletingTurf) {
                $bIds = Booking::where('turf_id', $deletingTurf->id)->pluck('id')->toArray();
                $pIds = !empty($bIds) ? Payment::whereIn('booking_id', $bIds)->pluck('id')->toArray() : [];
                $cIds = !empty($bIds) ? BookingCancellation::whereIn('booking_id', $bIds)->pluck('id')->toArray() : [];
                $ownerUser = $deletingTurf->location?->user;

                $walletTxCount = 0;
                if ($ownerUser && (!empty($bIds) || !empty($pIds) || !empty($cIds))) {
                    $walletTxCount = CommissionWalletTransaction::where('user_id', $ownerUser->id)
                        ->where(function ($q) use ($bIds, $pIds, $cIds) {
                            if (!empty($pIds)) {
                                $q->orWhere(fn ($sq) => $sq->where('reference_type', Payment::class)->whereIn('reference_id', $pIds));
                            }
                            if (!empty($bIds)) {
                                $q->orWhere(fn ($sq) => $sq->where('reference_type', Booking::class)->whereIn('reference_id', $bIds));
                            }
                            if (!empty($cIds)) {
                                $q->orWhere(fn ($sq) => $sq->where('reference_type', BookingCancellation::class)->whereIn('reference_id', $cIds));
                            }
                        })->count();
                }

                $deletingStats = [
                    'bookings_count' => count($bIds),
                    'photos_count' => $deletingTurf->photos->count(),
                    'wallet_tx_count' => $walletTxCount,
                    'invoices_count' => CommissionInvoice::where('turf_id', $deletingTurf->id)->count(),
                    'other_turfs_in_location' => $deletingTurf->location
                        ? $deletingTurf->location->turfs()->where('id', '!=', $deletingTurf->id)->count()
                        : 0,
                ];
            }
        }

        return [
            'turfs' => $turfs,
            'totalTurfsCount' => $totalTurfsCount,
            'approvedTurfsCount' => $approvedTurfsCount,
            'pendingTurfsCount' => $pendingTurfsCount,
            'totalLocationsCount' => $totalLocationsCount,
            'totalOwnersCount' => $totalOwnersCount,
            'availableSports' => $availableSports,
            'turfOwners' => $turfOwners,
            'deletingTurf' => $deletingTurf,
            'deletingStats' => $deletingStats,
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
            <div class="shrink-0 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="openOwnerOnboardModal"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-black transition flex items-center gap-1.5 shadow-sm cursor-pointer"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Setup Location / Turf for Owner</span>
                </button>
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
    @if (session()->has('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 shadow-2xs">
            <span>✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

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
                    $firstPhoto = $turf->photos->first()?->photo ?? $turf->photos->first()?->photo_path;
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
                                            <div class="flex items-center gap-1.5 justify-between">
                                                <span class="font-bold text-xs text-gray-900 block truncate">{{ $owner->name }}</span>
                                                @if ($owner->onTrial())
                                                    <span class="shrink-0 px-1.5 py-0.5 rounded text-[8px] font-black uppercase bg-emerald-100 text-emerald-800 border border-emerald-300">
                                                        Trial · {{ $owner->trial_ends_at->format('d M') }}
                                                    </span>
                                                @elseif ($owner->trial_started_at !== null)
                                                    <span class="shrink-0 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase bg-gray-100 text-gray-500">
                                                        Trial ended
                                                    </span>
                                                @endif
                                            </div>
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
                    <div class="px-5 pb-5 pt-2 border-t border-gray-100 flex items-center justify-between gap-2">
                        <span class="text-[11px] text-gray-400 font-medium">
                            Slots: <strong class="text-gray-700">{{ $turf->is_slots_verified ? 'Configured' : 'Pending' }}</strong>
                        </span>

                        <div class="flex items-center gap-2">
                            <a
                                href="{{ route('turf.qr.download.poster', ['turf' => $turf->id, 'size' => 'a4']) }}"
                                title="Download A4 Venue QR Poster PDF"
                                class="p-2 rounded-xl bg-indigo-50 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 transition cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </a>

                            @if ($owner)
                                <a 
                                    href="{{ route('saas.turfs.impersonate', $turf->id) }}" 
                                    target="_blank" 
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white text-xs font-black rounded-xl shadow-sm hover:shadow-md transition cursor-pointer"
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

                            @if (auth()->user()->hasRole('saas-admin'))
                                <button
                                    type="button"
                                    wire:click="confirmDeleteTurf({{ $turf->id }})"
                                    title="Delete Turf & Bookings"
                                    class="p-2 rounded-xl bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white border border-rose-200 transition cursor-pointer"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="pt-4">
            {{ $turfs->links() }}
        </div>
    @endif

    <!-- Setup Location / Turf for Owner Modal -->
    @if ($showOwnerOnboardModal)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="closeOwnerOnboardModal"></div>

            <div class="bg-white rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-2xl z-50 border border-gray-100">
                <div class="p-6 sm:p-8 space-y-5">
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                        <div>
                            <h3 class="text-base font-black text-gray-900">Setup Location / Turf for Turf Owner</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Select any Turf Owner account below (including newly registered owners with 0 turfs) to open their workspace and configure their Location, Turf, Photos, Slots & Pricing.</p>
                        </div>
                        <button type="button" wire:click="closeOwnerOnboardModal" class="p-1.5 rounded-xl text-gray-400 hover:bg-gray-100 hover:text-gray-600 cursor-pointer">
                            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="ownerSearch"
                            placeholder="Search Turf Owner by name, mobile, or email..."
                            class="w-full px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-xs sm:text-sm font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <div class="space-y-2.5 max-h-80 overflow-y-auto pr-1">
                        @forelse ($turfOwners as $ownerItem)
                            <div class="p-3.5 rounded-2xl border {{ $ownerItem->locations_count === 0 ? 'bg-amber-50/40 border-amber-200' : 'bg-gray-50/60 border-gray-200/80' }} flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-xs sm:text-sm text-gray-900 truncate">{{ $ownerItem->name }}</span>
                                        @if ($ownerItem->locations_count === 0)
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-100 text-amber-800 border border-amber-300">
                                                No Location Yet · Needs Setup
                                            </span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                {{ $ownerItem->locations_count }} {{ \Illuminate\Support\Str::plural('Location', $ownerItem->locations_count) }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-gray-500 font-medium mt-0.5">
                                        {{ $ownerItem->mobile }} &bull; {{ $ownerItem->email }}
                                    </div>
                                </div>

                                <a
                                    href="{{ route('saas.users.impersonate', $ownerItem->id) }}"
                                    target="_blank"
                                    class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition cursor-pointer"
                                >
                                    <span>{{ $ownerItem->locations_count === 0 ? 'Add Location & Turf' : 'Manage Workspace' }}</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </div>
                        @empty
                            <div class="py-8 text-center text-xs text-gray-500">
                                No Turf Owner accounts found matching your search.
                            </div>
                        @endforelse
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                        <a href="{{ route('saas.users') }}" wire:navigate class="text-xs font-bold text-indigo-600 hover:text-indigo-700">
                            + Create New Turf Owner in User Accounts &rarr;
                        </a>
                        <button type="button" wire:click="closeOwnerOnboardModal" class="px-4 py-2 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Delete Turf & Bookings Confirmation Modal (SaaS Admin Only) -->
    @if ($showDeleteTurfModal && $deletingTurf)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="cancelDeleteTurf"></div>

            <div class="bg-white rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-xl z-50 border border-gray-100">
                <div class="p-6 sm:p-8 space-y-5">
                    <div class="flex items-start justify-between gap-4 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-3.5">
                            <div class="h-12 w-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100 shrink-0">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-base font-black text-gray-900">Delete Turf & All Bookings</h3>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    <strong>{{ $deletingTurf->name }}</strong>
                                    @if ($deletingTurf->location)
                                        &bull; {{ $deletingTurf->location->name }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <button type="button" wire:click="cancelDeleteTurf" class="p-1.5 rounded-xl text-gray-400 hover:bg-gray-100 cursor-pointer">
                            <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Impact Summary Counters -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                        <div class="p-3 rounded-2xl bg-rose-50/70 border border-rose-100 text-center">
                            <span class="block text-lg font-black text-rose-700 font-mono">{{ $deletingStats['bookings_count'] }}</span>
                            <span class="block text-[10px] font-bold uppercase text-rose-600">Bookings</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-gray-50 border border-gray-200/70 text-center">
                            <span class="block text-lg font-black text-gray-800 font-mono">{{ $deletingStats['photos_count'] }}</span>
                            <span class="block text-[10px] font-bold uppercase text-gray-500">Photos</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-amber-50/70 border border-amber-100 text-center">
                            <span class="block text-lg font-black text-amber-800 font-mono">{{ $deletingStats['wallet_tx_count'] }}</span>
                            <span class="block text-[10px] font-bold uppercase text-amber-700">Wallet Entries</span>
                        </div>
                        <div class="p-3 rounded-2xl bg-indigo-50/70 border border-indigo-100 text-center">
                            <span class="block text-lg font-black text-indigo-800 font-mono">{{ $deletingStats['invoices_count'] }}</span>
                            <span class="block text-[10px] font-bold uppercase text-indigo-700">Invoices</span>
                        </div>
                    </div>

                    <p class="text-xs text-gray-600 leading-relaxed">
                        Deleting this turf will permanently remove all <strong>{{ $deletingStats['bookings_count'] }} booking(s)</strong> (including booking dates, slots, payments, cancellations &amp; reschedules), <strong>{{ $deletingStats['photos_count'] }} photo file(s)</strong> from storage, slot pricing, locks, coupons, reviews, and taxation settings.
                    </p>

                    <!-- Checkboxes A, B & C -->
                    <div class="space-y-2.5 bg-gray-50 p-4 rounded-2xl border border-gray-200/80">
                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-500 mb-1">Additional Cleanup Options</div>

                        <label class="flex items-start gap-3 cursor-pointer select-none">
                            <input type="checkbox" wire:model="deleteWalletTransactions" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500" />
                            <div class="text-xs">
                                <span class="font-bold text-gray-900 block">Delete Owner's Wallet Passbook entries ({{ $deletingStats['wallet_tx_count'] }}) &amp; recalculate wallet balance</span>
                                <span class="text-[11px] text-gray-500">Removes credits/debits from this turf's bookings so the owner's passbook has no ghost entries.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer select-none pt-2 border-t border-gray-200/60">
                            <input type="checkbox" wire:model="deleteCommissionInvoices" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500" />
                            <div class="text-xs">
                                <span class="font-bold text-gray-900 block">Delete Monthly Commission Invoices ({{ $deletingStats['invoices_count'] }}) for this turf</span>
                                <span class="text-[11px] text-gray-500">Removes generated commission invoices tied to this turf instead of leaving them unlinked.</span>
                            </div>
                        </label>

                        @if ($deletingTurf->location && $deletingStats['other_turfs_in_location'] === 0)
                            <label class="flex items-start gap-3 cursor-pointer select-none pt-2 border-t border-gray-200/60">
                                <input type="checkbox" wire:model="deleteEmptyLocation" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500" />
                                <div class="text-xs">
                                    <span class="font-bold text-gray-900 block">Also delete parent Location ({{ $deletingTurf->location->name }})</span>
                                    <span class="text-[11px] text-gray-500">This is the only turf inside {{ $deletingTurf->location->name }}.</span>
                                </div>
                            </label>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button type="button" wire:click="cancelDeleteTurf" class="px-5 py-2.5 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" wire:click="performDeleteTurf" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-sm transition cursor-pointer">
                            Yes, Permanently Delete Turf
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
