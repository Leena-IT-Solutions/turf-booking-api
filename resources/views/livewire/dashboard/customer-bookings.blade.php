<?php

use App\Models\Booking;
use App\Models\BookingDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all'; // 'all', 'Confirmed', 'Pending', 'Cancelled'
    public string $timelineFilter = 'all'; // 'all', 'upcoming', 'past', 'today'
    public string $sortBy = 'newest'; // 'newest', 'oldest', 'date_asc', 'date_desc'
    public int $perPage = 10;

    public ?int $selectedBookingId = null;
    public bool $showDetailModal = false;

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingTimelineFilter() { $this->resetPage(); }
    public function updatingSortBy() { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->timelineFilter = 'all';
        $this->sortBy = 'newest';
        $this->resetPage();
    }

    public function viewDetails(int $bookingId): void
    {
        $this->selectedBookingId = $bookingId;
        $this->showDetailModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
    }

    public function formatConsecutiveSlots($bookingSlots): array
    {
        $slots = [];
        $seen = [];
        foreach ($bookingSlots as $bs) {
            if ($bs->slot && $bs->slot->from_time && $bs->slot->to_time) {
                $timeKey = $bs->slot->from_time . '_' . $bs->slot->to_time;
                if (isset($seen[$timeKey])) {
                    continue;
                }
                $seen[$timeKey] = true;

                $slots[] = [
                    'from' => substr($bs->slot->from_time, 0, 5),
                    'to' => substr($bs->slot->to_time, 0, 5),
                    'from_ts' => strtotime($bs->slot->from_time),
                    'to_ts' => strtotime($bs->slot->to_time),
                ];
            }
        }

        if (empty($slots)) {
            return [];
        }

        usort($slots, fn($a, $b) => $a['from_ts'] <=> $b['from_ts']);

        $ranges = [];
        $currentStart = $slots[0]['from_ts'];
        $currentEnd = $slots[0]['to_ts'];
        $count = 1;

        for ($i = 1; $i < count($slots); $i++) {
            if ($slots[$i]['from_ts'] === $currentEnd) {
                // Continuous / consecutive slot
                $currentEnd = $slots[$i]['to_ts'];
                $count++;
            } else {
                $ranges[] = [
                    'from' => date('h:i A', $currentStart),
                    'to' => date('h:i A', $currentEnd),
                    'slots_count' => $count,
                ];
                $currentStart = $slots[$i]['from_ts'];
                $currentEnd = $slots[$i]['to_ts'];
                $count = 1;
            }
        }

        $ranges[] = [
            'from' => date('h:i A', $currentStart),
            'to' => date('h:i A', $currentEnd),
            'slots_count' => $count,
        ];

        return $ranges;
    }
}; ?>

