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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
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

                    // Collect slot summaries
                    $slotsSummary = [];
                    foreach ($b->bookingDates as $bDate) {
                        foreach ($bDate->bookingSlots as $bSlot) {
                            if ($bSlot->slot) {
                                $from = date('h:i A', strtotime($bSlot->slot->from_time));
                                $to = date('h:i A', strtotime($bSlot->slot->to_time));
                                $slotsSummary[] = "$from - $to";
                            }
                        }
                    }
                    $slotsSummary = array_unique($slotsSummary);
                    $slotTimeText = !empty($slotsSummary) ? implode(', ', array_slice($slotsSummary, 0, 2)) . (count($slotsSummary) > 2 ? ' +' . (count($slotsSummary) - 2) . ' more' : '') : 'N/A';

                    $turfPhoto = $b->turf?->photos->first()?->photo_url ?? null;
                @endphp

                <div class="bg-white rounded-3xl border border-gray-100 shadow-lg shadow-gray-100/60 overflow-hidden flex flex-col justify-between hover:shadow-xl hover:border-indigo-200 transition duration-200 group">
                    <div>
                        <!-- Card Header Banner -->
                        <div class="p-5 sm:p-6 bg-gradient-to-r from-gray-900 via-slate-800 to-indigo-950 text-white flex items-start justify-between gap-4 relative overflow-hidden">
                            <div class="absolute right-0 top-0 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>

                            <div class="relative z-10 space-y-1">
                                <span class="text-[10px] font-black uppercase tracking-widest text-indigo-300">
                                    Ref #BK-{{ str_pad($b->id, 5, '0', STR_PAD_LEFT) }}
                                </span>
                                <h3 class="text-lg font-black tracking-tight text-white group-hover:text-indigo-200 transition">
                                    {{ $b->turf?->name ?? 'Turf' }}
                                </h3>
                                <p class="text-xs text-gray-300 flex items-center gap-1 font-medium">
                                    <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>{{ $b->turf?->address ?? $b->turf?->location?->name ?? 'Location' }}</span>
                                </p>
                            </div>

                            <div class="relative z-10 flex flex-col items-end gap-1.5 shrink-0">
                                <!-- Booking Status Badge -->
                                @if ($b->status === 'Confirmed')
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 backdrop-blur-md">
                                        Confirmed
                                    </span>
                                @elseif ($b->status === 'Cancelled')
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-400/30 backdrop-blur-md">
                                        Cancelled
                                    </span>
                                @else
                                    <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-400/30 backdrop-blur-md">
                                        {{ $b->status }}
                                    </span>
                                @endif

                                <!-- Payment Status Badge -->
                                @if ($b->payment_status === 'Paid')
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-400 text-gray-950">
                                        Paid
                                    </span>
                                @elseif ($b->payment_status === 'Partially Paid')
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-amber-400 text-gray-950">
                                        Partial
                                    </span>
                                @else
                                    <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-400 text-gray-950">
                                        Unpaid
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Card Body Details -->
                        <div class="p-5 sm:p-6 space-y-4">
                            <div class="grid grid-cols-2 gap-3 text-xs">
                                <div class="bg-gray-50 p-3 rounded-2xl border border-gray-100">
                                    <span class="text-[10px] font-black uppercase text-gray-400 block mb-0.5">Booking Date</span>
                                    <span class="font-bold text-gray-900 block truncate">📅 {{ $formattedDateRange }}</span>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-2xl border border-gray-100">
                                    <span class="text-[10px] font-black uppercase text-gray-400 block mb-0.5">Time Slot</span>
                                    <span class="font-bold text-indigo-600 block truncate">⏰ {{ $slotTimeText }}</span>
                                </div>
                            </div>

                            <!-- Pricing Breakdown -->
                            <div class="flex items-center justify-between pt-2 border-t border-gray-100 text-xs">
                                <div>
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Total Amount</span>
                                    <span class="text-lg font-black text-gray-900">₹{{ number_format($totalAmount, 2) }}</span>
                                </div>

                                <div class="text-right">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Paid Amount</span>
                                    <span class="text-sm font-bold text-emerald-600">₹{{ number_format($paidSum, 2) }}</span>
                                    @if ($balance > 0 && $b->status !== 'Cancelled')
                                        <span class="text-[10px] font-bold text-rose-500 block">Bal: ₹{{ number_format($balance, 2) }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div class="p-4 sm:px-6 bg-gray-50/70 border-t border-gray-100 flex items-center justify-between gap-3">
                        <span class="text-[11px] text-gray-400 font-medium">Booked on {{ Carbon::parse($b->date_of_booking)->format('M d, Y') }}</span>
                        <button wire:click="viewDetails({{ $b->id }})" type="button"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold text-xs rounded-xl transition duration-150 shadow-md shadow-indigo-600/20 cursor-pointer">
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
                        <!-- Turf & Location -->
                        <div class="bg-indigo-50/60 p-4 rounded-2xl border border-indigo-100/70 space-y-1">
                            <h4 class="font-black text-indigo-950 uppercase text-[10px] tracking-wider">Ground Address</h4>
                            <p class="font-bold text-indigo-900 text-sm">{{ $modalBooking->turf?->name }}</p>
                            <p class="text-indigo-700 font-medium">{{ $modalBooking->turf?->address ?? 'N/A' }}</p>
                        </div>

                        <!-- Date & Slot Breakdown -->
                        <div class="space-y-3">
                            <h4 class="font-black text-gray-900 uppercase text-[10px] tracking-wider">Scheduled Slots</h4>
                            <div class="space-y-2">
                                @foreach ($modalBooking->bookingDates as $bDate)
                                    <div class="bg-gray-50 p-3.5 rounded-2xl border border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <div>
                                            <span class="font-bold text-gray-900 text-xs block">📅 {{ Carbon::parse($bDate->booking_date)->format('D, M d, Y') }}</span>
                                            <div class="flex items-center gap-1.5 flex-wrap mt-1">
                                                @foreach ($bDate->bookingSlots as $bSlot)
                                                    @if ($bSlot->slot)
                                                        <span class="px-2 py-0.5 rounded-lg bg-indigo-100 text-indigo-800 text-[10px] font-bold">
                                                            {{ date('h:i A', strtotime($bSlot->slot->from_time)) }} - {{ date('h:i A', strtotime($bSlot->slot->to_time)) }}
                                                        </span>
                                                    @endif
                                                @endforeach
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
