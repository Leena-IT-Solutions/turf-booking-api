<?php

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\Turf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Reactive Date & Filter State
    public string $selectedDate = '';
    public string $statusFilter = 'upcoming'; // 'all', 'upcoming', 'past', 'cancelled'
    public string $search = '';

    // Booking Details Modal
    public ?int $selectedBookingId = null;
    public bool $showDetailModal = false;

    // Record Payment Modal
    public ?int $paymentBookingDateId = null;
    public ?int $paymentBookingId = null;
    public string $paymentMethod = 'Cash';
    public string $paymentAmount = '';
    public bool $showPaymentModal = false;

    #[On('global-context-updated')]
    public function refreshContext()
    {
        // Reactively triggers a re-render when global top header Location or Turf changes
    }

    public function mount()
    {
        $this->selectedDate = Carbon::now('Asia/Kolkata')->toDateString();
    }

    public function previousDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate, 'Asia/Kolkata')->subDay()->toDateString();
    }

    public function nextDay(): void
    {
        $this->selectedDate = Carbon::parse($this->selectedDate, 'Asia/Kolkata')->addDay()->toDateString();
    }

    public function goToToday(): void
    {
        $this->selectedDate = Carbon::now('Asia/Kolkata')->toDateString();
    }

    public function setStatusFilter(string $filter): void
    {
        $this->statusFilter = $filter;
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
                    'category' => $bs->slot->category?->name ?? 'Standard',
                    'duration' => $bs->slot->duration ?? 30,
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

    public function viewDetails(int $bookingId): void
    {
        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->find($bookingId);
        if (!$booking) return;

        $this->selectedBookingId = $bookingId;
        $this->showDetailModal = true;
    }

    public function closeDetails(): void
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
    }

    public function openPaymentModal(int $bookingDateId): void
    {
        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $bDate = BookingDate::whereHas('booking', function ($q) use ($manageableTurfIds) {
            $q->whereIn('turf_id', $manageableTurfIds);
        })->with('booking')->find($bookingDateId);

        if (!$bDate) return;

        $this->paymentBookingDateId = $bookingDateId;
        $this->paymentBookingId = $bDate->booking_id;

        $paidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');
        $remaining = max(0.00, (float)$bDate->amount - $paidSum);

        $this->paymentAmount = number_format($remaining, 2, '.', '');
        $this->paymentMethod = 'Cash';
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentBookingDateId = null;
        $this->paymentBookingId = null;
        $this->paymentAmount = '';
    }

    public function submitPayment(): void
    {
        $this->validate([
            'paymentAmount' => 'required|numeric|min:0.01',
            'paymentMethod' => 'required|string|in:Cash,UPI,Other',
        ]);

        if (!$this->paymentBookingId) return;

        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->find($this->paymentBookingId);
        if (!$booking) return;

        $amountToPay = (float)$this->paymentAmount;

        DB::beginTransaction();
        try {
            (new \App\Services\BookingPaymentDistributor())->distribute(
                $booking,
                $amountToPay,
                $this->paymentMethod,
                null,
                0.00,
                0.00,
                null,
                null,
                null,
                'Success',
                $this->paymentBookingDateId
            );

            DB::commit();
            session()->flash('status', 'Payment of ₹' . number_format($amountToPay, 2) . ' recorded successfully!');
            $this->closePaymentModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $activeTurfId = session('active_turf_id');
        $activeLocationId = session('active_location_id');

        $manageableTurfs = Turf::manageable($user)->with('location')->get();
        $manageableTurfIds = $manageableTurfs->pluck('id')->toArray();

        // 1. Resolve Target Turf / Location context
        $activeTurf = null;
        if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
            $activeTurf = $manageableTurfs->firstWhere('id', $activeTurfId);
        }

        // 2. Base Query for the selected date
        $query = BookingDate::with([
            'booking.user',
            'booking.turf.location',
            'booking.payments' => fn($q) => $q->where('status', 'Success'),
            'bookingSlots.slot.category',
        ])
        ->whereDate('booking_date', $this->selectedDate);

        if ($activeTurf) {
            $query->whereHas('booking', fn($q) => $q->where('turf_id', $activeTurf->id));
        } elseif ($activeLocationId) {
            $locationTurfIds = $manageableTurfs->where('location_id', $activeLocationId)->pluck('id')->toArray();
            $query->whereHas('booking', fn($q) => $q->whereIn('turf_id', $locationTurfIds));
        } else {
            $query->whereHas('booking', fn($q) => $q->whereIn('turf_id', $manageableTurfIds));
        }

        // Search by customer name, mobile, reference
        if (trim($this->search) !== '') {
            $s = trim($this->search);
            $query->where(function ($q) use ($s) {
                $q->whereHas('booking', function ($bq) use ($s) {
                    $bq->where('booking_reference', 'like', "%{$s}%")
                       ->orWhere('id', 'like', "%{$s}%")
                       ->orWhereHas('user', function ($uq) use ($s) {
                           $uq->where('name', 'like', "%{$s}%")
                              ->orWhere('mobile', 'like', "%{$s}%")
                              ->orWhere('email', 'like', "%{$s}%");
                       });
                });
            });
        }

        $allDayBookings = $query->get();

        // 3. Classify into Upcoming, Past, Cancelled
        $now = Carbon::now('Asia/Kolkata');
        $todayStr = $now->toDateString();
        $currentTimeStr = $now->format('H:i:s');

        $categorized = $allDayBookings->map(function ($bDate) use ($todayStr, $currentTimeStr) {
            $isCancelled = ($bDate->status === 'Cancelled' || $bDate->booking?->status === 'Cancelled');

            // Find earliest slot start time
            $earliestSlotTime = null;
            foreach ($bDate->bookingSlots as $bSlot) {
                if ($bSlot->slot?->from_time) {
                    if ($earliestSlotTime === null || $bSlot->slot->from_time < $earliestSlotTime) {
                        $earliestSlotTime = $bSlot->slot->from_time;
                    }
                }
            }

            $dateRaw = $bDate->booking_date;
            $isPast = false;
            if ($dateRaw < $todayStr) {
                $isPast = true;
            } elseif ($dateRaw === $todayStr && $earliestSlotTime !== null && $earliestSlotTime < $currentTimeStr) {
                $isPast = true;
            }

            $bDate->is_cancelled_session = $isCancelled;
            $bDate->is_past_session = (!$isCancelled && $isPast);
            $bDate->is_upcoming_session = (!$isCancelled && !$isPast);
            $bDate->earliest_time = $earliestSlotTime;

            return $bDate;
        });

        $countAll = $categorized->count();
        $countUpcoming = $categorized->where('is_upcoming_session', true)->count();
        $countPast = $categorized->where('is_past_session', true)->count();
        $countCancelled = $categorized->where('is_cancelled_session', true)->count();

        // Filter by active tab
        $filtered = match ($this->statusFilter) {
            'upcoming' => $categorized->where('is_upcoming_session', true),
            'past' => $categorized->where('is_past_session', true),
            'cancelled' => $categorized->where('is_cancelled_session', true),
            default => $categorized,
        };

        // Sort by earliest slot time
        $sortedBookings = $filtered->sortBy(function ($bDate) {
            return $bDate->earliest_time ?? '99:99:99';
        })->values();

        $selectedBooking = $this->selectedBookingId
            ? Booking::with(['user', 'turf.location', 'bookingDates.bookingSlots.slot.category', 'payments' => fn($q) => $q->where('status', 'Success')])->find($this->selectedBookingId)
            : null;

        return [
            'bookings' => $sortedBookings,
            'countAll' => $countAll,
            'countUpcoming' => $countUpcoming,
            'countPast' => $countPast,
            'countCancelled' => $countCancelled,
            'activeTurf' => $activeTurf,
            'selectedBooking' => $selectedBooking,
            'dateCarbon' => Carbon::parse($this->selectedDate, 'Asia/Kolkata'),
        ];
    }
}; ?>