<div class="space-y-6">
    @php
        $userId = auth()->id() ?? 0;
        $todayStr = Carbon::today('Asia/Kolkata')->toDateString();

        $baseQuery = Booking::where('user_id', $userId)
            ->with([
                'turf.location',
                'turf.photos',
                'bookingDates' => function ($bdq) {
                    $bdq->orderBy('booking_date', 'asc')->with('bookingSlots.slot', 'payments');
                },
                'payments'
            ]);

        $totalUserBookingsCount = (clone $baseQuery)->count();

        $query = (clone $baseQuery);

        // Search filter
        if (trim($this->search) !== '') {
            $searchTerm = trim($this->search);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('id', 'LIKE', "%{$searchTerm}%")
                  ->orWhereHas('turf', function ($tq) use ($searchTerm) {
                      $tq->where('name', 'LIKE', "%{$searchTerm}%")
                         ->orWhere('address', 'LIKE', "%{$searchTerm}%")
                         ->orWhereHas('location', function ($lq) use ($searchTerm) {
                             $lq->where('name', 'LIKE', "%{$searchTerm}%");
                         });
                  });
            });
        }

        // Status Filter
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        // Timeline Filter
        if ($this->timelineFilter === 'today') {
            $query->whereHas('bookingDates', function ($bdq) use ($todayStr) {
                $bdq->whereDate('booking_date', $todayStr);
            });
        } elseif ($this->timelineFilter === 'upcoming') {
            $query->whereHas('bookingDates', function ($bdq) use ($todayStr) {
                $bdq->whereDate('booking_date', '>=', $todayStr);
            });
        } elseif ($this->timelineFilter === 'past') {
            $query->whereHas('bookingDates', function ($bdq) use ($todayStr) {
                $bdq->whereDate('booking_date', '<', $todayStr);
            });
        }

        // Sorting
        if ($this->sortBy === 'oldest') {
            $query->orderBy('created_at', 'asc');
        } elseif ($this->sortBy === 'date_asc') {
            $query->orderBy(DB::raw('(SELECT MIN(booking_date) FROM booking_dates WHERE booking_id = bookings.id)'), 'asc');
        } elseif ($this->sortBy === 'date_desc') {
            $query->orderBy(DB::raw('(SELECT MIN(booking_date) FROM booking_dates WHERE booking_id = bookings.id)'), 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $bookings = $query->paginate($this->perPage);
    @endphp

    <!-- Dashboard Section Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
        <div>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-bold shadow-inner">
                    🎫
                </div>
                <div>
                    <h2 class="text-xl sm:text-2xl font-black text-gray-900 tracking-tight">
                        {{ __('My Bookings') }}
                    </h2>
                    <p class="text-xs text-gray-500 font-medium">
                        {{ __('View and track all your turf slot reservations, payments, and schedules.') }}
                    </p>
                </div>
            </div>
        </div>

        @if ($totalUserBookingsCount > 0)
            <div class="flex items-center gap-2 self-start sm:self-center bg-gray-50 p-1.5 rounded-2xl border border-gray-100">
                <span class="text-xs font-bold text-gray-500 px-3 py-1">Total: <strong class="text-indigo-600 font-black">{{ $totalUserBookingsCount }}</strong></span>
            </div>
        @endif
    </div>

    <!-- Filter & Search Controls Bar -->
    @if ($totalUserBookingsCount > 0 || $search !== '' || $statusFilter !== 'all' || $timelineFilter !== 'all')
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-gray-100 shadow-sm space-y-4">
            
            <!-- Quick Timeline Filter Pills & Reset -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                    <span class="text-xs font-black uppercase tracking-wider text-gray-400 me-1">Filter Timeline:</span>
                    <button wire:click="$set('timelineFilter', 'all')" type="button" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition duration-150 cursor-pointer {{ $timelineFilter === 'all' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        All Time
                    </button>
                    <button wire:click="$set('timelineFilter', 'upcoming')" type="button" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition duration-150 cursor-pointer {{ $timelineFilter === 'upcoming' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Upcoming
                    </button>
                    <button wire:click="$set('timelineFilter', 'today')" type="button" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition duration-150 cursor-pointer {{ $timelineFilter === 'today' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Today
                    </button>
                    <button wire:click="$set('timelineFilter', 'past')" type="button" 
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition duration-150 cursor-pointer {{ $timelineFilter === 'past' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">
                        Past / Completed
                    </button>
                </div>

                @if ($search !== '' || $statusFilter !== 'all' || $timelineFilter !== 'all' || $sortBy !== 'newest')
                    <button wire:click="clearFilters" type="button" 
                        class="text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline flex items-center gap-1 shrink-0 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        <span>Reset Filters</span>
                    </button>
                @endif
            </div>

            <!-- Detailed Search & Filters Row -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <!-- Search Input -->
                <div class="lg:col-span-2 relative">
                    <label class="block text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Search Booking</label>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by Turf Name, City, or ID..." 
                            class="w-full pl-9 pr-4 py-2.5 text-xs font-semibold rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 transition shadow-xs">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Booking Status</label>
                    <select wire:model.live="statusFilter" class="w-full py-2.5 px-3 text-xs font-semibold rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 transition bg-white shadow-xs">
                        <option value="all">All Statuses</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Pending">Pending</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-[11px] font-black uppercase tracking-wider text-gray-400 mb-1">Sort By</label>
                    <select wire:model.live="sortBy" class="w-full py-2.5 px-3 text-xs font-semibold rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500 transition bg-white shadow-xs">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="date_asc">Session Date (Earliest)</option>
                        <option value="date_desc">Session Date (Latest)</option>
                    </select>
                </div>
            </div>
        </div>
    @endif

    <!-- Customer Bookings Cards List -->
    @if ($bookings->isNotEmpty())
        <div class="space-y-4 sm:space-y-6 w-full">
            @foreach ($bookings as $b)
                @php
                    $activeDates = $b->bookingDates->where('status', '!=', 'Cancelled');
                    $totalAmount = (float) $b->bookingDates->sum('amount');
                    $paidSum = (float) $b->payments->where('status', 'Success')->sum('amount');
                    $balance = max(0.00, $totalAmount - $paidSum);

                    $dateList = $b->bookingDates->pluck('booking_date')->toArray();
                    $firstDate = $dateList[0] ?? null;
                    $lastDate = end($dateList) ?? null;

                    $formattedDateRange = 'N/A';
                    if ($firstDate) {
                        if (count($dateList) > 1) {
                            $formattedDateRange = Carbon::parse($firstDate)->format('M d, Y') . ' - ' . Carbon::parse($lastDate)->format('M d, Y') . ' (' . count($dateList) . ' days)';
                        } else {
                            $formattedDateRange = Carbon::parse($firstDate)->format('D, M d, Y');
                        }
                    }

                    // Collect distinct consecutive session timing ranges across booking dates
                    $dailyRanges = [];
                    foreach ($b->bookingDates as $bDate) {
                        $ranges = $this->formatConsecutiveSlots($bDate->bookingSlots);
                        foreach ($ranges as $r) {
                            $rKey = $r['from'] . '-' . $r['to'];
                            if (!isset($dailyRanges[$rKey])) {
                                $dailyRanges[$rKey] = $r;
                            }
                        }
                    }
                    $consecutiveRanges = array_values($dailyRanges);
                    $timingParts = [];
                    foreach ($consecutiveRanges as $cr) {
                        $timingParts[] = $cr['from'] . ' - ' . $cr['to'];
                    }
                    $timingText = !empty($timingParts) ? implode(', ', $timingParts) : 'N/A';

                    $turfPhoto = $b->turf?->photos->first()?->photo_url ?? null;
                @endphp

                <div class="w-full bg-white rounded-3xl border border-emerald-100/80 shadow-lg shadow-emerald-950/5 overflow-hidden flex flex-col justify-between hover:shadow-xl hover:border-emerald-300 transition duration-200 group">
                    <div>
                        <!-- Card Header Banner (Eloquent Emerald & Teal Sports Turf Theme) -->
                        <div class="p-5 sm:p-6 bg-gradient-to-r from-emerald-600 via-teal-600 to-cyan-700 text-white flex flex-col sm:flex-row sm:items-center justify-between gap-4 relative overflow-hidden">
                            <!-- Ambient Glow Accents -->
                            <div class="absolute -right-8 -top-8 w-48 h-48 bg-emerald-400/20 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="absolute right-36 -bottom-10 w-36 h-36 bg-cyan-300/15 rounded-full blur-xl pointer-events-none"></div>

                            <div class="relative z-10 space-y-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-mono font-black uppercase tracking-wider text-white bg-white/15 px-2.5 py-0.5 rounded-lg border border-white/25 backdrop-blur-md shadow-2xs">
                                        Ref #BK-{{ str_pad($b->id, 5, '0', STR_PAD_LEFT) }}
                                    </span>
                                </div>
                                <h3 class="text-lg sm:text-xl font-black tracking-tight text-white group-hover:text-emerald-100 transition">
                                    {{ $b->turf?->name ?? 'Turf' }}
                                </h3>
                                <p class="text-xs text-emerald-100 flex items-center gap-1.5 font-medium">
                                    <svg class="w-3.5 h-3.5 text-emerald-200 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>{{ $b->turf?->address ?? $b->turf?->location?->name ?? 'Location' }}</span>
                                </p>
                            </div>

                            <div class="relative z-10 flex flex-wrap items-center gap-2 shrink-0">
                                <!-- Booking Status Badge -->
                                @if ($b->status === 'Confirmed')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white/20 text-white border border-white/30 backdrop-blur-md shadow-2xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
                                        Confirmed
                                    </span>
                                @elseif ($b->status === 'Cancelled')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-500/80 text-white border border-rose-300/40 backdrop-blur-md shadow-2xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-white"></span>
                                        Cancelled
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-amber-400/80 text-amber-950 border border-amber-300/50 backdrop-blur-md shadow-2xs">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-950"></span>
                                        {{ $b->status }}
                                    </span>
                                @endif

                                <!-- Payment Status Badge -->
                                @if ($b->payment_status === 'Paid')
                                    <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-white text-emerald-950 shadow-sm">
                                        <svg class="w-3 h-3 text-emerald-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        Paid
                                    </span>
                                @elseif ($b->payment_status === 'Partially Paid')
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-amber-300 text-amber-950 shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-950 animate-pulse"></span>
                                        Partial
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider bg-rose-400 text-rose-950 shadow-sm">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-950"></span>
                                        Unpaid
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Card Body Details -->
                        <div class="p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 text-xs">
                                <div class="bg-gray-50/80 p-3.5 sm:p-4 rounded-2xl border border-gray-100 flex flex-col justify-center">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block mb-1">Booking Date</span>
                                    <span class="font-bold text-gray-900 block truncate text-xs sm:text-sm">📅 {{ $formattedDateRange }}</span>
                                </div>
                                <div class="bg-gray-50/80 p-3.5 sm:p-4 rounded-2xl border border-gray-100 flex flex-col justify-center">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block mb-1">Timing</span>
                                    <span class="font-bold text-teal-700 block truncate text-xs sm:text-sm" title="{{ $timingText }}">⏰ {{ $timingText }}</span>
                                </div>
                                <div class="bg-gray-50/80 p-3.5 sm:p-4 rounded-2xl border border-gray-100 flex flex-col justify-center">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block mb-1">Total Amount</span>
                                    <span class="text-base sm:text-lg font-black text-gray-900 truncate">₹{{ number_format($totalAmount, 2) }}</span>
                                </div>
                                <div class="bg-gray-50/80 p-3.5 sm:p-4 rounded-2xl border border-gray-100 flex flex-col justify-center">
                                    <div class="flex items-center justify-between gap-1">
                                        <div>
                                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block mb-1">Paid Amount</span>
                                            <span class="text-sm sm:text-base font-bold text-emerald-600 truncate">₹{{ number_format($paidSum, 2) }}</span>
                                        </div>
                                        @if ($balance > 0 && $b->status !== 'Cancelled')
                                            <div class="text-right">
                                                <span class="text-[10px] font-black uppercase tracking-wider text-rose-400 block mb-1">Balance</span>
                                                <span class="text-xs sm:text-sm font-black text-rose-600 truncate">₹{{ number_format($balance, 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="p-4 sm:px-6 bg-gray-50/70 border-t border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <div class="flex items-center gap-2 text-[11px] text-gray-500 font-medium self-start sm:self-center">
                            <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Booked on {{ Carbon::parse($b->date_of_booking)->format('M d, Y') }}</span>
                        </div>
                        <button wire:click="viewDetails({{ $b->id }})" type="button"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 active:scale-95 text-white font-bold text-xs rounded-xl transition duration-150 shadow-md shadow-emerald-600/20 cursor-pointer">
                            <span>View Ticket</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $bookings->links() }}
        </div>
    @else
        <!-- EMPTY STATE VIEW -->
        <div class="bg-white rounded-3xl p-8 sm:p-12 text-center border border-gray-100 shadow-xl shadow-gray-100/50 max-w-lg mx-auto my-6">
            <div class="w-20 h-20 mx-auto rounded-3xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-4xl mb-6 shadow-inner">
                ⚽
            </div>
            <h3 class="text-2xl font-black text-gray-900 tracking-tight mb-2">
                You dont have booking yet!
            </h3>
            <p class="text-xs sm:text-sm text-gray-500 font-medium max-w-sm mx-auto leading-relaxed mb-6">
                @if ($totalUserBookingsCount > 0)
                    No bookings found matching your search or filters. Try resetting your active filters to see all bookings.
                @else
                    You haven't made any turf reservations yet. Find top sports grounds and book your slots now!
                @endif
            </p>
            @if ($totalUserBookingsCount > 0)
                <button wire:click="clearFilters" type="button" 
                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold text-xs uppercase tracking-wider rounded-2xl transition duration-150 shadow-lg shadow-indigo-600/20 cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Reset All Filters</span>
                </button>
            @endif
        </div>
    @endif

    <!-- DETAIL DRAWER MODAL -->
    @if ($showDetailModal && $selectedBookingId)
        @php
            $modalBooking = Booking::with([
                'turf.location',
                'bookingDates.bookingSlots.slot',
                'payments'
            ])->find($selectedBookingId);
        @endphp

        @if ($modalBooking)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div class="relative bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-gray-100 p-6 sm:p-8 space-y-6">
                    <!-- Modal Header -->
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-4">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600">
                                Booking Details
                            </span>
                            <h3 class="text-xl font-black text-gray-900 tracking-tight">
                                {{ $modalBooking->turf?->name }}
                            </h3>
                            <p class="text-xs text-gray-500 font-medium">
                                Reference Code: <strong class="text-gray-900 font-bold">#BK-{{ str_pad($modalBooking->id, 5, '0', STR_PAD_LEFT) }}</strong>
                            </p>
                        </div>
                        <button wire:click="closeDetails" type="button" class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 flex items-center justify-center transition cursor-pointer">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="space-y-5 text-xs text-gray-700">
                        <!-- Venue & Ground Address -->
                        <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1.5">
                            <h4 class="font-black text-gray-400 uppercase text-[10px] tracking-wider">Venue & Location</h4>
                            @if ($modalBooking->turf?->location?->name)
                                <p class="font-bold text-gray-900 text-sm flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <span>{{ $modalBooking->turf->location->name }}</span>
                                </p>
                            @endif
                            @php
                                $venueAddress = $modalBooking->turf?->location?->address ?? $modalBooking->turf?->address;
                            @endphp
                            @if ($venueAddress)
                                <p class="text-xs text-gray-600 flex items-center gap-1.5 font-medium">
                                    <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>{{ $venueAddress }}</span>
                                </p>
                            @elseif (!$modalBooking->turf?->location?->name)
                                <p class="text-xs text-gray-500">{{ $modalBooking->turf?->name }}</p>
                            @endif
                        </div>

                        <!-- Date & Timing Breakdown -->
                        <div class="space-y-3">
                            <h4 class="font-black text-gray-900 uppercase text-[10px] tracking-wider">Scheduled Timing</h4>
                            <div class="space-y-2">
                                @foreach ($modalBooking->bookingDates as $bDate)
                                    <div class="bg-gray-50 p-3.5 rounded-2xl border border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                            <span class="font-bold text-gray-900 text-xs block">📅 {{ Carbon::parse($bDate->booking_date)->format('D, M d, Y') }}</span>
                                            <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                                @php
                                                    $modalRanges = $this->formatConsecutiveSlots($bDate->bookingSlots);
                                                @endphp
                                                @if (!empty($modalRanges))
                                                    @foreach ($modalRanges as $mRange)
                                                        <span class="px-2.5 py-1 rounded-lg bg-indigo-100 text-indigo-800 text-[11px] font-bold inline-flex items-center gap-1">
                                                            <span>⏰ {{ $mRange['from'] }} - {{ $mRange['to'] }}</span>
                                                            @if ($mRange['slots_count'] > 1)
                                                                <span class="text-[9px] font-semibold text-indigo-600">({{ $mRange['slots_count'] }} slots)</span>
                                                            @endif
                                                        </span>
                                                    @endforeach
                                                @else
                                                    <span class="text-xs text-gray-400 italic">No timings scheduled</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="font-black text-gray-900 block">₹{{ number_format($bDate->amount, 2) }}</span>
                                            <span class="text-[10px] font-bold {{ $bDate->status === 'Cancelled' ? 'text-rose-600' : 'text-emerald-600' }}">{{ $bDate->status }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Payment & Financial Summary -->
                        @php
                            $modalTotal = (float) $modalBooking->bookingDates->sum('amount');
                            $modalPaid = (float) $modalBooking->payments->where('status', 'Success')->sum('amount');
                            $modalBalance = max(0.00, $modalTotal - $modalPaid);
                        @endphp
                        <div class="bg-gray-900 text-white p-5 rounded-2xl space-y-3 shadow-md">
                            <h4 class="font-black text-indigo-300 uppercase text-[10px] tracking-wider">Payment Summary</h4>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div>
                                    <span class="text-[9px] uppercase font-bold text-gray-400 block">Total</span>
                                    <span class="text-base font-black">₹{{ number_format($modalTotal, 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-[9px] uppercase font-bold text-emerald-400 block">Paid</span>
                                    <span class="text-base font-black text-emerald-400">₹{{ number_format($modalPaid, 2) }}</span>
                                </div>
                                <div>
                                    <span class="text-[9px] uppercase font-bold text-rose-400 block">Balance</span>
                                    <span class="text-base font-black text-rose-400">₹{{ number_format($modalBalance, 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="pt-3 border-t border-gray-100 flex justify-end">
                        <button wire:click="closeDetails" type="button" class="px-6 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-xl transition cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
