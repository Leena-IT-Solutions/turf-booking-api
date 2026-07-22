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
    public string $viewMode = 'table'; // 'table' or 'grid'
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
        $bDate = BookingDate::with('booking')->find($bookingDateId);
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

        $booking = Booking::find($this->paymentBookingId);
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
        $booking = Booking::with('bookingDates')->find($bookingId);
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

        $booking = Booking::with(['turf', 'bookingDates.bookingSlots'])->find($this->cancelBookingId);
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

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Top Bar / Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2.5">
                    <svg class="w-8 h-8 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Bookings Management
                </h1>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Track, search, filter, and manage all customer slot bookings and offline payments in real-time.
                </p>
            </div>

            <!-- Header Action Controls -->
            <div class="flex items-center gap-2">
                <!-- View Mode Toggle -->
                <div class="bg-gray-100 dark:bg-gray-800 p-1 rounded-xl flex items-center border border-gray-200 dark:border-gray-700">
                    <button wire:click="$set('viewMode', 'table')" type="button" 
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $viewMode === 'table' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-300 shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        Table
                    </button>
                    <button wire:click="$set('viewMode', 'grid')" type="button" 
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $viewMode === 'grid' ? 'bg-white dark:bg-gray-700 text-indigo-600 dark:text-indigo-300 shadow-xs' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Cards
                    </button>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('status'))
            <div class="p-4 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="p-4 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 text-sm flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @php
            $activeTurfId = session('active_turf_id');
            $turf = $activeTurfId ? Turf::manageable()->find($activeTurfId) : null;

            // Base query for statistics & listing
            $baseQuery = Booking::with(['turf', 'user', 'bookingDates.bookingSlots.slot', 'payments']);
            if ($activeTurfId) {
                $baseQuery->where('turf_id', $activeTurfId);
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
                 class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-xs cursor-pointer hover:border-indigo-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">All Bookings</span>
                    <span class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-lg text-xs font-bold">📋</span>
                </div>
                <p class="text-2xl font-black text-gray-900 dark:text-white mt-2">{{ number_format($statsAll) }}</p>
            </div>

            <div wire:click="$set('statusFilter', 'Confirmed')" 
                 class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-xs cursor-pointer hover:border-blue-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-blue-600 dark:text-blue-400">Confirmed</span>
                    <span class="p-1.5 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-lg text-xs font-bold">✅</span>
                </div>
                <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-2">{{ number_format($statsConfirmed) }}</p>
            </div>

            <div wire:click="$set('paymentStatusFilter', 'Partially Paid')" 
                 class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-xs cursor-pointer hover:border-amber-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-amber-600 dark:text-amber-400">Partially Paid</span>
                    <span class="p-1.5 bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 rounded-lg text-xs font-bold">⏳</span>
                </div>
                <p class="text-2xl font-black text-amber-600 dark:text-amber-400 mt-2">{{ number_format($statsPartPaid) }}</p>
            </div>

            <div wire:click="$set('paymentStatusFilter', 'Unpaid')" 
                 class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-xs cursor-pointer hover:border-red-400 transition">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-red-600 dark:text-red-400">Unpaid</span>
                    <span class="p-1.5 bg-red-50 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg text-xs font-bold">⚠️</span>
                </div>
                <p class="text-2xl font-black text-red-600 dark:text-red-400 mt-2">{{ number_format($statsUnpaid) }}</p>
            </div>

            <div wire:click="$set('statusFilter', 'Cancelled')" 
                 class="bg-white dark:bg-gray-800 p-4 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-xs cursor-pointer hover:border-gray-400 transition col-span-2 sm:col-span-1">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Cancelled</span>
                    <span class="p-1.5 bg-gray-100 dark:bg-gray-700 text-gray-500 rounded-lg text-xs font-bold">🚫</span>
                </div>
                <p class="text-2xl font-black text-gray-500 dark:text-gray-400 mt-2">{{ number_format($statsCancelled) }}</p>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xs border border-gray-200 dark:border-gray-700/80 p-5 space-y-4">
            
            <!-- Quick Date Filter Preset Chips -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-700/80 pb-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1 max-w-full">
                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 me-1">Dates:</span>
                    <button wire:click="setQuickPreset('all')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'all' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                        All Time
                    </button>
                    <button wire:click="setQuickPreset('today')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'today' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                        Today
                    </button>
                    <button wire:click="setQuickPreset('tomorrow')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'tomorrow' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                        Tomorrow
                    </button>
                    <button wire:click="setQuickPreset('week')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'week' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                        This Week
                    </button>
                    <button wire:click="setQuickPreset('month')" type="button" 
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition {{ $datePreset === 'month' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-300 hover:bg-gray-200' }}">
                        This Month
                    </button>
                </div>

                <!-- Clear Filters Button -->
                @if ($search !== '' || $statusFilter !== 'all' || $paymentStatusFilter !== 'all' || $bookingTypeFilter !== 'all' || $datePreset !== 'all')
                    <button wire:click="clearFilters" type="button" 
                        class="text-xs font-semibold text-red-600 dark:text-red-400 hover:underline flex items-center gap-1 shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Reset All Filters
                    </button>
                @endif
            </div>

            <!-- Search & Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                <!-- Search Input -->
                <div class="lg:col-span-2 relative">
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Search Customer / Ref</label>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Name, Mobile, Ref #..." 
                            class="w-full pl-9 pr-4 py-2 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- Booking Status -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Booking Status</label>
                    <select wire:model.live="statusFilter" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Statuses</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Partially Cancelled">Partially Cancelled</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Payment Status</label>
                    <select wire:model.live="paymentStatusFilter" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Payments</option>
                        <option value="Paid">Paid (Full)</option>
                        <option value="Partially Paid">Partially Paid</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>

                <!-- Booking Type -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Booking Type</label>
                    <select wire:model.live="bookingTypeFilter" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Types</option>
                        <option value="day">Single Day</option>
                        <option value="long">Long (Multi-Date)</option>
                        <option value="scattered">Scattered</option>
                    </select>
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-1">Sort By</label>
                    <select wire:model.live="sortBy" class="w-full py-2 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="date_asc">Session Date (Earliest)</option>
                        <option value="date_desc">Session Date (Latest)</option>
                    </select>
                </div>
            </div>

            <!-- Custom Date Range Row -->
            @if ($datePreset === 'custom')
                <div class="pt-3 border-t border-gray-100 dark:border-gray-700/80 grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-md">
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1">From Date</label>
                        <input type="date" wire:model.live="startDate" class="w-full py-1.5 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 mb-1">To Date</label>
                        <input type="date" wire:model.live="endDate" class="w-full py-1.5 px-3 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                    </div>
                </div>
            @endif
        </div>

        <!-- Bookings Main Content (Table or Grid View) -->
        @if ($viewMode === 'table')
            <!-- TABLE VIEW -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xs border border-gray-200 dark:border-gray-700/80 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-500 dark:text-gray-400 uppercase tracking-wider font-bold border-b border-gray-100 dark:border-gray-700/80">
                            <tr>
                                <th class="px-4 py-3.5">Ref / Customer</th>
                                <th class="px-4 py-3.5">Turf</th>
                                <th class="px-4 py-3.5">Type & Dates</th>
                                <th class="px-4 py-3.5">Time Slots</th>
                                <th class="px-4 py-3.5">Amount & Paid</th>
                                <th class="px-4 py-3.5">Payment Status</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-4 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-300">
                            @forelse ($bookings as $b)
                                @php
                                    $activeDates = $b->bookingDates->where('status', '!=', 'Cancelled');
                                    $totalAmount = (float)$activeDates->sum('amount');
                                    $paidSum = (float)$b->payments->where('status', 'Success')->sum('amount');
                                    $balance = max(0.00, $totalAmount - $paidSum);

                                    // Time slots label summary
                                    $slotLabels = [];
                                    foreach ($b->bookingDates as $bd) {
                                        foreach ($bd->bookingSlots as $bs) {
                                            if ($bs->slot) {
                                                $from = date('h:i A', strtotime($bs->slot->from_time));
                                                $to = date('h:i A', strtotime($bs->slot->to_time));
                                                $slotLabels[] = "$from - $to";
                                            }
                                        }
                                    }
                                    $firstSlot = $slotLabels[0] ?? '';
                                    $lastSlot = count($slotLabels) > 1 ? end($slotLabels) : '';
                                    $timeRangeStr = (count($slotLabels) > 1 && $firstSlot && $lastSlot)
                                        ? (explode(' - ', $firstSlot)[0] . ' - ' . explode(' - ', $lastSlot)[1])
                                        : ($firstSlot ?: 'N/A');
                                @endphp

                                <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-700/40 transition">
                                    <!-- Customer Info -->
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-gray-900 dark:text-white">
                                            {{ $b->booking_reference ?? ('#' . $b->id) }}
                                        </div>
                                        <div class="text-xs font-semibold text-gray-700 dark:text-gray-300 mt-0.5">
                                            {{ $b->user?->name ?? 'Manual / Guest' }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                            {{ $b->user?->mobile ?? $b->user?->email ?? '' }}
                                        </div>
                                    </td>

                                    <!-- Turf -->
                                    <td class="px-4 py-3.5">
                                        <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $b->turf?->name ?? 'N/A' }}</span>
                                    </td>

                                    <!-- Type & Dates -->
                                    <td class="px-4 py-3.5">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 uppercase">
                                            {{ $b->booking_type ?? 'day' }}
                                        </span>
                                        <div class="text-xs font-bold text-gray-900 dark:text-gray-100 mt-1">
                                            {{ $b->bookingDates->pluck('booking_date')->implode(', ') }}
                                        </div>
                                    </td>

                                    <!-- Time Slots -->
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-gray-900 dark:text-gray-100">
                                            ⏱️ {{ $timeRangeStr }}
                                        </div>
                                        <div class="text-[10px] text-gray-400 mt-0.5">
                                            {{ count($slotLabels) }} slot(s) total
                                        </div>
                                    </td>

                                    <!-- Amount & Paid -->
                                    <td class="px-4 py-3.5">
                                        <div class="font-bold text-gray-900 dark:text-white">₹{{ number_format($totalAmount, 2) }}</div>
                                        <div class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">Paid: ₹{{ number_format($paidSum, 2) }}</div>
                                        @if ($balance > 0)
                                            <div class="text-[11px] font-bold text-amber-600 dark:text-amber-400">Bal: ₹{{ number_format($balance, 2) }}</div>
                                        @endif
                                    </td>

                                    <!-- Payment Status -->
                                    <td class="px-4 py-3.5">
                                        @if ($b->payment_status === 'Paid')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-emerald-100 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                                                Paid
                                            </span>
                                        @elseif ($b->payment_status === 'Partially Paid')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-amber-100 dark:bg-amber-950/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
                                                Partially Paid
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-red-100 dark:bg-red-950/60 text-red-800 dark:text-red-300 border border-red-300 dark:border-red-700">
                                                Unpaid
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Booking Status -->
                                    <td class="px-4 py-3.5">
                                        @if ($b->status === 'Confirmed')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 border border-blue-300 dark:border-blue-700">
                                                Confirmed
                                            </span>
                                        @elseif ($b->status === 'Partially Cancelled')
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-orange-100 dark:bg-orange-950/60 text-orange-800 dark:text-orange-300 border border-orange-300 dark:border-orange-700">
                                                Partially Cancelled
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600">
                                                Cancelled
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Actions -->
                                    <td class="px-4 py-3.5 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- View Details Button -->
                                            <button wire:click="viewDetails({{ $b->id }})" type="button" 
                                                class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-[11px] font-bold transition flex items-center gap-1">
                                                <span>Details</span>
                                            </button>

                                            <!-- Record Payment Button -->
                                            @if ($balance > 0 && $b->status !== 'Cancelled')
                                                @php $firstUnpaidDate = $b->bookingDates->firstWhere('payment_status', '!=', 'Paid'); @endphp
                                                @if ($firstUnpaidDate)
                                                    <button wire:click="openPaymentModal({{ $firstUnpaidDate->id }})" type="button" 
                                                        class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold transition flex items-center gap-1 shadow-xs">
                                                        <span>+ Pay</span>
                                                    </button>
                                                @endif
                                            @endif

                                            <!-- Cancel Button -->
                                            @if ($b->status !== 'Cancelled')
                                                <button wire:click="openCancelModal({{ $b->id }})" type="button" 
                                                    class="p-1 bg-red-50 hover:bg-red-100 dark:bg-red-950/40 text-red-600 dark:text-red-400 rounded-lg text-[11px] font-bold transition">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="max-w-xs mx-auto space-y-2">
                                            <span class="text-3xl">🔍</span>
                                            <p class="font-bold text-gray-700 dark:text-gray-300">No bookings matched your search or filters.</p>
                                            <button wire:click="clearFilters" class="text-xs font-bold text-indigo-600 dark:text-indigo-400 hover:underline">Clear all filters</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 border-t border-gray-100 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/30">
                    {{ $bookings->links() }}
                </div>
            </div>

        @else
            <!-- GRID / CARD VIEW -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($bookings as $b)
                    @php
                        $activeDates = $b->bookingDates->where('status', '!=', 'Cancelled');
                        $totalAmount = (float)$activeDates->sum('amount');
                        $paidSum = (float)$b->payments->where('status', 'Success')->sum('amount');
                        $balance = max(0.00, $totalAmount - $paidSum);
                        $progress = $totalAmount > 0 ? min(100, round(($paidSum / $totalAmount) * 100)) : 0;
                    @endphp

                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700/80 p-5 shadow-xs flex flex-col justify-between space-y-4 hover:border-indigo-400 transition">
                        <div>
                            <!-- Card Header -->
                            <div class="flex items-start justify-between gap-2 border-b border-gray-100 dark:border-gray-700/80 pb-3">
                                <div>
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">{{ $b->booking_reference ?? ('#' . $b->id) }}</span>
                                    <h3 class="font-extrabold text-sm text-gray-900 dark:text-white mt-0.5">{{ $b->user?->name ?? 'Manual / Guest' }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $b->user?->mobile ?? $b->user?->email ?? '' }}</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $b->status === 'Confirmed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300' }}">
                                    {{ $b->status }}
                                </span>
                            </div>

                            <!-- Dates & Turf -->
                            <div class="mt-3 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Turf:</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200">{{ $b->turf?->name }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Session Dates:</span>
                                    <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $b->bookingDates->pluck('booking_date')->implode(', ') }}</span>
                                </div>
                            </div>

                            <!-- Payment Progress Bar -->
                            <div class="mt-4 space-y-1">
                                <div class="flex items-center justify-between text-xs font-bold">
                                    <span class="text-gray-700 dark:text-gray-300">₹{{ number_format($paidSum, 2) }} paid</span>
                                    <span class="text-gray-500 dark:text-gray-400">Total: ₹{{ number_format($totalAmount, 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 h-2 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Footer -->
                        <div class="pt-3 border-t border-gray-100 dark:border-gray-700/80 flex items-center justify-between gap-2">
                            <button wire:click="viewDetails({{ $b->id }})" type="button" 
                                class="flex-1 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-xl text-xs font-bold transition text-center">
                                View Details
                            </button>

                            @if ($balance > 0 && $b->status !== 'Cancelled')
                                @php $firstUnpaidDate = $b->bookingDates->firstWhere('payment_status', '!=', 'Paid'); @endphp
                                @if ($firstUnpaidDate)
                                    <button wire:click="openPaymentModal({{ $firstUnpaidDate->id }})" type="button" 
                                        class="flex-1 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition text-center shadow-xs">
                                        + Record Pay
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full bg-white dark:bg-gray-800 p-12 rounded-2xl border border-gray-200 dark:border-gray-700/80 text-center text-gray-500 dark:text-gray-400">
                        No bookings found matching your search.
                    </div>
                @endforelse
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        @endif

    </div>

    <!-- DETAILS DRAWER MODAL -->
    @if ($showDetailModal && $selectedBookingId)
        @php
            $bDetail = Booking::with(['turf', 'user', 'bookingDates.bookingSlots.slot', 'payments'])->find($selectedBookingId);
        @endphp
        @if ($bDetail)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-xs flex justify-end transition">
                <div class="w-full max-w-xl bg-white dark:bg-gray-800 min-h-screen p-6 shadow-2xl flex flex-col justify-between border-l border-gray-200 dark:border-gray-700">
                    <div class="space-y-6">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700">
                            <div>
                                <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Booking Ledger</span>
                                <h2 class="text-xl font-black text-gray-900 dark:text-white">{{ $bDetail->booking_reference ?? ('#' . $bDetail->id) }}</h2>
                            </div>
                            <button wire:click="closeDetails" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Customer & Turf Overview -->
                        <div class="grid grid-cols-2 gap-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700/80">
                            <div>
                                <span class="text-[10px] font-bold uppercase text-gray-400">Customer Info</span>
                                <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ $bDetail->user?->name ?? 'Guest User' }}</p>
                                <p class="text-xs text-gray-500">{{ $bDetail->user?->mobile ?? 'No Mobile' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $bDetail->user?->email ?? '' }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-gray-400">Turf & Session Type</span>
                                <p class="text-sm font-bold text-gray-900 dark:text-white mt-0.5">{{ $bDetail->turf?->name }}</p>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100 dark:bg-indigo-950 text-indigo-800 dark:text-indigo-300">
                                    {{ ucfirst($bDetail->booking_type) }} Session
                                </span>
                            </div>
                        </div>

                        <!-- Dates & Slots Timeline -->
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Booked Dates & Slots</h3>
                            <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                @foreach ($bDetail->bookingDates as $bd)
                                    @php
                                        $bdPaidSum = (float) Payment::where('booking_date_id', $bd->id)->where('status', 'Success')->sum('amount');
                                    @endphp
                                    <div class="p-3 rounded-xl border border-gray-100 dark:border-gray-700/80 bg-white dark:bg-gray-800 flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-xs text-gray-900 dark:text-white">📅 {{ $bd->booking_date }}</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">
                                                Slots: 
                                                @foreach ($bd->bookingSlots as $bs)
                                                    @if ($bs->slot)
                                                        {{ date('h:i A', strtotime($bs->slot->from_time)) }} - {{ date('h:i A', strtotime($bs->slot->to_time)) }}@if (!$loop->last), @endif
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="font-bold text-xs text-gray-900 dark:text-white">₹{{ number_format($bd->amount, 2) }}</div>
                                            <span class="text-[10px] font-bold {{ $bd->payment_status === 'Paid' ? 'text-emerald-600' : ($bd->payment_status === 'Partially Paid' ? 'text-amber-600' : 'text-red-600') }}">
                                                {{ $bd->payment_status }} (Paid: ₹{{ number_format($bdPaidSum, 2) }})
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- Payments Ledger -->
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-3">Payments History</h3>
                            <div class="space-y-2">
                                @forelse ($bDetail->payments as $pay)
                                    <div class="p-3 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-100 dark:border-gray-700/80 flex items-center justify-between text-xs">
                                        <div>
                                            <span class="font-bold text-gray-900 dark:text-white">₹{{ number_format($pay->amount, 2) }}</span>
                                            <span class="ml-2 text-[10px] font-semibold px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                {{ $pay->payment_method }}
                                            </span>
                                        </div>
                                        <div class="text-gray-400 text-[10px]">
                                            {{ $pay->paid_at ? Carbon::parse($pay->paid_at)->format('d M, h:i A') : '' }}
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-400">No payment records found.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Footer Actions -->
                    <div class="pt-4 border-t border-gray-100 dark:border-gray-700 flex gap-2">
                        <button wire:click="closeDetails" class="w-full py-2.5 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl text-xs font-bold transition">
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
            <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-2xl space-y-4 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                    <h3 class="font-black text-lg text-gray-900 dark:text-white">Record Offline Payment</h3>
                    <button wire:click="closePaymentModal" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Payment Method</label>
                        <select wire:model="paymentMethod" class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white">
                            <option value="Cash">Cash Payment</option>
                            <option value="UPI">UPI Payment</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">Payment Amount (₹)</label>
                        <input type="number" step="0.01" wire:model="paymentAmount" class="w-full p-2.5 text-xs rounded-xl border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white font-bold text-emerald-600">
                    </div>
                </div>

                <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                    <button wire:click="closePaymentModal" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-bold">Cancel</button>
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
                <div class="w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-2xl space-y-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-700">
                        <h3 class="font-black text-lg text-red-600 dark:text-red-400">Cancel Booking Dates</h3>
                        <button wire:click="closeCancelModal" class="text-gray-400 hover:text-gray-600">✕</button>
                    </div>

                    <p class="text-xs text-gray-600 dark:text-gray-400">
                        Select the session dates you wish to cancel for booking <strong class="text-gray-900 dark:text-white">{{ $cBooking->booking_reference ?? ('#' . $cBooking->id) }}</strong>:
                    </p>

                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        @foreach ($cBooking->bookingDates as $bd)
                            <label class="flex items-center justify-between p-3 rounded-xl border border-gray-200 dark:border-gray-700 cursor-pointer text-xs">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" value="{{ $bd->id }}" wire:model.live="cancelDateIds" 
                                        {{ $bd->status === 'Cancelled' ? 'disabled' : '' }} 
                                        class="rounded text-red-600 focus:ring-red-500">
                                    <span class="font-bold text-gray-900 dark:text-white">{{ $bd->booking_date }}</span>
                                </div>
                                <span class="font-bold {{ $bd->status === 'Cancelled' ? 'text-gray-400' : 'text-emerald-600' }}">
                                    {{ $bd->status === 'Cancelled' ? 'Already Cancelled' : ('₹' . number_format($bd->amount, 2)) }}
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex justify-end gap-2">
                        <button wire:click="closeCancelModal" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-xs font-bold">Keep Booking</button>
                        <button wire:click="submitCancellation" class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-xs">
                            Confirm Cancellation
                        </button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