<div class="space-y-5 max-w-5xl mx-auto px-2 sm:px-4 py-4">

    <!-- Flash Notifications -->
    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl flex items-center gap-2.5 text-xs sm:text-sm font-semibold shadow-xs">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-2xl flex items-center gap-2.5 text-xs sm:text-sm font-semibold shadow-xs">
            <svg class="w-5 h-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Page Header & Venue Breadcrumb -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 font-bold">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </span>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">Client Bookings</h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 mt-1 flex items-center gap-1.5 font-medium">
                @if ($activeTurf)
                    <span>Selected Turf:</span>
                    <span class="font-bold text-slate-800">{{ $activeTurf->name }}</span>
                    @if ($activeTurf->location)
                        <span class="text-slate-400">• {{ $activeTurf->location->name }}</span>
                    @endif
                @else
                    <span>Showing all manageable venues under active top filter.</span>
                @endif
            </p>
        </div>

        <!-- Search Bar -->
        <div class="relative w-full md:w-72">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search customer, phone, ref..." 
                class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-slate-300 focus:border-emerald-500 focus:ring-emerald-500 bg-slate-50/50">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            @if ($search)
                <button wire:click="$set('search', '')" class="absolute right-3 top-2.5 text-slate-400 hover:text-slate-600">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            @endif
        </div>
    </div>

    <!-- Status Tabs (All, Upcoming, Past, Cancelled) -->
    <div class="flex items-center justify-between sm:justify-start gap-1 sm:gap-2 overflow-x-auto p-1.5 bg-slate-100/80 rounded-2xl">
        <button wire:click="setStatusFilter('all')" type="button" 
            class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition cursor-pointer active:scale-95 text-center flex items-center justify-center gap-1.5 {{ $statusFilter === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
            <span>All</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $statusFilter === 'all' ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $countAll }}</span>
        </button>

        <button wire:click="setStatusFilter('upcoming')" type="button" 
            class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition cursor-pointer active:scale-95 text-center flex items-center justify-center gap-1.5 {{ $statusFilter === 'upcoming' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
            <span>Upcoming</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $statusFilter === 'upcoming' ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $countUpcoming }}</span>
        </button>

        <button wire:click="setStatusFilter('past')" type="button" 
            class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition cursor-pointer active:scale-95 text-center flex items-center justify-center gap-1.5 {{ $statusFilter === 'past' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
            <span>Past</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $statusFilter === 'past' ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $countPast }}</span>
        </button>

        <button wire:click="setStatusFilter('cancelled')" type="button" 
            class="flex-1 sm:flex-none px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition cursor-pointer active:scale-95 text-center flex items-center justify-center gap-1.5 {{ $statusFilter === 'cancelled' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/60' }}">
            <span>Cancelled</span>
            <span class="px-1.5 py-0.5 rounded-full text-[10px] {{ $statusFilter === 'cancelled' ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-700' }}">{{ $countCancelled }}</span>
        </button>
    </div>

    <!-- Date Navigation Carousel -->
    <div class="bg-emerald-50/60 border border-emerald-100 rounded-2xl p-3 sm:p-4 flex items-center justify-between shadow-2xs">
        <!-- Previous Day Button -->
        <button wire:click="previousDay" type="button" title="Previous Day"
            class="p-2 sm:px-3 sm:py-2 rounded-xl bg-white hover:bg-emerald-100/70 border border-emerald-200 text-emerald-800 transition active:scale-90 shadow-2xs flex items-center gap-1 font-bold text-xs">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            <span class="hidden sm:inline">Prev</span>
        </button>

        <!-- Current Date Info with Quick Calendar Picker -->
        <div class="text-center relative">
            <div class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider">
                {{ $dateCarbon->format('l') }}
            </div>
            <div class="text-base sm:text-lg font-black text-slate-900 tracking-tight flex items-center justify-center gap-2 mt-0.5">
                <span>{{ $dateCarbon->format('d M Y') }}</span>
                <label class="cursor-pointer text-emerald-600 hover:text-emerald-800" title="Pick date">
                    <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <input type="date" wire:model.live="selectedDate" class="sr-only">
                </label>
                @if ($selectedDate !== Carbon::now('Asia/Kolkata')->toDateString())
                    <button wire:click="goToToday" type="button" class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-600 text-white hover:bg-emerald-700 transition">
                        Today
                    </button>
                @endif
            </div>
        </div>

        <!-- Next Day Button -->
        <button wire:click="nextDay" type="button" title="Next Day"
            class="p-2 sm:px-3 sm:py-2 rounded-xl bg-white hover:bg-emerald-100/70 border border-emerald-200 text-emerald-800 transition active:scale-90 shadow-2xs flex items-center gap-1 font-bold text-xs">
            <span class="hidden sm:inline">Next</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    <!-- Client Booking Cards List -->
    <div class="space-y-4">
        @forelse ($bookings as $bDate)
            @php
                $booking = $bDate->booking;
                $slotRanges = $this->formatConsecutiveSlots($bDate->bookingSlots);

                // Financial calculations for this date
                $dateTotal = (float)$bDate->amount;
                $paidSum = (float)$bDate->paid_amount;
                if ($paidSum <= 0 && $booking) {
                    $totalBookingPaid = (float)$booking->payments->sum('amount');
                    $paidSum = min($dateTotal, $totalBookingPaid);
                }
                if ($bDate->payment_status === 'Paid') {
                    $dateBalance = 0.00;
                    $paidSum = $dateTotal;
                } else {
                    $dateBalance = max(0.00, $dateTotal - $paidSum);
                }
            @endphp

            <div class="bg-white rounded-2xl border border-emerald-200/80 hover:border-emerald-400 p-4 sm:p-5 shadow-xs hover:shadow-md transition-all duration-200">
                <!-- Top Header: Turf Name & Badges -->
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900">{{ $booking->turf->name ?? 'Turf' }}</h2>
                        <div class="text-xs font-mono font-semibold text-slate-500 mt-0.5">
                            {{ $booking->booking_reference ?? ('#' . $booking->id) }}
                        </div>
                    </div>

                    <div class="flex items-center gap-1.5 shrink-0">
                        <!-- Session Status Badge -->
                        @if ($bDate->is_cancelled_session)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                Cancelled
                            </span>
                        @elseif ($bDate->is_upcoming_session)
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-bold bg-teal-50 text-teal-800 border border-teal-300">
                                <svg class="w-3.5 h-3.5 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Upcoming
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                Completed
                            </span>
                        @endif

                        <!-- Payment Status Badge -->
                        @if ($bDate->payment_status === 'Paid')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                Paid
                            </span>
                        @elseif ($bDate->payment_status === 'Partially Paid')
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                Partial
                            </span>
                        @else
                            <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                Unpaid
                            </span>
                        @endif
                    </div>
                </div>

                <!-- Middle Content: Customer & Slots -->
                <div class="mt-3.5 space-y-2">
                    <!-- Client Details -->
                    <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-800 font-medium">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        <span class="font-bold text-slate-900">{{ $booking->user->name ?? 'Customer' }}</span>
                        @if ($booking->user?->mobile)
                            <span class="text-slate-500">({{ $booking->user->mobile }})</span>
                            <a href="tel:{{ $booking->user->mobile }}" class="text-emerald-600 hover:text-emerald-800 ml-1" title="Call">
                                <svg class="w-3.5 h-3.5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </a>
                            <a href="https://wa.me/91{{ preg_replace('/[^0-9]/', '', $booking->user->mobile) }}" target="_blank" class="text-emerald-600 hover:text-emerald-800 ml-1" title="WhatsApp">
                                <svg class="w-3.5 h-3.5 inline" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.299.045-.677.063-1.092-.069-.252-.08-.575-.187-.988-.365-1.739-.751-2.874-2.502-2.961-2.617-.087-.116-.708-.94-.708-1.793s.448-1.273.607-1.446c.159-.173.346-.217.462-.217l.332.007c.106.005.249-.04.39.298.144.347.491 1.2.534 1.287.043.087.072.188.014.304-.058.116-.087.188-.173.289l-.26.304c-.087.086-.177.18-.076.354.101.174.449.741.964 1.201.662.591 1.221.774 1.394.86s.275.072.376-.043c.101-.116.433-.506.549-.68.116-.173.231-.145.39-.087s1.011.477 1.184.564.289.13.332.202c.043.072.043.419-.101.824z"/></svg>
                            </a>
                        @endif
                    </div>

                    <!-- Slot Timings -->
                    <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm text-slate-700">
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @if (!empty($slotRanges))
                            @foreach ($slotRanges as $r)
                                <span class="font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded-md">
                                    {{ $r['from'] }} - {{ $r['to'] }}
                                </span>
                            @endforeach
                        @else
                            <span class="font-medium text-slate-500">Slot timing not set</span>
                        @endif
                    </div>
                </div>

                <!-- Divider Line -->
                <hr class="my-3.5 border-slate-100">

                <!-- Financial Footer & Quick Actions -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <!-- Price Columns -->
                    <div class="grid grid-cols-3 gap-4 text-center sm:text-left">
                        <div>
                            <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Total</div>
                            <div class="text-sm sm:text-base font-black text-slate-900">₹{{ number_format($dateTotal, 0) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Paid</div>
                            <div class="text-sm sm:text-base font-black text-emerald-600">₹{{ number_format($paidSum, 0) }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Balance</div>
                            <div class="text-sm sm:text-base font-black {{ $dateBalance > 0 ? 'text-rose-600' : 'text-slate-700' }}">
                                ₹{{ number_format($dateBalance, 0) }}
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center gap-2 shrink-0 justify-end">
                        @if ($dateBalance > 0 && !$bDate->is_cancelled_session)
                            <button wire:click="openPaymentModal({{ $bDate->id }})" type="button"
                                class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition active:scale-95 shadow-2xs cursor-pointer">
                                Record Payment
                            </button>
                        @endif

                        <a href="{{ route('turf.bookings.invoice', $booking->id) }}" target="_blank"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition active:scale-95 flex items-center gap-1 cursor-pointer"
                            title="Download Tax Invoice / Bill">
                            <svg class="w-3.5 h-3.5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Bill</span>
                        </a>

                        <button wire:click="viewDetails({{ $booking->id }})" type="button"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold text-xs transition active:scale-95 cursor-pointer">
                            Details
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="bg-white rounded-3xl border border-slate-200/80 p-8 sm:p-12 text-center shadow-xs">
                <div class="w-16 h-16 rounded-3xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <h3 class="text-base sm:text-lg font-black text-slate-900">No {{ $statusFilter !== 'all' ? ucfirst($statusFilter) : '' }} Bookings Found</h3>
                <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-sm mx-auto">
                    There are no bookings for <strong>{{ $dateCarbon->format('l, d M Y') }}</strong> under the selected criteria.
                </p>
                <div class="mt-4 flex items-center justify-center gap-2">
                    <button wire:click="goToToday" type="button" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs cursor-pointer">
                        Go to Today
                    </button>
                    @if ($statusFilter !== 'all')
                        <button wire:click="setStatusFilter('all')" type="button" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer">
                            View All for this Date
                        </button>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    <!-- Booking Details Drawer Modal -->
    @if ($showDetailModal && $selectedBooking)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6" wire:click.self="closeDetails">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-xl w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-slate-50 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-mono font-bold text-slate-500">{{ $selectedBooking->booking_reference }}</div>
                        <h2 class="text-lg font-black text-slate-900">{{ $selectedBooking->turf->name ?? 'Turf' }}</h2>
                    </div>
                    <button wire:click="closeDetails" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-200/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="p-6 space-y-4 max-h-[75vh] overflow-y-auto text-xs sm:text-sm">
                    <!-- Customer Details -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100 space-y-1">
                        <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider">Customer Details</div>
                        <div class="font-bold text-slate-900 text-sm">{{ $selectedBooking->user->name ?? 'Customer' }}</div>
                        <div class="text-slate-600">Mobile: {{ $selectedBooking->user->mobile ?? 'N/A' }} | Email: {{ $selectedBooking->user->email ?? 'N/A' }}</div>
                        @if ($selectedBooking->customer_company_name)
                            <div class="text-slate-600">Company: <strong>{{ $selectedBooking->customer_company_name }}</strong></div>
                        @endif
                        @if ($selectedBooking->customer_gstin)
                            <div class="text-slate-600">GSTIN: <strong>{{ $selectedBooking->customer_gstin }}</strong></div>
                        @endif
                    </div>

                    <!-- Booked Dates & Slots -->
                    <div>
                        <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider mb-2">Booked Session Dates</div>
                        <div class="space-y-2">
                            @foreach ($selectedBooking->bookingDates as $d)
                                <div class="p-3 bg-white rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-slate-900">{{ Carbon::parse($d->booking_date)->format('D, d M Y') }}</div>
                                        <div class="text-slate-500 mt-0.5">
                                            @php $dRanges = $this->formatConsecutiveSlots($d->bookingSlots); @endphp
                                            @foreach ($dRanges as $r)
                                                <span class="inline-block bg-slate-100 px-1.5 py-0.5 rounded text-[11px] font-semibold text-slate-700 mr-1">
                                                    {{ $r['from'] }} - {{ $r['to'] }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-slate-900">₹{{ number_format($d->amount, 2) }}</div>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $d->payment_status === 'Paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                            {{ $d->payment_status }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Financial Summary -->
                    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-100">
                        <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider mb-2">Financial Breakdown</div>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex justify-between text-slate-600">
                                <span>Total Booking Amount:</span>
                                <span class="font-bold text-slate-900">₹{{ number_format($selectedBooking->total_amount, 2) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600">
                                <span>Total Paid:</span>
                                <span class="font-bold text-emerald-600">₹{{ number_format($selectedBooking->payments->sum('amount'), 2) }}</span>
                            </div>
                            <div class="flex justify-between text-slate-600 border-t border-slate-200 pt-1.5 font-bold">
                                <span>Balance Remaining:</span>
                                <span class="{{ $selectedBooking->balance_amount > 0 ? 'text-rose-600' : 'text-slate-700' }}">
                                    ₹{{ number_format(max(0, $selectedBooking->total_amount - $selectedBooking->payments->sum('amount')), 2) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Payments History -->
                    @if ($selectedBooking->payments->isNotEmpty())
                        <div>
                            <div class="text-[10px] font-black uppercase text-slate-400 tracking-wider mb-2">Payment Records</div>
                            <div class="space-y-1.5">
                                @foreach ($selectedBooking->payments as $p)
                                    <div class="p-2.5 bg-white rounded-xl border border-slate-200 flex items-center justify-between text-xs">
                                        <div>
                                            <span class="font-bold text-slate-900">{{ $p->payment_method }}</span>
                                            <span class="text-slate-400 text-[11px] ml-1">({{ $p->paid_at ? Carbon::parse($p->paid_at)->format('d M Y, h:i A') : $p->created_at->format('d M Y') }})</span>
                                            @if ($p->gateway_payment_id)
                                                <div class="text-[10px] font-mono text-slate-400">{{ $p->gateway_payment_id }}</div>
                                            @endif
                                        </div>
                                        <div class="font-bold text-emerald-600">
                                            ₹{{ number_format($p->amount, 2) }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <a href="{{ route('turf.bookings.invoice', $selectedBooking->id) }}" target="_blank"
                        class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center gap-1.5 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <span>Download Invoice Bill</span>
                    </a>
                    <button wire:click="closeDetails" type="button" class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Record Payment Modal -->
    @if ($showPaymentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6" wire:click.self="closePaymentModal">
            <div class="bg-white rounded-3xl shadow-2xl border border-slate-200 max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-6 py-4 bg-emerald-50/70 border-b border-emerald-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm">₹</span>
                        <h2 class="text-base font-black text-slate-900">Record Offline Payment</h2>
                    </div>
                    <button wire:click="closePaymentModal" class="text-slate-400 hover:text-slate-600 p-1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form wire:submit="submitPayment" class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Amount (₹)</label>
                        <input type="number" step="0.01" wire:model="paymentAmount" required
                            class="w-full text-base font-bold px-3 py-2 rounded-xl border border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                        @error('paymentAmount') <span class="text-xs text-rose-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 mb-1">Payment Method</label>
                        <select wire:model="paymentMethod" class="w-full text-xs font-bold px-3 py-2 rounded-xl border border-slate-300 focus:border-emerald-500 focus:ring-emerald-500">
                            <option value="Cash">Cash at Venue</option>
                            <option value="UPI">UPI / QR Code</option>
                            <option value="Other">Other / Card</option>
                        </select>
                        @error('paymentMethod') <span class="text-xs text-rose-600 font-semibold">{{ $message }}</span> @enderror
                    </div>

                    <div class="pt-3 flex items-center justify-end gap-2 border-t border-slate-100">
                        <button type="button" wire:click="closePaymentModal" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs transition shadow-xs">
                            Confirm & Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

</div>
