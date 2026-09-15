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
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Filters & Search
    public string $search = '';
    public string $datePreset = 'all';
    public string $startDate = '';
    public string $endDate = '';
    public string $statusFilter = 'all';
    public string $paymentStatusFilter = 'all';
    public string $bookingTypeFilter = 'all';
    public string $sortBy = 'newest';
    public int $perPage = 15;

    // Detail Drawer Modal
    public ?int $selectedBookingId = null;
    public bool $showDetailModal = false;

    // Record Payment Modal
    public ?int $paymentBookingDateId = null;
    public ?int $paymentBookingId = null;
    public string $paymentMethod = 'Cash';
    public string $paymentAmount = '';
    public bool $showPaymentModal = false;

    // Cancel Booking Modal
    public ?int $cancelBookingId = null;
    public array $cancelDateIds = [];
    public bool $showCancelModal = false;

    #[On('global-context-updated')]
    public function refreshContext()
    {
        $this->resetPage();
    }

    public function mount()
    {
        $this->setQuickPreset('all');
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingPaymentStatusFilter() { $this->resetPage(); }
    public function updatingBookingTypeFilter() { $this->resetPage(); }
    public function updatingStartDate() { $this->datePreset = 'custom'; $this->resetPage(); }
    public function updatingEndDate() { $this->datePreset = 'custom'; $this->resetPage(); }

    public function formatConsecutiveSlots($bookingSlots): array
    {
        $slots = [];
        foreach ($bookingSlots as $bs) {
            if ($bs->slot && $bs->slot->from_time && $bs->slot->to_time) {
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

    public function setQuickPreset(string $preset)
    {
        $this->datePreset = $preset;
        $now = Carbon::now('Asia/Kolkata');

        if ($preset === 'today') {
            $this->startDate = $now->toDateString();
            $this->endDate = $now->toDateString();
        } elseif ($preset === 'tomorrow') {
            $this->startDate = $now->copy()->addDay()->toDateString();
            $this->endDate = $now->copy()->addDay()->toDateString();
        } elseif ($preset === 'week') {
            $this->startDate = $now->copy()->startOfWeek()->toDateString();
            $this->endDate = $now->copy()->endOfWeek()->toDateString();
        } elseif ($preset === 'month') {
            $this->startDate = $now->copy()->startOfMonth()->toDateString();
            $this->endDate = $now->copy()->endOfMonth()->toDateString();
        } else {
            $this->startDate = '';
            $this->endDate = '';
        }

        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->statusFilter = 'all';
        $this->paymentStatusFilter = 'all';
        $this->bookingTypeFilter = 'all';
        $this->sortBy = 'newest';
        $this->setQuickPreset('all');
    }

    public function viewDetails(int $bookingId)
    {
        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->find($bookingId);
        if (!$booking) return;

        $this->selectedBookingId = $bookingId;
        $this->showDetailModal = true;
    }

    public function closeDetails()
    {
        $this->showDetailModal = false;
        $this->selectedBookingId = null;
    }

    public function openPaymentModal(int $bookingDateId)
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

    public function closePaymentModal()
    {
        $this->showPaymentModal = false;
        $this->paymentBookingDateId = null;
        $this->paymentBookingId = null;
        $this->paymentAmount = '';
    }

    public function submitPayment()
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
            // Distribute payment proportionally across active dates
            $bookingDates = $booking->bookingDates()->where('status', '!=', 'Cancelled')->get();
            if ($bookingDates->isEmpty()) {
                $bookingDates = $booking->bookingDates()->get();
            }

            $dateBalances = [];
            $totalRemainingBalance = 0.00;

            foreach ($bookingDates as $bDate) {
                $paidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');
                $balance = max(0.00, (float)$bDate->amount - $paidSum);
                $dateBalances[$bDate->id] = $balance;
                $totalRemainingBalance += $balance;
            }

            if ($totalRemainingBalance > 0) {
                $actualAmountToDistribute = min($amountToPay, $totalRemainingBalance);
                $remainingToDistribute = $actualAmountToDistribute;
                $unpaidDates = $bookingDates->filter(fn($d) => ($dateBalances[$d->id] ?? 0) > 0)->values();
                $count = $unpaidDates->count();

                foreach ($unpaidDates as $index => $bDate) {
                    if ($remainingToDistribute <= 0) break;

                    if ($index === $count - 1) {
                        $paidForDate = round($remainingToDistribute, 2);
                    } else {
                        $ratio = $dateBalances[$bDate->id] / $totalRemainingBalance;
                        $paidForDate = round($actualAmountToDistribute * $ratio, 2);
                        $paidForDate = min($paidForDate, $remainingToDistribute);
                    }

                    if ($paidForDate > 0) {
                        Payment::create([
                            'booking_id' => $booking->id,
                            'booking_date_id' => $bDate->id,
                            'payment_method' => $this->paymentMethod,
                            'amount' => $paidForDate,
                            'status' => 'Success',
                            'paid_at' => Carbon::now(),
                        ]);
                        $remainingToDistribute -= $paidForDate;
                    }
                }

                // Recalculate payment status
                $booking->load('bookingDates');
                $allDatesPaid = true;
                $anyDatePaid = false;
                $totalBookingAmt = 0.00;

                foreach ($booking->bookingDates as $bDate) {
                    $totalBookingAmt += (float)$bDate->amount;
                    $bPaidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');
                    if ($bDate->amount > 0 && $bPaidSum >= $bDate->amount) {
                        $bDate->update(['payment_status' => 'Paid']);
                        $anyDatePaid = true;
                    } elseif ($bPaidSum > 0) {
                        $bDate->update(['payment_status' => 'Partially Paid']);
                        $allDatesPaid = false;
                        $anyDatePaid = true;
                    } else {
                        $bDate->update(['payment_status' => 'Unpaid']);
                        $allDatesPaid = false;
                    }
                }

                if ($allDatesPaid && $totalBookingAmt > 0) {
                    $booking->update(['payment_status' => 'Paid']);
                } elseif ($anyDatePaid) {
                    $booking->update(['payment_status' => 'Partially Paid']);
                } else {
                    $booking->update(['payment_status' => 'Unpaid']);
                }
            }

            DB::commit();
            session()->flash('status', 'Payment of ₹' . number_format($amountToPay, 2) . ' recorded successfully!');
            $this->closePaymentModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Failed to record payment: ' . $e->getMessage());
        }
    }

    public function openCancelModal(int $bookingId)
    {
        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->with('bookingDates')->find($bookingId);
        if (!$booking) return;

        $this->cancelBookingId = $bookingId;
        $this->cancelDateIds = $booking->bookingDates->where('status', 'Confirmed')->pluck('id')->toArray();
        $this->showCancelModal = true;
    }

    public function closeCancelModal()
    {
        $this->showCancelModal = false;
        $this->cancelBookingId = null;
        $this->cancelDateIds = [];
    }

    public function submitCancellation()
    {
        if (!$this->cancelBookingId || empty($this->cancelDateIds)) {
            session()->flash('error', 'Please select at least one booking date to cancel.');
            return;
        }

        $manageableTurfIds = Turf::manageable()->pluck('id')->toArray();
        $booking = Booking::whereIn('turf_id', $manageableTurfIds)->with(['turf', 'bookingDates.bookingSlots'])->find($this->cancelBookingId);
        if (!$booking) return;

        DB::beginTransaction();
        try {
            $turf = $booking->turf;
            $cancellationFeePerSlot = (float)($turf->cancellation_fee ?? 0.00);

            foreach ($this->cancelDateIds as $bdId) {
                $bDate = $booking->bookingDates->firstWhere('id', $bdId);
                if (!$bDate || $bDate->status === 'Cancelled') continue;

                $slotCount = $bDate->bookingSlots->count();
                $dateFee = $cancellationFeePerSlot * $slotCount;
                $datePaidSum = (float) Payment::where('booking_date_id', $bDate->id)->where('status', 'Success')->sum('amount');
                $refundForDate = max(0.00, $datePaidSum - $dateFee);

                $bDate->update([
                    'status' => 'Cancelled',
                    'cancelled_at' => Carbon::now(),
                    'cancellation_fee_applied' => $dateFee,
                    'refund_amount' => $refundForDate,
                    'refund_status' => ($refundForDate > 0) ? 'Refunded' : 'Not Applicable',
                    'refunded_at' => ($refundForDate > 0) ? Carbon::now() : null,
                ]);
            }

            // Update parent booking status
            $allDates = $booking->bookingDates()->get();
            $totalCount = $allDates->count();
            $cancelledCount = $allDates->where('status', 'Cancelled')->count();

            $parentStatus = ($cancelledCount === $totalCount) ? 'Cancelled' : (($cancelledCount > 0) ? 'Partially Cancelled' : 'Confirmed');

            $booking->update([
                'status' => $parentStatus,
                'cancelled_at' => $allDates->whereNotNull('cancelled_at')->min('cancelled_at'),
                'cancellation_fee_applied' => (float)$allDates->sum('cancellation_fee_applied'),
                'refund_amount' => (float)$allDates->sum('refund_amount'),
                'refund_status' => ($allDates->sum('refund_amount') > 0) ? 'Refunded' : 'Not Applicable',
                'refunded_at' => $allDates->whereNotNull('refunded_at')->max('refunded_at'),
            ]);

            DB::commit();
            session()->flash('status', 'Booking cancellation processed successfully.');
            $this->closeCancelModal();
        } catch (\Exception $e) {
            DB::rollBack();
            session()->flash('error', 'Cancellation failed: ' . $e->getMessage());
        }
    }
}; ?>

<div class="w-full">
    <div class="w-full space-y-6">
        
        <!-- Top Bar / Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2.5">
                    <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Bookings Management
                </h1>
                <p class="text-xs text-gray-500 mt-1">
                    Track, search, filter, and manage all customer slot bookings and offline payments in real-time.
                </p>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
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

            // Base query for statistics & listing strictly scoped to manageable turfs
            $baseQuery = Booking::with(['turf.location', 'user', 'bookingDates.bookingSlots.slot.category', 'payments']);
            if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
                $baseQuery->where('turf_id', $activeTurfId);
            } else {
                $baseQuery->whereIn('turf_id', $manageableTurfIds);
            }

            // Stats counts
            $statsAll = (clone $baseQuery)->count();
            $statsConfirmed = (clone $baseQuery)->where('status', 'Confirmed')->count();
            $statsPartPaid = (clone $baseQuery)->where('payment_status', 'Partially Paid')->count();
            $statsUnpaid = (clone $baseQuery)->where('payment_status', 'Unpaid')->count();
            $statsCancelled = (clone $baseQuery)->where('status', 'Cancelled')->count();

            // Filtered Query for Listing
            $query = (clone $baseQuery);

            // Search filter
            if (trim($this->search) !== '') {
                $search = trim($this->search);
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%{$search}%")
                      ->orWhere('booking_reference', 'LIKE', "%{$search}%")
                      ->orWhereHas('user', function ($uq) use ($search) {
                          $uq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('email', 'LIKE', "%{$search}%")
                            ->orWhere('mobile', 'LIKE', "%{$search}%");
                      });
                });
            }

            // Date Preset / Range Filter
            if ($this->startDate && $this->endDate) {
                $query->whereHas('bookingDates', function ($q) {
                    $q->whereBetween('booking_date', [$this->startDate, $this->endDate]);
                });
            }

            // Status Filter
            if ($this->statusFilter !== 'all') {
                $query->where('status', $this->statusFilter);
            }

            // Payment Status Filter
            if ($this->paymentStatusFilter !== 'all') {
                $query->where('payment_status', $this->paymentStatusFilter);
            }

            // Booking Type Filter
            if ($this->bookingTypeFilter !== 'all') {
                $query->where('booking_type', $this->bookingTypeFilter);
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

        <!-- KPI Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
            <div wire:click="$set('statusFilter', 'all'); $set('paymentStatusFilter', 'all');" 
                 class="bg-white p-4 rounded-2xl border border-gray-200 shadow-xs cursor-pointer hover:border-indigo-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500">All Bookings</span>
                    <span class="p-1.5 bg-gray-100 text-gray-600 rounded-lg text-xs font-bold">📋</span>
                </div>
                <p class="text-2xl font-black text-gray-900 mt-2">{{ number_format($statsAll) }}</p>
            </div>

            <div wire:click="$set('statusFilter', 'Confirmed')" 
                 class="bg-white p-4 rounded-2xl border border-gray-200 shadow-xs cursor-pointer hover:border-blue-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-blue-600">Confirmed</span>
                    <span class="p-1.5 bg-blue-50 text-blue-600 rounded-lg text-xs font-bold">✅</span>
                </div>
                <p class="text-2xl font-black text-blue-600 mt-2">{{ number_format($statsConfirmed) }}</p>
            </div>

            <div wire:click="$set('paymentStatusFilter', 'Partially Paid')" 
                 class="bg-white p-4 rounded-2xl border border-gray-200 shadow-xs cursor-pointer hover:border-amber-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-amber-600">Partially Paid</span>
                    <span class="p-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-bold">⏳</span>
                </div>
                <p class="text-2xl font-black text-amber-600 mt-2">{{ number_format($statsPartPaid) }}</p>
            </div>

            <div wire:click="$set('paymentStatusFilter', 'Unpaid')" 
                 class="bg-white p-4 rounded-2xl border border-gray-200 shadow-xs cursor-pointer hover:border-red-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-red-600">Unpaid</span>
                    <span class="p-1.5 bg-red-50 text-red-600 rounded-lg text-xs font-bold">⚠️</span>
                </div>
                <p class="text-2xl font-black text-red-600 mt-2">{{ number_format($statsUnpaid) }}</p>
            </div>

            <div wire:click="$set('statusFilter', 'Cancelled')" 
                 class="bg-white p-4 rounded-2xl border border-gray-200 shadow-xs cursor-pointer hover:border-gray-400 transition col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500">Cancelled</span>
                    <span class="p-1.5 bg-gray-100 text-gray-500 rounded-lg text-xs font-bold">🚫</span>
                </div>
                <p class="text-2xl font-black text-gray-500 mt-2">{{ number_format($statsCancelled) }}</p>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="bg-white rounded-2xl shadow-xs border border-gray-200 p-5 space-y-4">
            
            <!-- Quick Date Filter Preset Chips -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400 me-1">Dates:</span>
                    <button wire:click="setQuickPreset('all')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        All Time
                    </button>
                    <button wire:click="setQuickPreset('today')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'today' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Today
                    </button>
                    <button wire:click="setQuickPreset('tomorrow')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'tomorrow' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        Tomorrow
                    </button>
                    <button wire:click="setQuickPreset('week')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'week' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        This Week
                    </button>
                    <button wire:click="setQuickPreset('month')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'month' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        This Month
                    </button>
                </div>

                <!-- Clear Filters Button -->
                @if ($search !== '' || $statusFilter !== 'all' || $paymentStatusFilter !== 'all' || $bookingTypeFilter !== 'all' || $datePreset !== 'all')
                    <button wire:click="clearFilters" type="button" 
                        class="text-xs font-semibold text-red-600 hover:underline flex items-center gap-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reset All Filters
                    </button>
                @endif
            </div>

            <!-- Search & Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <!-- Search Input -->
                <div class="lg:col-span-2 relative">
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Search Customer / Ref</label>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name, Mobile, Ref #..." 
                            class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- Booking Status -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Booking Status</label>
                    <select wire:model.live="statusFilter" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Statuses</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Partially Cancelled">Partially Cancelled</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Payment Status</label>
                    <select wire:model.live="paymentStatusFilter" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Payments</option>
                        <option value="Paid">Paid (Full)</option>
                        <option value="Partially Paid">Partially Paid</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>

                <!-- Booking Type -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Booking Type</label>
                    <select wire:model.live="bookingTypeFilter" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Types</option>
                        <option value="day">Single Day</option>
                        <option value="long">Long (Multi-Date)</option>
                        <option value="scattered">Scattered</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Sort By</label>
                    <select wire:model.live="sortBy" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="date_asc">Session Date (Earliest)</option>
                        <option value="date_desc">Session Date (Latest)</option>
                    </select>
                </div>
            </div>

            <!-- Custom Date Range Row -->
            @if ($datePreset === 'custom')
                <div class="pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">From Date</label>
                        <input type="date" wire:model.live="startDate" class="w-full py-1.5 px-3 text-xs rounded-xl border border-gray-300">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 mb-1">To Date</label>
                        <input type="date" wire:model.live="endDate" class="w-full py-1.5 px-3 text-xs rounded-xl border border-gray-300">
                    </div>
                </div>
            @endif
        </div>

        <!-- Bookings Main Content: Full Width Cards -->
        <div class="space-y-4">
            @forelse ($bookings as $b)
                @php
                    $activeDates = $b->bookingDates->where('status', '!=', 'Cancelled');
                    $totalAmount = (float)($b->total_amount > 0 ? $b->total_amount : $activeDates->sum('amount'));
                    $paidSum = (float)$b->payments->where('status', 'Success')->sum('amount');
                    $balance = max(0.00, $totalAmount - $paidSum);

                    // Collect all booking slots across dates to compute overall timing or datewise timing
                    $allSlots = $b->bookingDates->flatMap(fn($bd) => $bd->bookingSlots);
                    $consecutiveRanges = $this->formatConsecutiveSlots($allSlots);

                    $dateList = $b->bookingDates->pluck('booking_date')->toArray();
                @endphp

                <div class="bg-white rounded-2xl border border-gray-200/90 hover:border-indigo-400/80 shadow-xs hover:shadow-md transition-all duration-200 p-5 sm:p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-5">
                        
                        <!-- Col 1: Booking ID & Timestamp -->
                        <div class="flex items-start gap-3.5 lg:w-1/5 shrink-0">
                            <div class="w-10 h-10 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-700 font-black text-sm shrink-0">
                                #
                            </div>
                            <div>
                                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Booking ID</span>
                                <div class="text-base font-black text-gray-900 tracking-tight mt-0.5">
                                    {{ $b->booking_reference ?? ('#' . $b->id) }}
                                </div>
                                <div class="text-[11px] text-gray-500 mt-1 flex items-center gap-1">
                                    <span>📅 {{ $b->created_at ? $b->created_at->format('d M Y, h:i A') : 'N/A' }}</span>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-gray-100 text-gray-600 mt-1.5 uppercase">
                                    {{ ucfirst($b->booking_type ?? 'day') }}
                                </span>
                            </div>
                        </div>

                        <!-- Col 2: Customer Details -->
                        <div class="flex items-start gap-3 lg:w-1/4 shrink-0 border-t lg:border-t-0 lg:border-l border-gray-100 pt-3 lg:pt-0 lg:pl-5">
                            <div class="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs shrink-0 mt-0.5">
                                {{ strtoupper(substr($b->user?->name ?? 'G', 0, 1)) }}
                            </div>
                            <div class="min-w-0 space-y-0.5">
                                <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Customer Details</span>
                                <div class="font-extrabold text-sm text-gray-900 truncate">
                                    {{ $b->user?->name ?? 'Guest / Manual User' }}
                                </div>
                                <div class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <span>{{ $b->user?->mobile ?? 'No contact' }}</span>
                                </div>
                                @if ($b->user?->email)
                                    <div class="text-[11px] text-gray-400 truncate max-w-[200px]" title="{{ $b->user->email }}">
                                        {{ $b->user->email }}
                                    </div>
                                @endif
                                @if ($b->customer_gstin || $b->customer_company_name)
                                    <div class="text-[10px] text-indigo-700 font-semibold mt-0.5">
                                        🏢 {{ $b->customer_company_name ?? 'B2B' }} ({{ $b->customer_gstin }})
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Col 3: Booking Date -->
                        <div class="lg:w-1/5 shrink-0 border-t lg:border-t-0 lg:border-l border-gray-100 pt-3 lg:pt-0 lg:pl-5 space-y-1">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Booking Date</span>
                            @if (count($dateList) === 1)
                                @php $cdObj = Carbon::parse($dateList[0]); @endphp
                                <div class="font-extrabold text-sm text-gray-900 flex items-center gap-1.5">
                                    <span>📅 {{ $cdObj->format('d M Y') }}</span>
                                </div>
                                <span class="text-xs font-semibold text-gray-500">
                                    {{ $cdObj->format('l') }}
                                </span>
                            @elseif (count($dateList) > 1)
                                <div class="font-extrabold text-sm text-gray-900">
                                    📅 {{ Carbon::parse($dateList[0])->format('d M') }} &rarr; {{ Carbon::parse(end($dateList))->format('d M Y') }}
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                    {{ count($dateList) }} Dates Total
                                </span>
                            @else
                                <span class="text-xs text-gray-400 italic">No date recorded</span>
                            @endif

                            <div class="text-[11px] text-gray-500 pt-1">
                                Turf: <strong class="text-gray-800">{{ $b->turf?->name }}</strong>
                            </div>
                        </div>

                        <!-- Col 4: Timing (Consecutive Slots from - to) -->
                        <div class="lg:w-1/4 shrink-0 border-t lg:border-t-0 lg:border-l border-gray-100 pt-3 lg:pt-0 lg:pl-5 space-y-1">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider block">Timing (Slots Range)</span>
                            
                            @if (!empty($consecutiveRanges))
                                <div class="space-y-1.5">
                                    @foreach ($consecutiveRanges as $range)
                                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-teal-50/80 border border-teal-200 text-teal-900 font-extrabold text-xs shadow-2xs">
                                            <span>⏰ {{ $range['from'] }} - {{ $range['to'] }}</span>
                                            @if ($range['slots_count'] > 1)
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 bg-teal-200/60 rounded text-teal-800">
                                                    {{ $range['slots_count'] }} slots
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-xs text-gray-400 italic">No slot timings recorded</span>
                            @endif

                            <div class="text-[11px] text-gray-500 pt-1 flex items-center gap-1">
                                <span>Total Booked: <strong>{{ $allSlots->count() }} {{ \Illuminate\Support\Str::plural('slot', $allSlots->count()) }}</strong></span>
                            </div>
                        </div>

                        <!-- Col 5: Financials, Status & Action -->
                        <div class="flex items-center justify-between lg:justify-end gap-4 border-t lg:border-t-0 lg:border-l border-gray-100 pt-3 lg:pt-0 lg:pl-5 shrink-0">
                            <div class="text-left lg:text-right space-y-1">
                                <div class="text-sm font-black text-gray-900">
                                    ₹{{ number_format($totalAmount, 2) }}
                                </div>
                                <div>
                                    @if ($b->payment_status === 'Paid')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            ✓ Paid
                                        </span>
                                    @elseif ($b->payment_status === 'Partially Paid')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            Due: ₹{{ number_format($balance, 2) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-50 text-red-700 border border-red-200">
                                            Unpaid
                                        </span>
                                    @endif
                                </div>
                                <div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $b->status === 'Confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : ($b->status === 'Partially Cancelled' ? 'bg-orange-50 text-orange-700 border border-orange-200' : 'bg-gray-100 text-gray-600 border border-gray-300') }}">
                                        {{ $b->status }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col gap-1.5">
                                <button wire:click="viewDetails({{ $b->id }})" type="button" 
                                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs hover:shadow-md cursor-pointer">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    View Details
                                </button>

                                @if ($balance > 0 && $b->status !== 'Cancelled')
                                    @php $firstUnpaidDate = $b->bookingDates->firstWhere('payment_status', '!=', 'Paid'); @endphp
                                    @if ($firstUnpaidDate)
                                        <button wire:click="openPaymentModal({{ $firstUnpaidDate->id }})" type="button" 
                                            class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition text-center shadow-2xs">
                                            + Pay
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            @empty
                <div class="bg-white p-12 rounded-2xl border border-gray-200 text-center text-gray-500 space-y-2">
                    <span class="text-3xl block">📭</span>
                    @if (empty($manageableTurfIds))
                        <p class="font-bold text-gray-700">You don't have any turfs or bookings yet.</p>
                        <a href="{{ route('turf.turfs') }}" class="inline-block text-xs font-bold text-indigo-600 hover:underline">Add your first turf</a>
                    @else
                        <p class="font-bold text-gray-700">No bookings found matching your search or filters.</p>
                        <button wire:click="clearFilters" class="text-xs font-bold text-indigo-600 hover:underline">Clear all filters</button>
                    @endif
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        <div class="mt-4">
            {{ $bookings->links() }}
        </div>

    </div>

    <!-- DETAILS DRAWER MODAL -->
    @if ($showDetailModal && $selectedBookingId)
        @php
            $bDetail = Booking::with(['turf.location', 'user', 'bookingDates.bookingSlots.slot.category', 'payments'])->find($selectedBookingId);
        @endphp
        @if ($bDetail)
            @php
                $dActiveDates = $bDetail->bookingDates->where('status', '!=', 'Cancelled');
                $dTotalAmount = (float)($bDetail->total_amount > 0 ? $bDetail->total_amount : $dActiveDates->sum('amount'));
                $dPaidSum = (float)$bDetail->payments->where('status', 'Success')->sum('amount');
                $dBalance = max(0.00, $dTotalAmount - $dPaidSum);

                // Collect distinct payment methods used
                $paymentMethods = $bDetail->payments->where('status', 'Success')->pluck('payment_method')->unique()->filter()->values();
                if ($paymentMethods->isEmpty()) {
                    $paymentMethods = collect(['Unpaid / Pending']);
                }
            @endphp
            <div class="fixed inset-0 z-50 overflow-hidden bg-gray-900/60 backdrop-blur-xs flex justify-end transition"
                 wire:click.self="closeDetails">
                <div class="w-full max-w-2xl bg-white h-screen max-h-screen flex flex-col shadow-2xl border-l border-gray-200 overflow-hidden">
                    
                    <!-- 1. Drawer Header & Booking ID (Sticky Top) -->
                    <div class="p-5 sm:p-6 border-b border-gray-200 bg-white flex items-center justify-between shrink-0 shadow-2xs">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-md bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-100 uppercase tracking-wider">
                                    Booking Ledger
                                </span>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $bDetail->status === 'Confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : ($bDetail->status === 'Partially Cancelled' ? 'bg-orange-50 text-orange-700 border border-orange-200' : 'bg-gray-100 text-gray-700 border border-gray-300') }}">
                                    {{ $bDetail->status }}
                                </span>
                            </div>
                            <h2 class="text-2xl font-black text-gray-900 mt-1">
                                {{ $bDetail->booking_reference ?? ('#' . $bDetail->id) }}
                            </h2>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Booked on {{ $bDetail->created_at ? $bDetail->created_at->format('d M Y, h:i A') : 'N/A' }} • ID #{{ $bDetail->id }}
                            </p>
                        </div>
                        <button wire:click="closeDetails" class="p-2 text-gray-400 hover:text-gray-600 rounded-xl hover:bg-gray-100 transition cursor-pointer">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- 2. Scrollable Body Content -->
                    <div class="flex-1 overflow-y-auto p-5 sm:p-6 space-y-6">

                        <!-- 2. Customer & Contact Details + Turf & Location -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Customer Details -->
                            <div class="p-4 rounded-2xl bg-gray-50/80 border border-gray-200/80 space-y-1.5">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Customer & Contact</span>
                                <div class="font-black text-sm text-gray-900">
                                    {{ $bDetail->user?->name ?? 'Guest User' }}
                                </div>
                                <div class="text-xs font-semibold text-gray-700 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                    <span>{{ $bDetail->user?->mobile ?? 'No contact mobile' }}</span>
                                </div>
                                @if ($bDetail->user?->email)
                                    <div class="text-xs text-gray-500 truncate" title="{{ $bDetail->user->email }}">
                                        ✉️ {{ $bDetail->user->email }}
                                    </div>
                                @endif
                                @if ($bDetail->customer_gstin || $bDetail->customer_company_name)
                                    <div class="pt-2 border-t border-gray-200 text-xs text-indigo-700 font-semibold">
                                        🏢 {{ $bDetail->customer_company_name ?? 'B2B Client' }}
                                        <div class="text-[11px] text-gray-500">GSTIN: {{ $bDetail->customer_gstin ?? 'N/A' }}</div>
                                    </div>
                                @endif
                            </div>

                            <!-- Turf & Location -->
                            <div class="p-4 rounded-2xl bg-gray-50/80 border border-gray-200/80 space-y-1.5">
                                <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 block">Turf & Location</span>
                                <div class="font-black text-sm text-gray-900">
                                    {{ $bDetail->turf?->name }}
                                </div>
                                <div class="text-xs font-semibold text-gray-600 flex items-center gap-1">
                                    <span>📍 {{ $bDetail->turf?->location?->name ?? 'Main Location' }}</span>
                                </div>
                                <div class="pt-1.5 flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100 uppercase">
                                        {{ ucfirst($bDetail->booking_type ?? 'day') }} Session
                                    </span>
                                    @if ($bDetail->turf?->type)
                                        <span class="text-xs text-gray-500">({{ $bDetail->turf->type }})</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- 3. Paid Amount & Balance Amount Breakup + Mode of Payment -->
                        <div class="p-4 rounded-2xl bg-gradient-to-br from-gray-50 to-indigo-50/20 border border-gray-200 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">Payment & Balance Breakup</span>
                                <div class="flex items-center gap-1.5">
                                    <span class="text-[11px] text-gray-400">Mode:</span>
                                    @foreach ($paymentMethods as $pm)
                                        <span class="px-2 py-0.5 rounded bg-white text-gray-800 border border-gray-200 text-xs font-bold shadow-2xs">
                                            {{ $pm }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3 text-center">
                                <div class="p-3 rounded-xl bg-white border border-gray-200 shadow-2xs">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total Booking</span>
                                    <p class="text-base font-black text-gray-900 mt-0.5">₹{{ number_format($dTotalAmount, 2) }}</p>
                                </div>
                                <div class="p-3 rounded-xl bg-white border border-gray-200 shadow-2xs">
                                    <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Paid</span>
                                    <p class="text-base font-black text-emerald-600 mt-0.5">₹{{ number_format($dPaidSum, 2) }}</p>
                                </div>
                                <div class="p-3 rounded-xl bg-white border border-gray-200 shadow-2xs">
                                    <span class="text-[10px] font-bold {{ $dBalance > 0 ? 'text-amber-600' : 'text-gray-400' }} uppercase tracking-wider">Balance Due</span>
                                    <p class="text-base font-black {{ $dBalance > 0 ? 'text-amber-600' : 'text-gray-400' }} mt-0.5">₹{{ number_format($dBalance, 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Booking Slots Datewise List with Timing (Consecutive First & Last from - to) -->
                        <div class="space-y-2.5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-600 flex items-center gap-1.5">
                                    <span>📅 Booked Dates & Slot Timings</span>
                                    <span class="text-gray-400">({{ $bDetail->bookingDates->count() }} {{ \Illuminate\Support\Str::plural('date', $bDetail->bookingDates->count()) }})</span>
                                </h3>
                            </div>

                            <div class="space-y-2 max-h-56 overflow-y-auto pr-1">
                                @foreach ($bDetail->bookingDates as $dIndex => $bd)
                                    @php
                                        $bdDateCarbon = Carbon::parse($bd->booking_date);
                                        $bdPaidSum = (float) Payment::where('booking_date_id', $bd->id)->where('status', 'Success')->sum('amount');
                                        $bdBalance = max(0.00, (float)$bd->amount - $bdPaidSum);
                                        $dateRanges = $this->formatConsecutiveSlots($bd->bookingSlots);
                                    @endphp
                                    <div class="p-3.5 rounded-xl border border-gray-200 bg-white hover:border-indigo-200 transition shadow-2xs space-y-2">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <span class="w-6 h-6 rounded-md bg-amber-100 text-amber-800 text-xs font-black flex items-center justify-center">
                                                    {{ $dIndex + 1 }}
                                                </span>
                                                <div>
                                                    <span class="font-extrabold text-xs text-gray-900">
                                                        📅 {{ $bdDateCarbon->format('d M Y') }} ({{ $bdDateCarbon->format('l') }})
                                                    </span>
                                                    <span class="text-[10px] text-gray-400 ml-1">ID #{{ $bd->id }}</span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="font-black text-xs text-gray-900">₹{{ number_format($bd->amount, 2) }}</span>
                                                <span class="ml-1.5 inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold {{ $bd->payment_status === 'Paid' ? 'bg-emerald-50 text-emerald-700' : ($bd->payment_status === 'Partially Paid' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">
                                                    {{ $bd->payment_status }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Consecutive Timings (from - to) -->
                                        <div class="pt-1.5 border-t border-gray-100 flex flex-wrap items-center gap-2">
                                            <span class="text-[10px] font-bold uppercase text-gray-400">Timing:</span>
                                            @if (!empty($dateRanges))
                                                @foreach ($dateRanges as $range)
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-teal-50 border border-teal-200 text-teal-900 text-xs font-black">
                                                        ⏰ {{ $range['from'] }} - {{ $range['to'] }}
                                                        @if ($range['slots_count'] > 1)
                                                            <span class="text-[10px] text-teal-700 font-bold">({{ $range['slots_count'] }} slots)</span>
                                                        @endif
                                                    </span>
                                                @endforeach
                                            @else
                                                <span class="text-xs text-gray-400 italic">No slot timing recorded</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- 5. Complete Financial Breakup Sections -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-600">Complete Financial Breakup</h3>
                            
                            <!-- Financial Table Matrix -->
                            <div class="rounded-2xl border border-gray-200 overflow-hidden text-xs divide-y divide-gray-100 bg-white">
                                
                                <!-- Base Taxable & Discounts -->
                                <div class="p-3 bg-gray-50/50 flex items-center justify-between">
                                    <span class="text-gray-600 font-medium">Taxable Base Amount:</span>
                                    <span class="font-extrabold text-gray-900">₹{{ number_format($bDetail->taxable_amount ?? 0, 2) }}</span>
                                </div>

                                @if ((float)$bDetail->coupon_discount > 0 || (float)$bDetail->additional_discount > 0)
                                    <div class="p-3 bg-emerald-50/40 flex items-center justify-between text-emerald-800">
                                        <span>Coupon / Additional Discounts:</span>
                                        <span class="font-extrabold">-₹{{ number_format(((float)$bDetail->coupon_discount + (float)$bDetail->additional_discount), 2) }}</span>
                                    </div>
                                @endif

                                <!-- GST Breakup -->
                                <div class="p-3 space-y-1.5 bg-white">
                                    <div class="flex items-center justify-between font-bold text-gray-800">
                                        <span>GST Breakup (Rate: {{ (float)$bDetail->turf_gst_rate }}%):</span>
                                        <span>₹{{ number_format($bDetail->turf_gst_amount ?? 0, 2) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-500 pl-2">
                                        <div class="flex justify-between">
                                            <span>CGST:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->turf_cgst_amount ?? 0, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>SGST:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->turf_sgst_amount ?? 0, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Platform Fee Breakup -->
                                <div class="p-3 space-y-1.5 bg-white">
                                    <div class="flex items-center justify-between font-bold text-gray-800">
                                        <span>Platform Fee with Breakup:</span>
                                        <span>₹{{ number_format(((float)$bDetail->platform_fee + (float)$bDetail->platform_fee_gst), 2) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-500 pl-2">
                                        <div class="flex justify-between">
                                            <span>Base Fee:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->platform_fee ?? 0, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Fee GST:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->platform_fee_gst ?? 0, 2) }}</span>
                                        </div>
                                        @if ((float)$bDetail->platform_fee_cgst > 0)
                                            <div class="flex justify-between">
                                                <span>Fee CGST:</span>
                                                <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->platform_fee_cgst, 2) }}</span>
                                            </div>
                                        @endif
                                        @if ((float)$bDetail->platform_fee_sgst > 0)
                                            <div class="flex justify-between">
                                                <span>Fee SGST:</span>
                                                <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->platform_fee_sgst, 2) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Platform Commission Breakup -->
                                <div class="p-3 space-y-1.5 bg-white">
                                    <div class="flex items-center justify-between font-bold text-gray-800">
                                        <span>Platform Commission Breakup (Rate: {{ (float)$bDetail->commission_rate }}%):</span>
                                        <span>₹{{ number_format(((float)$bDetail->commission_amount + (float)$bDetail->commission_gst_amount), 2) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-500 pl-2">
                                        <div class="flex justify-between">
                                            <span>Commission Base:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->commission_amount ?? 0, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Commission GST:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->commission_gst_amount ?? 0, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between col-span-2 pt-1 border-t border-gray-100 text-indigo-700 font-bold">
                                            <span>Estimated Turf Payout:</span>
                                            <span>₹{{ number_format($bDetail->turf_payout_amount ?? 0, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Gateway Charges Breakup -->
                                <div class="p-3 space-y-1.5 bg-white">
                                    <div class="flex items-center justify-between font-bold text-gray-800">
                                        <span>Payment Gateway Charges Breakup:</span>
                                        <span>₹{{ number_format(((float)$bDetail->gateway_charge_amount + (float)$bDetail->gateway_tax_amount), 2) }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-500 pl-2">
                                        <div class="flex justify-between">
                                            <span>Gateway Fee:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->gateway_charge_amount ?? 0, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Gateway Tax:</span>
                                            <span class="font-semibold text-gray-700">₹{{ number_format($bDetail->gateway_tax_amount ?? 0, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cancellation & Refund Breakup -->
                                <div class="p-3 space-y-1.5 {{ $bDetail->status === 'Cancelled' || $bDetail->status === 'Partially Cancelled' ? 'bg-red-50/50' : 'bg-white' }}">
                                    <div class="flex items-center justify-between font-bold {{ $bDetail->status === 'Cancelled' || $bDetail->status === 'Partially Cancelled' ? 'text-red-900' : 'text-gray-800' }}">
                                        <span>Cancellation & Refund Breakup:</span>
                                        <span>{{ $bDetail->refund_status ?? 'Not Applicable' }}</span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-[11px] text-gray-600 pl-2">
                                        <div class="flex justify-between">
                                            <span>Cancellation Fee Applied:</span>
                                            <span class="font-semibold text-gray-800">₹{{ number_format($bDetail->cancellation_fee_applied ?? 0, 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span>Refund Amount:</span>
                                            <span class="font-semibold text-purple-700">₹{{ number_format($bDetail->refund_amount ?? 0, 2) }}</span>
                                        </div>
                                        @if ($bDetail->cancelled_at)
                                            <div class="flex justify-between col-span-2 text-gray-400 text-[10px]">
                                                <span>Cancelled On:</span>
                                                <span>{{ Carbon::parse($bDetail->cancelled_at)->format('d M Y, h:i A') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Grand Total Row -->
                                <div class="p-3.5 bg-gray-900 text-white flex items-center justify-between font-black text-sm">
                                    <span>Grand Total Amount:</span>
                                    <span>₹{{ number_format($dTotalAmount, 2) }}</span>
                                </div>

                            </div>
                        </div>

                        <!-- 6. Payments History Ledger -->
                        <div class="space-y-2.5">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-600">Payments Audit Log</h3>
                            <div class="space-y-2">
                                @forelse ($bDetail->payments as $pay)
                                    <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-gray-900">₹{{ number_format($pay->amount, 2) }}</span>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-gray-200 text-gray-800">
                                                {{ $pay->payment_method }}
                                            </span>
                                            <span class="text-[10px] font-semibold text-emerald-700">({{ $pay->status }})</span>
                                        </div>
                                        <div class="text-gray-400 text-[11px]">
                                            {{ $pay->paid_at ? Carbon::parse($pay->paid_at)->format('d M Y, h:i A') : '' }}
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400 italic">No payments recorded yet.</p>
                                @endforelse
                            </div>
                        </div>

                    </div>

                    <!-- 3. Drawer Footer Actions (Sticky Bottom) -->
                    <div class="p-4 sm:p-5 border-t border-gray-200 bg-gray-50/90 shrink-0 flex items-center gap-3">
                        @if ($dBalance > 0 && $bDetail->status !== 'Cancelled')
                            @php $firstUnpaidDate = $bDetail->bookingDates->firstWhere('payment_status', '!=', 'Paid'); @endphp
                            @if ($firstUnpaidDate)
                                <button wire:click="openPaymentModal({{ $firstUnpaidDate->id }})" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 shadow-xs cursor-pointer">
                                    + Record Payment
                                </button>
                            @endif
                        @endif

                        @if ($bDetail->status !== 'Cancelled')
                            <button wire:click="openCancelModal({{ $bDetail->id }})" class="py-2.5 px-4 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-xs font-bold transition cursor-pointer">
                                Cancel Booking
                            </button>
                        @endif

                        <button wire:click="closeDetails" class="py-2.5 px-5 bg-white border border-gray-300 hover:bg-gray-100 text-gray-700 rounded-xl text-xs font-bold transition cursor-pointer shadow-2xs">
                            Close
                        </button>
                    </div>

                </div>
            </div>
        @endif
    @endif

    <!-- RECORD PAYMENT MODAL -->
    @if ($showPaymentModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="w-full max-w-md bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-gray-200">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="font-black text-lg text-gray-900">Record Offline Payment</h3>
                    <button wire:click="closePaymentModal" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Payment Method</label>
                        <select wire:model="paymentMethod" class="w-full p-2.5 text-xs rounded-xl border border-gray-300">
                            <option value="Cash">Cash Payment</option>
                            <option value="UPI">UPI Payment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Payment Amount (₹)</label>
                        <input type="number" step="0.01" wire:model="paymentAmount" class="w-full p-2.5 text-xs rounded-xl border border-gray-300 font-bold text-emerald-600">
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 flex justify-end gap-2">
                    <button wire:click="closePaymentModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-xs font-bold">Cancel</button>
                    <button wire:click="submitPayment" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs">
                        Confirm & Record Payment
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- CANCEL BOOKING MODAL -->
    @if ($showCancelModal && $cancelBookingId)
        @php
            $cBooking = Booking::with('bookingDates')->find($cancelBookingId);
        @endphp
        @if ($cBooking)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex items-center justify-center p-4">
                <div class="w-full max-w-md bg-white rounded-2xl p-6 shadow-2xl space-y-4 border border-gray-200">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                        <h3 class="font-black text-lg text-red-600">Cancel Booking Dates</h3>
                        <button wire:click="closeCancelModal" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>

                    <p class="text-xs text-gray-600">
                        Select the session dates you wish to cancel for booking <strong class="text-gray-900">{{ $cBooking->booking_reference ?? ('#' . $cBooking->id) }}</strong>:
                    </p>

                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        @foreach ($cBooking->bookingDates as $bd)
                            <label class="flex items-center justify-between p-3 rounded-xl border border-gray-200 cursor-pointer text-xs">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" value="{{ $bd->id }}" wire:model.live="cancelDateIds" 
                                        {{ $bd->status === 'Cancelled' ? 'disabled' : '' }} 
                                        class="rounded text-red-600 focus:ring-red-500">
                                    <span class="font-bold text-gray-900">{{ $bd->booking_date }}</span>
                                </div>
                                <span class="font-bold {{ $bd->status === 'Cancelled' ? 'text-gray-400' : 'text-emerald-600' }}">
                                    {{ $bd->status === 'Cancelled' ? 'Already Cancelled' : ('₹' . number_format($bd->amount, 2)) }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="pt-3 border-t border-gray-100 flex justify-end gap-2">
                        <button wire:click="closeCancelModal" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl text-xs font-bold">Keep Booking</button>
                        <button wire:click="submitCancellation" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-xs">
                            Confirm Cancellation
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
