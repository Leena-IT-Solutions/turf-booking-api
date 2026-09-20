<?php

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\Turf;
use App\Services\BookingCancellationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // --- Tab ---
    public string $activeTab = 'cancellations'; // 'active_bookings' | 'cancellations'

    // --- Active Bookings Filters ---
    public string $bookingSearch = '';
    public string $bookingDateFilter = '';

    // --- Cancellation Table Filters ---
    public string $cancelSearch = '';
    public string $cancelStatusFilter = 'all';

    // --- Cancel Modal ---
    public bool $showCancelModal = false;
    public ?int $cancelBookingId = null;
    public array $selectedDateIds = [];
    public string $cancelReason = '';

    // --- Resolve Refund Modal ---
    public bool $showResolveModal = false;
    public ?int $resolveCancellationId = null;
    public string $resolutionMode = 'standard_policy';
    public string $disbursementChannel = 'offline';
    public string $customRefundAmount = '';
    public string $offlineReference = '';

    #[On('global-context-updated')]
    public function refreshContext()
    {
        $this->resetPage();
    }

    public function updatingBookingSearch() { $this->resetPage(); }
    public function updatingCancelSearch() { $this->resetPage(); }
    public function updatingCancelStatusFilter() { $this->resetPage(); }

    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    // --- Cancel Modal Methods ---
    public function openCancelModal(int $bookingId)
    {
        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->with('bookingDates')->find($bookingId);
        if (!$booking) return;

        $this->cancelBookingId = $bookingId;
        $this->selectedDateIds = $booking->bookingDates->where('status', '!=', 'Cancelled')->pluck('id')->toArray();
        $this->cancelReason = '';
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancelBookingId = null;
        $this->selectedDateIds = [];
        $this->cancelReason = '';
    }

    public function submitCancellation()
    {
        if (!$this->cancelBookingId || empty($this->selectedDateIds)) {
            session()->flash('error', 'Please select at least one booking date to cancel.');
            return;
        }

        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->find($this->cancelBookingId);
        if (!$booking) {
            session()->flash('error', 'Booking not found or unauthorized.');
            return;
        }

        $service = new BookingCancellationService();
        $result = $service->cancelBookingDates(
            $booking,
            $this->selectedDateIds,
            auth()->user(),
            $this->cancelReason ?: 'Cancelled by turf admin',
            true // isAdmin = true (bypasses cutoff)
        );

        if ($result['success']) {
            session()->flash('status', $result['message']);
            $this->closeCancelModal();
            $this->activeTab = 'cancellations';
        } else {
            session()->flash('error', $result['message']);
        }
    }

    // --- Resolve Refund Modal Methods ---
    public function openResolveModal(int $cancellationId)
    {
        $this->resolveCancellationId = $cancellationId;
        $this->resolutionMode = 'standard_policy';
        $this->disbursementChannel = 'offline';
        $this->customRefundAmount = '';
        $this->offlineReference = '';
        $this->showResolveModal = true;
    }

    public function closeResolveModal()
    {
        $this->showResolveModal = false;
        $this->resolveCancellationId = null;
    }

    public function submitResolveRefund()
    {
        if (!$this->resolveCancellationId) return;

        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $cancellation = BookingCancellation::whereHas('booking', function ($q) use ($manageableTurfIds) {
            $q->whereIn('turf_id', $manageableTurfIds);
        })->find($this->resolveCancellationId);

        if (!$cancellation) {
            session()->flash('error', 'Cancellation record not found.');
            return;
        }

        $service = new BookingCancellationService();
        $result = $service->resolveRefund(
            $cancellation,
            $this->resolutionMode,
            $this->disbursementChannel,
            auth()->user(),
            $this->resolutionMode === 'custom' ? (float)$this->customRefundAmount : null,
            $this->disbursementChannel === 'offline' ? $this->offlineReference : null
        );

        if ($result['success']) {
            session()->flash('status', $result['message']);
            $this->closeResolveModal();
        } else {
            session()->flash('error', $result['message']);
        }
    }

    public function formatConsecutiveSlots($bookingSlots): array
    {
        $slots = [];
        $seen = [];
        foreach ($bookingSlots as $bs) {
            if ($bs->slot && $bs->slot->from_time && $bs->slot->to_time) {
                $timeKey = $bs->slot->from_time . '_' . $bs->slot->to_time;
                if (isset($seen[$timeKey])) continue;
                $seen[$timeKey] = true;
                $slots[] = [
                    'from_ts' => strtotime($bs->slot->from_time),
                    'to_ts' => strtotime($bs->slot->to_time),
                ];
            }
        }
        if (empty($slots)) return [];
        usort($slots, fn($a, $b) => $a['from_ts'] <=> $b['from_ts']);
        $ranges = [];
        $currentStart = $slots[0]['from_ts'];
        $currentEnd = $slots[0]['to_ts'];
        for ($i = 1; $i < count($slots); $i++) {
            if ($slots[$i]['from_ts'] === $currentEnd) {
                $currentEnd = $slots[$i]['to_ts'];
            } else {
                $ranges[] = date('h:i A', $currentStart) . ' – ' . date('h:i A', $currentEnd);
                $currentStart = $slots[$i]['from_ts'];
                $currentEnd = $slots[$i]['to_ts'];
            }
        }
        $ranges[] = date('h:i A', $currentStart) . ' – ' . date('h:i A', $currentEnd);
        return $ranges;
    }
}; ?>

<div class="w-full">
    <div class="w-full space-y-6">

        {{-- Header --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2.5">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    Cancellations & Refunds
                </h1>
                <p class="text-xs text-gray-500 mt-1">Cancel bookings, free slots instantly, and resolve refunds with flexible options.</p>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @php
            $user = auth()->user();
            $activeTurfId = session('active_turf_id');
            $manageableTurfIds = Turf::manageable($user)->pluck('id')->toArray();

            if (empty($manageableTurfIds)) {
                $activeBookings = collect();
                $cancellations = null;
                $pendingCount = 0;
                $resolvedCount = 0;
            } else {
                // Scope to active turf
                $scopedTurfIds = ($activeTurfId && in_array($activeTurfId, $manageableTurfIds))
                    ? [$activeTurfId] : $manageableTurfIds;

                // --- Active Bookings (for cancellation) ---
                $abQuery = Booking::with(['turf', 'user', 'bookingDates.bookingSlots.slot'])
                    ->whereIn('turf_id', $scopedTurfIds)
                    ->where('status', '!=', 'Cancelled')
                    ->whereHas('bookingDates', function ($q) {
                        $q->where('status', '!=', 'Cancelled');
                    });

                if (trim($this->bookingSearch) !== '') {
                    $s = trim($this->bookingSearch);
                    $abQuery->where(function ($q) use ($s) {
                        $q->where('booking_number', 'LIKE', "%{$s}%")
                          ->orWhereHas('user', function ($uq) use ($s) {
                              $uq->where('name', 'LIKE', "%{$s}%")
                                ->orWhere('mobile', 'LIKE', "%{$s}%");
                          });
                    });
                }

                if ($this->bookingDateFilter) {
                    $abQuery->whereHas('bookingDates', function ($q) {
                        $q->where('booking_date', $this->bookingDateFilter)
                          ->where('status', '!=', 'Cancelled');
                    });
                }

                $activeBookings = $abQuery->orderBy('created_at', 'desc')->paginate(10, ['*'], 'bookingsPage');

                // --- Cancellations Table ---
                $cQuery = BookingCancellation::with(['booking.turf', 'booking.user', 'bookingDate.bookingSlots.slot', 'cancelledByUser', 'resolvedByUser'])
                    ->whereHas('booking', function ($q) use ($scopedTurfIds) {
                        $q->whereIn('turf_id', $scopedTurfIds);
                    });

                if (trim($this->cancelSearch) !== '') {
                    $cs = trim($this->cancelSearch);
                    $cQuery->where(function ($q) use ($cs) {
                        $q->whereHas('booking', function ($bq) use ($cs) {
                            $bq->where('booking_number', 'LIKE', "%{$cs}%");
                        })->orWhereHas('booking.user', function ($uq) use ($cs) {
                            $uq->where('name', 'LIKE', "%{$cs}%")
                              ->orWhere('mobile', 'LIKE', "%{$cs}%");
                        });
                    });
                }

                if ($this->cancelStatusFilter !== 'all') {
                    $cQuery->where('refund_status', $this->cancelStatusFilter);
                }

                $cancellations = $cQuery->orderByDesc('created_at')->paginate(15, ['*'], 'cancelsPage');

                $pendingCount = BookingCancellation::whereHas('booking', function ($q) use ($scopedTurfIds) {
                    $q->whereIn('turf_id', $scopedTurfIds);
                })->whereIn('refund_status', ['Pending Resolution', 'Pending'])->count();

                $resolvedCount = BookingCancellation::whereHas('booking', function ($q) use ($scopedTurfIds) {
                    $q->whereIn('turf_id', $scopedTurfIds);
                })->whereNotIn('refund_status', ['Pending Resolution', 'Pending'])->count();
            }
        @endphp

        @if (empty($manageableTurfIds))
            <div class="text-center py-16">
                <svg class="mx-auto w-16 h-16 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                <p class="mt-4 text-gray-500 text-sm">No turfs assigned. Contact admin.</p>
            </div>
        @else
            {{-- Tab Switcher --}}
            <div class="flex border-b border-gray-200">
                <button wire:click="switchTab('active_bookings')"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors {{ $this->activeTab === 'active_bookings' ? 'border-indigo-600 text-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Active Bookings
                </button>
                <button wire:click="switchTab('cancellations')"
                    class="px-5 py-3 text-sm font-medium border-b-2 transition-colors {{ $this->activeTab === 'cancellations' ? 'border-red-500 text-red-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                    <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                    Cancellations & Refunds
                    @if ($pendingCount > 0)
                        <span class="ml-1.5 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-700">{{ $pendingCount }}</span>
                    @endif
                </button>
            </div>

            {{-- ============================================================ --}}
            {{-- TAB 1: Active Bookings for Cancellation                       --}}
            {{-- ============================================================ --}}
            @if ($this->activeTab === 'active_bookings')
                <div class="space-y-4">
                    {{-- Filters --}}
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input wire:model.live.debounce.300ms="bookingSearch" type="text" placeholder="Search by booking #, customer name or mobile..." class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-gray-50/50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 focus:bg-white transition">
                        </div>
                        <input wire:model.live="bookingDateFilter" type="date" class="px-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-gray-50/50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                    </div>

                    {{-- Bookings List --}}
                    @if ($activeBookings->isEmpty())
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-100">
                            <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="mt-3 text-gray-500 text-sm">No active bookings found.</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($activeBookings as $booking)
                                @php
                                    $activeDates = $booking->bookingDates->where('status', '!=', 'Cancelled');
                                @endphp
                                <div class="bg-white rounded-2xl border border-gray-100 shadow-xs hover:shadow-sm transition-shadow">
                                    <div class="p-4 sm:p-5">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                            {{-- Booking Info --}}
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-sm text-gray-900">{{ $booking->booking_number ?? '#'.$booking->id }}</span>
                                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $booking->status === 'Confirmed' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $booking->status }}</span>
                                                    <span class="text-xs text-gray-400">{{ $booking->turf->name ?? '' }}</span>
                                                </div>
                                                <div class="mt-1.5 flex items-center gap-3 text-xs text-gray-600">
                                                    <span class="font-medium">{{ $booking->user->name ?? 'N/A' }}</span>
                                                    <span>{{ $booking->user->mobile ?? '' }}</span>
                                                </div>
                                                {{-- Dates & Slots --}}
                                                <div class="mt-3 space-y-1.5">
                                                    @foreach ($activeDates as $bDate)
                                                        @php $slotRanges = $this->formatConsecutiveSlots($bDate->bookingSlots); @endphp
                                                        <div class="flex items-center gap-2 text-xs">
                                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-medium">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                                {{ \Carbon\Carbon::parse($bDate->booking_date)->format('d M Y') }}
                                                            </span>
                                                            @foreach ($slotRanges as $range)
                                                                <span class="text-gray-500">{{ $range }}</span>
                                                            @endforeach
                                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDate->amount, 0) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                            {{-- Action --}}
                                            <div class="shrink-0">
                                                <button wire:click="openCancelModal({{ $booking->id }})" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-xl bg-red-50 text-red-700 hover:bg-red-100 border border-red-200 transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                    Cancel Dates
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $activeBookings->links() }}</div>
                    @endif
                </div>
            @endif

            {{-- ============================================================ --}}
            {{-- TAB 2: Cancellations & Refund Resolution Table                --}}
            {{-- ============================================================ --}}
            @if ($this->activeTab === 'cancellations')
                <div class="space-y-4">
                    {{-- Stats Cards --}}
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-xs">
                            <p class="text-xs text-gray-500 font-medium">Pending Resolution</p>
                            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $pendingCount }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-xs">
                            <p class="text-xs text-gray-500 font-medium">Resolved</p>
                            <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $resolvedCount }}</p>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-4 shadow-xs">
                            <p class="text-xs text-gray-500 font-medium">Total Cancellations</p>
                            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $pendingCount + $resolvedCount }}</p>
                        </div>
                    </div>

                    {{-- Filters --}}
                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-1">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input wire:model.live.debounce.300ms="cancelSearch" type="text" placeholder="Search by booking #, customer name or mobile..." class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-gray-50/50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 focus:bg-white transition">
                        </div>
                        <select wire:model.live="cancelStatusFilter" class="px-4 py-2.5 text-sm rounded-xl border border-gray-200 bg-gray-50/50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 transition">
                            <option value="all">All Statuses</option>
                            <option value="Pending Resolution">⏳ Pending Resolution</option>
                            <option value="Refunded">✅ Refunded</option>
                            <option value="Compensated">💰 Compensated</option>
                            <option value="Forfeited">🚫 Forfeited</option>
                            <option value="Cash / Offline Refund">💵 Offline Refund</option>
                        </select>
                    </div>

                    {{-- Cancellation Cards --}}
                    @if ($cancellations && $cancellations->isEmpty())
                        <div class="text-center py-12 bg-white rounded-2xl border border-gray-100">
                            <svg class="mx-auto w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="mt-3 text-gray-500 text-sm">No cancellations found.</p>
                        </div>
                    @elseif ($cancellations)
                        <div class="space-y-3">
                            @foreach ($cancellations as $c)
                                @php
                                    $isPending = in_array($c->refund_status, ['Pending Resolution', 'Pending']);
                                    $statusColor = match ($c->refund_status) {
                                        'Pending Resolution', 'Pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'Refunded' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                        'Compensated' => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'Forfeited' => 'bg-gray-100 text-gray-600 border-gray-200',
                                        'Cash / Offline Refund' => 'bg-violet-50 text-violet-700 border-violet-200',
                                        default => 'bg-gray-100 text-gray-600 border-gray-200',
                                    };
                                    $bDate = $c->bookingDate;
                                    $slotRanges = $bDate ? $this->formatConsecutiveSlots($bDate->bookingSlots) : [];
                                @endphp
                                <div class="bg-white rounded-2xl border {{ $isPending ? 'border-amber-200 ring-1 ring-amber-100' : 'border-gray-100' }} shadow-xs">
                                    <div class="p-4 sm:p-5">
                                        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                                            {{-- Info --}}
                                            <div class="flex-1 min-w-0 space-y-2">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="font-semibold text-sm text-gray-900">{{ $c->booking->booking_number ?? '#'.$c->booking_id }}</span>
                                                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold border {{ $statusColor }}">{{ $c->refund_status }}</span>
                                                    @if ($c->resolution_mode)
                                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                                                            {{ str_replace('_', ' ', ucfirst($c->resolution_mode)) }}
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="flex items-center gap-3 text-xs text-gray-600">
                                                    <span class="font-medium">{{ $c->booking->user->name ?? 'N/A' }}</span>
                                                    <span>{{ $c->booking->user->mobile ?? '' }}</span>
                                                    <span class="text-gray-400">{{ $c->booking->turf->name ?? '' }}</span>
                                                </div>
                                                @if ($bDate)
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg bg-red-50 text-red-600 font-medium">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                            {{ \Carbon\Carbon::parse($bDate->booking_date)->format('d M Y') }}
                                                        </span>
                                                        @foreach ($slotRanges as $range)
                                                            <span class="text-gray-500">{{ $range }}</span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                                <div class="flex items-center gap-4 text-xs text-gray-500">
                                                    <span>Cancelled by: <strong>{{ $c->cancelledByUser->name ?? 'N/A' }}</strong> ({{ ucfirst($c->canceller_role) }})</span>
                                                    <span>{{ $c->created_at?->format('d M Y, h:i A') }}</span>
                                                </div>
                                                @if ($c->reason)
                                                    <p class="text-xs text-gray-500 italic">Reason: {{ $c->reason }}</p>
                                                @endif
                                            </div>

                                            {{-- Financial Summary & Action --}}
                                            <div class="shrink-0 sm:text-right space-y-2">
                                                <div class="text-xs text-gray-500">Gross Paid</div>
                                                <div class="text-lg font-bold text-gray-900">₹{{ number_format($c->gross_cancelled_amount, 2) }}</div>

                                                @if (!$isPending)
                                                    <div class="text-xs text-gray-500">Refund Issued</div>
                                                    <div class="text-base font-bold {{ $c->refund_amount > 0 ? 'text-emerald-600' : 'text-gray-400' }}">₹{{ number_format($c->refund_amount, 2) }}</div>
                                                    @if ($c->disbursement_channel === 'offline' && $c->offline_reference)
                                                        <p class="text-[10px] text-gray-400 mt-0.5">Ref: {{ $c->offline_reference }}</p>
                                                    @endif
                                                    @if ($c->resolvedByUser)
                                                        <p class="text-[10px] text-gray-400">Resolved by {{ $c->resolvedByUser->name }} on {{ $c->resolved_at?->format('d M Y') }}</p>
                                                    @endif
                                                @else
                                                    <div class="text-xs text-gray-500">Est. Refund (Policy)</div>
                                                    <div class="text-base font-semibold text-amber-600">₹{{ number_format($c->refund_amount, 2) }}</div>
                                                    <button wire:click="openResolveModal({{ $c->id }})" class="mt-2 inline-flex items-center gap-1.5 px-4 py-2.5 text-xs font-bold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm shadow-indigo-200 transition">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        Resolve Refund
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-4">{{ $cancellations->links() }}</div>
                    @endif
                </div>
            @endif

        @endif {{-- end manageableTurfIds check --}}

    </div>

    {{-- ============================================================ --}}
    {{-- MODAL: Cancel Booking Dates                                   --}}
    {{-- ============================================================ --}}
    @if ($showCancelModal && $cancelBookingId)
        @php
            $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
            $cancelBooking = Booking::whereIn('turf_id', $manageableTurfIds)->with(['turf', 'user', 'bookingDates.bookingSlots.slot'])->find($cancelBookingId);
        @endphp
        @if ($cancelBooking)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-init="document.body.classList.add('overflow-hidden')" x-on:remove.window="document.body.classList.remove('overflow-hidden')">
                <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeCancelModal"></div>
                <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <div class="p-6 space-y-5">
                        {{-- Header --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                    Cancel Booking Dates
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $cancelBooking->booking_number }} · {{ $cancelBooking->user->name ?? 'N/A' }}</p>
                            </div>
                            <button wire:click="closeCancelModal" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Info Banner --}}
                        <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-800">
                            <strong>Phase 1:</strong> Selected dates will be cancelled immediately and slots will be freed. Refund will require separate resolution.
                        </div>

                        {{-- Date Selection --}}
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-700">Select dates to cancel</label>
                            @foreach ($cancelBooking->bookingDates->where('status', '!=', 'Cancelled') as $bDate)
                                @php $slotRanges = $this->formatConsecutiveSlots($bDate->bookingSlots); @endphp
                                <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 hover:border-indigo-300 hover:bg-indigo-50/30 cursor-pointer transition">
                                    <input type="checkbox" wire:model="selectedDateIds" value="{{ $bDate->id }}" class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 text-sm">
                                            <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($bDate->booking_date)->format('D, d M Y') }}</span>
                                            <span class="text-gray-500 text-xs">{{ implode(', ', $slotRanges) }}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">₹{{ number_format($bDate->amount, 0) }} · {{ $bDate->bookingSlots->count() }} slot(s)</div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        {{-- Reason --}}
                        <div>
                            <label class="text-sm font-semibold text-gray-700">Reason for cancellation</label>
                            <textarea wire:model="cancelReason" rows="2" placeholder="e.g. Customer requested, Rain, Ground maintenance..." class="mt-1.5 w-full text-sm rounded-xl border border-gray-200 bg-gray-50/50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 px-4 py-2.5 transition"></textarea>
                        </div>

                        {{-- Actions --}}
                        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                            <button wire:click="closeCancelModal" class="px-5 py-2.5 text-sm font-medium rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                            <button wire:click="submitCancellation" wire:loading.attr="disabled" class="px-5 py-2.5 text-sm font-bold rounded-xl bg-red-600 text-white hover:bg-red-700 shadow-sm shadow-red-200 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="submitCancellation">Cancel Selected Dates</span>
                                <span wire:loading wire:target="submitCancellation">Processing...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: Resolve Refund                                         --}}
    {{-- ============================================================ --}}
    @if ($showResolveModal && $resolveCancellationId)
        @php
            $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
            $resolveCancel = BookingCancellation::whereHas('booking', function ($q) use ($manageableTurfIds) {
                $q->whereIn('turf_id', $manageableTurfIds);
            })->with(['booking.user', 'bookingDate'])->find($resolveCancellationId);
        @endphp
        @if ($resolveCancel)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data x-init="document.body.classList.add('overflow-hidden')" x-on:remove.window="document.body.classList.remove('overflow-hidden')">
                <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" wire:click="closeResolveModal"></div>
                <div class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <div class="p-6 space-y-5">
                        {{-- Header --}}
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Resolve Refund
                                </h3>
                                <p class="text-xs text-gray-500 mt-0.5">{{ $resolveCancel->booking->booking_number ?? '' }} · {{ $resolveCancel->booking->user->name ?? 'N/A' }}</p>
                            </div>
                            <button wire:click="closeResolveModal" class="p-2 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-600 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        {{-- Gross Amount Display --}}
                        <div class="p-4 rounded-xl bg-gray-50 border border-gray-200 text-center">
                            <p class="text-xs text-gray-500">Gross Amount Paid by Customer</p>
                            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($resolveCancel->gross_cancelled_amount, 2) }}</p>
                            @if ($resolveCancel->bookingDate)
                                <p class="text-xs text-gray-500 mt-1">Date: {{ \Carbon\Carbon::parse($resolveCancel->bookingDate->booking_date)->format('d M Y') }}</p>
                            @endif
                        </div>

                        {{-- Resolution Mode --}}
                        <div class="space-y-2">
                            <label class="text-sm font-semibold text-gray-700">Refund Resolution</label>

                            {{-- Option 1: Full Compensation --}}
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $resolutionMode === 'full_compensation' ? 'border-blue-400 bg-blue-50/50 ring-1 ring-blue-200' : 'border-gray-200 hover:border-blue-300' }}">
                                <input type="radio" wire:model.live="resolutionMode" value="full_compensation" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <span class="text-sm font-semibold text-gray-900">Full Compensation (100%)</span>
                                    <p class="text-xs text-gray-500 mt-0.5">Customer receives full ₹{{ number_format($resolveCancel->gross_cancelled_amount, 2) }}. Turf absorbs platform and cancellation fees.</p>
                                    <p class="text-xs text-blue-600 font-medium mt-1">Refund: ₹{{ number_format($resolveCancel->gross_cancelled_amount, 2) }}</p>
                                </div>
                            </label>

                            {{-- Option 2: Standard Policy --}}
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $resolutionMode === 'standard_policy' ? 'border-emerald-400 bg-emerald-50/50 ring-1 ring-emerald-200' : 'border-gray-200 hover:border-emerald-300' }}">
                                <input type="radio" wire:model.live="resolutionMode" value="standard_policy" class="mt-0.5 text-emerald-600 focus:ring-emerald-500">
                                <div>
                                    <span class="text-sm font-semibold text-gray-900">Standard Policy Refund</span>
                                    <p class="text-xs text-gray-500 mt-0.5">Deducts non-refundable platform fee, SaaS cancellation fee %, and turf fee per slot.</p>
                                    <div class="mt-1 text-xs space-y-0.5">
                                        <p class="text-gray-600">Total Deductions: <strong class="text-red-600">₹{{ number_format($resolveCancel->total_cancellation_fee, 2) }}</strong></p>
                                        <p class="text-emerald-600 font-medium">Net Refund: ₹{{ number_format($resolveCancel->refund_amount, 2) }}</p>
                                    </div>
                                </div>
                            </label>

                            {{-- Option 3: Custom --}}
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $resolutionMode === 'custom' ? 'border-violet-400 bg-violet-50/50 ring-1 ring-violet-200' : 'border-gray-200 hover:border-violet-300' }}">
                                <input type="radio" wire:model.live="resolutionMode" value="custom" class="mt-0.5 text-violet-600 focus:ring-violet-500">
                                <div class="w-full">
                                    <span class="text-sm font-semibold text-gray-900">Custom Adjusted Amount</span>
                                    <p class="text-xs text-gray-500 mt-0.5">Enter a mutually agreed custom refund amount.</p>
                                    @if ($resolutionMode === 'custom')
                                        <div class="mt-2">
                                            <div class="relative">
                                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">₹</span>
                                                <input wire:model="customRefundAmount" type="number" min="0" max="{{ $resolveCancel->gross_cancelled_amount }}" step="0.01" placeholder="0.00" class="w-full pl-7 pr-4 py-2 text-sm rounded-lg border border-violet-300 focus:ring-2 focus:ring-violet-500/20 focus:border-violet-400">
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </label>

                            {{-- Option 4: No Refund --}}
                            <label class="flex items-start gap-3 p-3.5 rounded-xl border cursor-pointer transition {{ $resolutionMode === 'no_refund' ? 'border-gray-400 bg-gray-50/80 ring-1 ring-gray-300' : 'border-gray-200 hover:border-gray-300' }}">
                                <input type="radio" wire:model.live="resolutionMode" value="no_refund" class="mt-0.5 text-gray-600 focus:ring-gray-500">
                                <div>
                                    <span class="text-sm font-semibold text-gray-900">No Refund (Forfeited)</span>
                                    <p class="text-xs text-gray-500 mt-0.5">₹0.00 refund — e.g. customer no-show, policy forfeiture.</p>
                                </div>
                            </label>
                        </div>

                        {{-- Disbursement Channel --}}
                        @if ($resolutionMode !== 'no_refund')
                            <div class="space-y-2">
                                <label class="text-sm font-semibold text-gray-700">Disbursement Method</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition {{ $disbursementChannel === 'online_gateway' ? 'border-indigo-400 bg-indigo-50/50' : 'border-gray-200 hover:border-indigo-300' }}">
                                        <input type="radio" wire:model.live="disbursementChannel" value="online_gateway" class="text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-semibold text-gray-900">Payment Gateway</span>
                                            <p class="text-[10px] text-gray-500">Razorpay / UPI refund</p>
                                        </div>
                                    </label>
                                    <label class="flex items-center gap-2 p-3 rounded-xl border cursor-pointer transition {{ $disbursementChannel === 'offline' ? 'border-indigo-400 bg-indigo-50/50' : 'border-gray-200 hover:border-indigo-300' }}">
                                        <input type="radio" wire:model.live="disbursementChannel" value="offline" class="text-indigo-600 focus:ring-indigo-500">
                                        <div>
                                            <span class="text-xs font-semibold text-gray-900">Cash / Offline UPI</span>
                                            <p class="text-[10px] text-gray-500">Settled directly</p>
                                        </div>
                                    </label>
                                </div>

                                @if ($disbursementChannel === 'offline')
                                    <div class="mt-2">
                                        <input wire:model="offlineReference" type="text" placeholder="UPI transaction ID / Cash receipt note" class="w-full text-sm rounded-xl border border-gray-200 bg-gray-50/50 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-400 px-4 py-2.5 transition">
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Actions --}}
                        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100">
                            <button wire:click="closeResolveModal" class="px-5 py-2.5 text-sm font-medium rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition">Cancel</button>
                            <button wire:click="submitResolveRefund" wire:loading.attr="disabled" class="px-5 py-2.5 text-sm font-bold rounded-xl bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm shadow-indigo-200 transition disabled:opacity-50">
                                <span wire:loading.remove wire:target="submitResolveRefund">Confirm & Execute</span>
                                <span wire:loading wire:target="submitResolveRefund">Processing...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

</div>
