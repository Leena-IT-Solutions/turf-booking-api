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

    // Expanded Rows for Dates & Slots Levels
    public array $expandedBookingIds = [];

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

    public function toggleExpand(int $bookingId)
    {
        if (in_array($bookingId, $this->expandedBookingIds)) {
            $this->expandedBookingIds = array_values(array_diff($this->expandedBookingIds, [$bookingId]));
        } else {
            $this->expandedBookingIds[] = $bookingId;
        }
    }

    public function toggleExpandAll(array $currentBookingIds = [])
    {
        if (count($this->expandedBookingIds) >= count($currentBookingIds) && !empty($currentBookingIds)) {
            $this->expandedBookingIds = [];
        } else {
            $this->expandedBookingIds = $currentBookingIds;
        }
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

            <!-- Header Action Controls -->
            <div class="flex items-center gap-2">
                <!-- View Mode Toggle -->
                <div class="bg-gray-100 p-1 rounded-xl flex items-center border border-gray-200">
                    <button wire:click="$set('viewMode', 'table')" type="button" 
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $viewMode === 'table' ? 'bg-white text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        Table
                    </button>
                    <button wire:click="$set('viewMode', 'grid')" type="button" 
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 {{ $viewMode === 'grid' ? 'bg-white text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        Cards
                    </button>
                </div>
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

        <!-- Bookings Main Content (Table or Grid View) -->
        @if ($viewMode === 'table')
            <!-- COMPREHENSIVE 3-LEVEL TABLE VIEW -->
            @php
                $pageBookingIds = $bookings->pluck('id')->toArray();
                $allPageExpanded = !empty($pageBookingIds) && count(array_intersect($pageBookingIds, $expandedBookingIds)) === count($pageBookingIds);
            @endphp
            <div class="bg-white rounded-2xl shadow-xs border border-gray-200 overflow-hidden">
                <!-- Table Top Banner with Controls -->
                <div class="px-5 py-3.5 bg-gray-50/70 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-gray-700">Level Hierarchy:</span>
                        <span class="px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 font-semibold border border-indigo-100">1. Booking</span>
                        <span class="text-gray-400 font-bold">&rarr;</span>
                        <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 font-semibold border border-amber-100">2. Booking Dates</span>
                        <span class="text-gray-400 font-bold">&rarr;</span>
                        <span class="px-2 py-0.5 rounded-md bg-teal-50 text-teal-700 font-semibold border border-teal-100">3. Booking Slots</span>
                    </div>
                    <div>
                        <button wire:click="toggleExpandAll(@js($pageBookingIds))" type="button"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition border {{ $allPageExpanded ? 'bg-indigo-50 text-indigo-700 border-indigo-200 hover:bg-indigo-100' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }} shadow-2xs">
                            <svg class="w-4 h-4 transition-transform {{ $allPageExpanded ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <span>{{ $allPageExpanded ? 'Collapse All Dates & Slots' : 'Expand All Dates & Slots' }}</span>
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs border-collapse">
                        <!-- LEVEL 1: BOOKING LEVEL HEADER -->
                        <thead class="bg-gray-100/80 text-gray-600 uppercase tracking-wider font-extrabold border-b border-gray-200 text-[11px]">
                            <tr>
                                <th class="w-10 px-3 py-3.5 text-center">#</th>
                                <th class="px-4 py-3.5 whitespace-nowrap">Booking & Time</th>
                                <th class="px-4 py-3.5 min-w-[200px]">Customer</th>
                                <th class="px-4 py-3.5 min-w-[180px]">Turf & Type</th>
                                <th class="px-4 py-3.5 min-w-[160px]">Dates & Sessions</th>
                                <th class="px-4 py-3.5 min-w-[220px]">Financial Breakdown</th>
                                <th class="px-4 py-3.5 whitespace-nowrap">Statuses</th>
                                <th class="px-4 py-3.5 text-right whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-gray-700">
                            @forelse ($bookings as $b)
                                @php
                                    $isExpanded = in_array($b->id, $expandedBookingIds);
                                    $activeDates = $b->bookingDates->where('status', '!=', 'Cancelled');
                                    $totalAmount = (float)($b->total_amount > 0 ? $b->total_amount : $activeDates->sum('amount'));
                                    $paidSum = (float)$b->payments->where('status', 'Success')->sum('amount');
                                    $balance = max(0.00, $totalAmount - $paidSum);

                                    $totalSlotsCount = $b->bookingDates->sum(fn($bd) => $bd->bookingSlots->count());
                                    $datesCount = $b->bookingDates->count();
                                    $cancelledDatesCount = $b->bookingDates->where('status', 'Cancelled')->count();
                                @endphp

                                <!-- LEVEL 1: BOOKING MASTER ROW -->
                                <tr class="transition {{ $isExpanded ? 'bg-indigo-50/25' : 'hover:bg-gray-50/70' }}">
                                    <!-- Expand/Collapse Chevron -->
                                    <td class="px-3 py-4 text-center align-top">
                                        <button wire:click="toggleExpand({{ $b->id }})" type="button" 
                                            title="{{ $isExpanded ? 'Hide Dates & Slots' : 'Show Dates & Slots' }}"
                                            class="w-7 h-7 inline-flex items-center justify-center rounded-lg border border-gray-200 hover:border-indigo-400 bg-white hover:bg-indigo-50 text-gray-500 hover:text-indigo-600 transition shadow-2xs cursor-pointer">
                                            <svg class="w-4 h-4 transition-transform duration-200 {{ $isExpanded ? 'rotate-90 text-indigo-600' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </button>
                                    </td>

                                    <!-- 1. Booking Reference & Booked At Timestamp -->
                                    <td class="px-4 py-4 align-top whitespace-nowrap">
                                        <div class="font-black text-gray-900 text-sm tracking-tight">
                                            {{ $b->booking_reference ?? ('#' . $b->id) }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-1 flex items-center gap-1.5">
                                            <span class="text-gray-400">Booked:</span>
                                            <span class="font-medium text-gray-700">{{ $b->created_at ? $b->created_at->format('d M Y, h:i A') : 'N/A' }}</span>
                                        </div>
                                        <div class="mt-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-gray-100 text-gray-600 border border-gray-200">
                                                ID #{{ $b->id }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- 2. Customer Details -->
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-start gap-2.5">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 font-black flex items-center justify-center text-xs shrink-0 border border-indigo-200 mt-0.5">
                                                {{ strtoupper(substr($b->user?->name ?? 'G', 0, 1)) }}
                                            </div>
                                            <div class="space-y-0.5 min-w-0">
                                                <div class="font-bold text-gray-900 text-xs truncate">
                                                    {{ $b->user?->name ?? 'Manual / Guest User' }}
                                                </div>
                                                <div class="text-[11px] text-gray-600 font-medium flex items-center gap-1">
                                                    <svg class="w-3 h-3 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                    <span>{{ $b->user?->mobile ?? 'No Mobile' }}</span>
                                                </div>
                                                @if ($b->user?->email)
                                                    <div class="text-[10px] text-gray-400 truncate max-w-[170px]" title="{{ $b->user->email }}">
                                                        {{ $b->user->email }}
                                                    </div>
                                                @endif
                                                @if ($b->customer_gstin || $b->customer_company_name)
                                                    <div class="mt-1 pt-1 border-t border-gray-100 text-[10px] text-indigo-700 font-semibold">
                                                        🏢 {{ $b->customer_company_name ?? 'B2B' }} (GSTIN: {{ $b->customer_gstin ?? 'N/A' }})
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 3. Turf & Location & Booking Type -->
                                    <td class="px-4 py-4 align-top">
                                        <div class="font-extrabold text-gray-900 text-xs">
                                            {{ $b->turf?->name ?? 'Turf' }}
                                        </div>
                                        <div class="text-[11px] text-gray-500 mt-0.5 flex items-center gap-1">
                                            <span>📍 {{ $b->turf?->location?->name ?? 'Main Location' }}</span>
                                        </div>
                                        <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                {{ ucfirst($b->booking_type ?? 'day') }}
                                            </span>
                                            @if ($b->turf?->type)
                                                <span class="text-[10px] text-gray-500">({{ $b->turf->type }})</span>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- 4. Dates & Sessions Level Summary -->
                                    <td class="px-4 py-4 align-top">
                                        <div class="flex items-center gap-1.5">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                📅 {{ $datesCount }} {{ \Illuminate\Support\Str::plural('Date', $datesCount) }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                                ⏰ {{ $totalSlotsCount }} {{ \Illuminate\Support\Str::plural('Slot', $totalSlotsCount) }}
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-gray-600 mt-1.5 font-medium">
                                            @php
                                                $dateList = $b->bookingDates->pluck('booking_date')->toArray();
                                            @endphp
                                            @if (count($dateList) === 1)
                                                <span>{{ $dateList[0] }}</span>
                                            @elseif (count($dateList) > 1)
                                                <span>{{ $dateList[0] }} &rarr; {{ end($dateList) }}</span>
                                            @else
                                                <span class="text-gray-400 italic">No dates</span>
                                            @endif
                                        </div>
                                        @if ($cancelledDatesCount > 0)
                                            <div class="text-[10px] text-red-600 font-bold mt-0.5">
                                                ⚠️ {{ $cancelledDatesCount }} date(s) cancelled
                                            </div>
                                        @endif
                                    </td>

                                    <!-- 5. Financial Breakdown -->
                                    <td class="px-4 py-4 align-top">
                                        <div class="space-y-1">
                                            <!-- Total & Balance Highlights -->
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="font-extrabold text-gray-900 text-xs">Total: ₹{{ number_format($totalAmount, 2) }}</span>
                                                <span class="font-bold text-[11px] {{ $balance > 0 ? 'text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200' : 'text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200' }}">
                                                    {{ $balance > 0 ? ('Due: ₹' . number_format($balance, 2)) : 'Paid in Full' }}
                                                </span>
                                            </div>
                                            <!-- Sub-breakdown details -->
                                            <div class="text-[10px] text-gray-500 space-y-0.5 pt-1 border-t border-gray-100">
                                                <div class="flex items-center justify-between">
                                                    <span>Taxable Base:</span>
                                                    <span class="font-medium text-gray-700">₹{{ number_format($b->taxable_amount ?? 0, 2) }}</span>
                                                </div>
                                                @if ((float)$b->coupon_discount > 0 || (float)$b->additional_discount > 0)
                                                    <div class="flex items-center justify-between text-emerald-600 font-medium">
                                                        <span>Discounts:</span>
                                                        <span>-₹{{ number_format(((float)$b->coupon_discount + (float)$b->additional_discount), 2) }}</span>
                                                    </div>
                                                @endif
                                                @if ((float)$b->turf_gst_amount > 0)
                                                    <div class="flex items-center justify-between">
                                                        <span>Turf GST ({{ (float)$b->turf_gst_rate }}%):</span>
                                                        <span class="font-medium text-gray-700">₹{{ number_format($b->turf_gst_amount, 2) }}</span>
                                                    </div>
                                                @endif
                                                @if ((float)$b->platform_fee > 0)
                                                    <div class="flex items-center justify-between">
                                                        <span>Platform Fee:</span>
                                                        <span class="font-medium text-gray-700">₹{{ number_format(((float)$b->platform_fee + (float)$b->platform_fee_gst), 2) }}</span>
                                                    </div>
                                                @endif
                                                <div class="flex items-center justify-between text-emerald-700 font-bold pt-0.5">
                                                    <span>Paid Amount:</span>
                                                    <span>₹{{ number_format($paidSum, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- 6. Statuses -->
                                    <td class="px-4 py-4 align-top whitespace-nowrap">
                                        <div class="space-y-1.5">
                                            <!-- Booking Status Badge -->
                                            <div>
                                                @if ($b->status === 'Confirmed')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                                        ● Confirmed
                                                    </span>
                                                @elseif ($b->status === 'Partially Cancelled')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-orange-50 text-orange-700 border border-orange-200">
                                                        ● Partially Cancelled
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-700 border border-gray-300">
                                                        ● Cancelled
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Payment Status Badge -->
                                            <div>
                                                @if ($b->payment_status === 'Paid')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                        ✓ Paid
                                                    </span>
                                                @elseif ($b->payment_status === 'Partially Paid')
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                                        ⏳ Partially Paid
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-50 text-red-700 border border-red-200">
                                                        ✕ Unpaid
                                                    </span>
                                                @endif
                                            </div>

                                            <!-- Refund Audit (if cancelled) -->
                                            @if ($b->refund_status && $b->refund_status !== 'Not Applicable')
                                                <div class="text-[10px] font-semibold text-purple-700">
                                                    Refund: {{ $b->refund_status }} (₹{{ number_format($b->refund_amount ?? 0, 2) }})
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    <!-- 7. Actions -->
                                    <td class="px-4 py-4 align-top text-right whitespace-nowrap">
                                        <div class="flex flex-col items-end gap-1.5">
                                            <button wire:click="viewDetails({{ $b->id }})" type="button" 
                                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                Ledger
                                            </button>

                                            @if ($balance > 0 && $b->status !== 'Cancelled')
                                                @php $firstUnpaidDate = $b->bookingDates->firstWhere('payment_status', '!=', 'Paid'); @endphp
                                                @if ($firstUnpaidDate)
                                                    <button wire:click="openPaymentModal({{ $firstUnpaidDate->id }})" type="button" 
                                                        class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-bold transition flex items-center gap-1 shadow-2xs cursor-pointer">
                                                        + Record Pay
                                                    </button>
                                                @endif
                                            @endif

                                            <button wire:click="toggleExpand({{ $b->id }})" type="button" 
                                                class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-1 mt-0.5">
                                                <span>{{ $isExpanded ? 'Collapse' : 'Expand Levels' }}</span>
                                                <svg class="w-3 h-3 transition-transform {{ $isExpanded ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- LEVEL 2 & LEVEL 3 EXPANDED CONTAINER ROW -->
                                @if ($isExpanded)
                                    <tr class="bg-indigo-50/20">
                                        <td colspan="8" class="p-0">
                                            <div class="p-4 sm:p-5 border-y border-indigo-100 space-y-4">
                                                
                                                <!-- Level Header Banner -->
                                                <div class="flex items-center justify-between pb-2 border-b border-indigo-100">
                                                    <div class="flex items-center gap-2">
                                                        <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                                                        <h4 class="font-black text-gray-900 text-xs uppercase tracking-wider">
                                                            Booking Dates & Slots Hierarchy for {{ $b->booking_reference ?? ('#' . $b->id) }}
                                                        </h4>
                                                    </div>
                                                    <span class="text-[11px] text-gray-500 font-medium">
                                                        Total {{ $datesCount }} {{ \Illuminate\Support\Str::plural('Date', $datesCount) }} • {{ $totalSlotsCount }} {{ \Illuminate\Support\Str::plural('Slot', $totalSlotsCount) }}
                                                    </span>
                                                </div>

                                                <!-- LEVEL 2: DATES ACCORDION / LIST -->
                                                <div class="space-y-3">
                                                    @foreach ($b->bookingDates as $bdIndex => $bd)
                                                        @php
                                                            $bdPaidSum = (float) Payment::where('booking_date_id', $bd->id)->where('status', 'Success')->sum('amount');
                                                            $bdBalance = max(0.00, (float)$bd->amount - $bdPaidSum);
                                                            $dateCarbon = Carbon::parse($bd->booking_date);
                                                        @endphp
                                                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-2xs">
                                                            
                                                            <!-- LEVEL 2: BOOKING DATE HEADER ROW (ALL COLUMNS) -->
                                                            <div class="p-3.5 bg-gradient-to-r from-gray-50 to-amber-50/30 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3 text-xs">
                                                                <!-- Date & Day -->
                                                                <div class="flex items-center gap-3">
                                                                    <span class="w-6 h-6 rounded-lg bg-amber-100 text-amber-800 font-bold flex items-center justify-center text-xs shrink-0 border border-amber-200">
                                                                        {{ $bdIndex + 1 }}
                                                                    </span>
                                                                    <div>
                                                                        <div class="font-extrabold text-gray-900 text-xs sm:text-sm flex items-center gap-2">
                                                                            <span>📅 {{ $dateCarbon->format('d M Y') }}</span>
                                                                            <span class="text-xs font-semibold text-gray-500">({{ $dateCarbon->format('l') }})</span>
                                                                        </div>
                                                                        <div class="text-[10px] text-gray-500 mt-0.5">
                                                                            Booking Date ID: #{{ $bd->id }}
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <!-- Date Financial Breakdown -->
                                                                <div class="flex flex-wrap items-center gap-4 text-xs">
                                                                    <div>
                                                                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Date Amount</span>
                                                                        <span class="font-black text-gray-900">₹{{ number_format($bd->amount, 2) }}</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Paid</span>
                                                                        <span class="font-bold text-emerald-700">₹{{ number_format($bdPaidSum, 2) }}</span>
                                                                    </div>
                                                                    <div>
                                                                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Balance</span>
                                                                        <span class="font-bold {{ $bdBalance > 0 ? 'text-amber-700' : 'text-gray-400' }}">
                                                                            ₹{{ number_format($bdBalance, 2) }}
                                                                        </span>
                                                                    </div>

                                                                    <!-- Date Statuses -->
                                                                    <div>
                                                                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Date Status</span>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $bd->status === 'Confirmed' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'bg-gray-100 text-gray-600 border border-gray-300' }}">
                                                                            {{ $bd->status }}
                                                                        </span>
                                                                    </div>

                                                                    <div>
                                                                        <span class="text-[10px] uppercase font-bold text-gray-400 block">Payment</span>
                                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $bd->payment_status === 'Paid' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($bd->payment_status === 'Partially Paid' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-red-50 text-red-700 border border-red-200') }}">
                                                                            {{ $bd->payment_status }}
                                                                        </span>
                                                                    </div>

                                                                    <!-- Date Level Action -->
                                                                    @if ($bdBalance > 0 && $bd->status !== 'Cancelled')
                                                                        <div>
                                                                            <button wire:click="openPaymentModal({{ $bd->id }})" type="button"
                                                                                class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition shadow-2xs">
                                                                                Pay Date
                                                                            </button>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <!-- Date Cancellation / Refund Audit (if cancelled) -->
                                                            @if ($bd->status === 'Cancelled' || (float)$bd->cancellation_fee_applied > 0 || (float)$bd->refund_amount > 0)
                                                                <div class="px-4 py-2 bg-red-50/50 border-b border-red-100 flex flex-wrap items-center justify-between text-[11px] text-red-800">
                                                                    <div class="flex items-center gap-2">
                                                                        <span>⚠️ <strong>Cancelled Date Audit:</strong></span>
                                                                        <span>Fee: ₹{{ number_format($bd->cancellation_fee_applied ?? 0, 2) }}</span>
                                                                        <span>•</span>
                                                                        <span>Refund: ₹{{ number_format($bd->refund_amount ?? 0, 2) }} ({{ $bd->refund_status ?? 'N/A' }})</span>
                                                                    </div>
                                                                    <div class="text-[10px] text-gray-500">
                                                                        {{ $bd->cancelled_at ? Carbon::parse($bd->cancelled_at)->format('d M Y, h:i A') : '' }}
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            <!-- LEVEL 3: BOOKING SLOTS LEVEL (SUB-TABLE) -->
                                                            <div class="p-3 bg-white">
                                                                <div class="text-[10px] font-bold uppercase tracking-wider text-teal-800 mb-2 flex items-center gap-1.5">
                                                                    <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                                                                    <span>Booked Slots Level ({{ $bd->bookingSlots->count() }} {{ \Illuminate\Support\Str::plural('Slot', $bd->bookingSlots->count()) }}):</span>
                                                                </div>

                                                                @if ($bd->bookingSlots->isNotEmpty())
                                                                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                                                                        <table class="w-full text-left text-xs bg-white">
                                                                            <thead class="bg-teal-50/60 text-teal-900 uppercase tracking-wider font-bold text-[10px] border-b border-gray-200">
                                                                                <tr>
                                                                                    <th class="px-3 py-2 w-12 text-center">#</th>
                                                                                    <th class="px-3 py-2">Slot Time Window</th>
                                                                                    <th class="px-3 py-2">Duration</th>
                                                                                    <th class="px-3 py-2">Category</th>
                                                                                    <th class="px-3 py-2">Slot Status</th>
                                                                                    <th class="px-3 py-2 text-right">Estimated Rate</th>
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody class="divide-y divide-gray-100 text-[11px] text-gray-700">
                                                                                @foreach ($bd->bookingSlots as $sIndex => $bs)
                                                                                    @php
                                                                                        $fromFormatted = $bs->slot?->from_time ? date('h:i A', strtotime($bs->slot->from_time)) : 'N/A';
                                                                                        $toFormatted = $bs->slot?->to_time ? date('h:i A', strtotime($bs->slot->to_time)) : 'N/A';
                                                                                        $slotDuration = $bs->slot?->duration ?? 60;
                                                                                        $slotCategory = $bs->slot?->category?->name ?? 'Standard';
                                                                                        $avgSlotPrice = $bd->bookingSlots->count() > 0 ? ($bd->amount / $bd->bookingSlots->count()) : 0;
                                                                                    @endphp
                                                                                    <tr class="hover:bg-gray-50/50">
                                                                                        <td class="px-3 py-2 text-center text-gray-400 font-bold">
                                                                                            {{ $sIndex + 1 }}
                                                                                        </td>
                                                                                        <td class="px-3 py-2 font-bold text-gray-900 whitespace-nowrap">
                                                                                            ⏰ {{ $fromFormatted }} &rarr; {{ $toFormatted }}
                                                                                        </td>
                                                                                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap">
                                                                                            {{ $slotDuration }} mins
                                                                                        </td>
                                                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-teal-50 text-teal-800 border border-teal-200">
                                                                                                {{ $slotCategory }}
                                                                                            </span>
                                                                                        </td>
                                                                                        <td class="px-3 py-2 whitespace-nowrap">
                                                                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $bs->status === 'Confirmed' || $bs->status === 'Booked' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-gray-100 text-gray-600 border border-gray-300' }}">
                                                                                                {{ $bs->status ?? 'Booked' }}
                                                                                            </span>
                                                                                        </td>
                                                                                        <td class="px-3 py-2 text-right font-bold text-gray-900 whitespace-nowrap">
                                                                                            ₹{{ number_format($avgSlotPrice, 2) }}
                                                                                        </td>
                                                                                    </tr>
                                                                                @endforeach
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                @else
                                                                    <p class="text-xs text-gray-400 italic">No specific slot intervals recorded for this date.</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                                        <div class="max-w-xs mx-auto space-y-2">
                                            <span class="text-3xl block">📭</span>
                                            @if (empty($manageableTurfIds))
                                                <p class="font-bold text-gray-700">You don't have any turfs or bookings yet.</p>
                                                <a href="{{ route('turf.turfs') }}" class="inline-block text-xs font-bold text-indigo-600 hover:underline">Add your first turf</a>
                                            @else
                                                <p class="font-bold text-gray-700">No bookings matched your search or filters.</p>
                                                <button wire:click="clearFilters" class="text-xs font-bold text-indigo-600 hover:underline">Clear all filters</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 border-t border-gray-100 bg-gray-50/50">
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

                    <div class="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col justify-between space-y-4 hover:border-indigo-400 transition">
                        <div>
                            <!-- Card Header -->
                            <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-3">
                                <div>
                                    <span class="text-xs font-bold text-gray-900">{{ $b->booking_reference ?? ('#' . $b->id) }}</span>
                                    <h3 class="font-extrabold text-sm text-gray-900 mt-0.5">{{ $b->user?->name ?? 'Manual / Guest' }}</h3>
                                    <p class="text-xs text-gray-500">{{ $b->user?->mobile ?? $b->user?->email ?? '' }}</p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold {{ $b->status === 'Confirmed' ? 'bg-blue-100 text-blue-800 ' : 'bg-gray-200 text-gray-700 ' }}">
                                    {{ $b->status }}
                                </span>
                            </div>

                            <!-- Dates & Turf -->
                            <div class="mt-3 space-y-1.5 text-xs">
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Turf & Location:</span>
                                    <span class="font-bold text-gray-800 text-right">{{ $b->turf?->name }} ({{ $b->turf?->location?->name ?? 'Main' }})</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-gray-500">Type:</span>
                                    <span class="font-bold text-indigo-700 uppercase text-[10px]">{{ $b->booking_type ?? 'day' }}</span>
                                </div>
                            </div>

                            <!-- Level 2 & 3: Dates & Booked Slots Summary -->
                            <div class="mt-3 pt-2.5 border-t border-gray-100 space-y-2">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-amber-800">📅 Session Dates ({{ $b->bookingDates->count() }}):</span>
                                    <span class="text-teal-800 font-bold">⏰ {{ $b->bookingDates->sum(fn($bd) => $bd->bookingSlots->count()) }} Slots</span>
                                </div>
                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                    @foreach ($b->bookingDates as $cBd)
                                        @php
                                            $cBdCarbon = Carbon::parse($cBd->booking_date);
                                        @endphp
                                        <div class="p-2 rounded-lg bg-gray-50 border border-gray-100 text-[11px]">
                                            <div class="flex items-center justify-between font-bold text-gray-900">
                                                <span>{{ $cBdCarbon->format('d M Y') }} ({{ $cBdCarbon->format('D') }})</span>
                                                <span>₹{{ number_format($cBd->amount, 2) }}</span>
                                            </div>
                                            <!-- Slots on this date -->
                                            <div class="mt-1 flex flex-wrap gap-1">
                                                @foreach ($cBd->bookingSlots as $cBs)
                                                    @if ($cBs->slot)
                                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-teal-50 text-teal-800 border border-teal-100 font-medium">
                                                            {{ date('h:i A', strtotime($cBs->slot->from_time)) }}-{{ date('h:i A', strtotime($cBs->slot->to_time)) }}
                                                        </span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Payment Progress Bar -->
                            <div class="mt-3 space-y-1">
                                <div class="flex items-center justify-between text-xs font-bold">
                                    <span class="text-gray-700">₹{{ number_format($paidSum, 2) }} paid</span>
                                    <span class="text-gray-500">Total: ₹{{ number_format($totalAmount, 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                    <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Card Action Footer -->
                        <div class="pt-3 border-t border-gray-100 flex items-center justify-between gap-2">
                            <button wire:click="viewDetails({{ $b->id }})" type="button" 
                                class="flex-1 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition text-center">
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
                    <div class="col-span-full bg-white p-12 rounded-2xl border border-gray-200 text-center text-gray-500 space-y-2">
                        <span class="text-3xl block">📭</span>
                        @if (empty($manageableTurfIds))
                            <p class="font-bold text-gray-700">You don't have any turfs or bookings yet.</p>
                            <a href="{{ route('turf.turfs') }}" class="inline-block text-xs font-bold text-indigo-600 hover:underline">Add your first turf</a>
                        @else
                            <p class="font-bold text-gray-700">No bookings found matching your search.</p>
                        @endif
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
                <div class="w-full max-w-xl bg-white min-h-screen p-6 shadow-2xl flex flex-col justify-between border-l border-gray-200">
                    <div class="space-y-6">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                            <div>
                                <span class="text-xs font-bold text-indigo-600 uppercase tracking-wider">Booking Ledger</span>
                                <h2 class="text-xl font-black text-gray-900">{{ $bDetail->booking_reference ?? ('#' . $bDetail->id) }}</h2>
                            </div>
                            <button wire:click="closeDetails" class="p-2 text-gray-400 hover:text-gray-600 rounded-xl hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <!-- Financial Summary Box -->
                        @php
                            $dActiveDates = $bDetail->bookingDates->where('status', '!=', 'Cancelled');
                            $dTotalAmount = (float)$dActiveDates->sum('amount');
                            $dPaidSum = (float)$bDetail->payments->where('status', 'Success')->sum('amount');
                            $dBalance = max(0.00, $dTotalAmount - $dPaidSum);
                        @endphp

                        <div class="p-4 rounded-xl bg-gray-50 border border-gray-100 space-y-3">
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="p-2 rounded-lg bg-white border border-gray-100">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Total</span>
                                    <p class="text-sm font-black text-gray-900 mt-0.5">₹{{ number_format($dTotalAmount, 2) }}</p>
                                </div>
                                <div class="p-2 rounded-lg bg-white border border-gray-100">
                                    <span class="text-[10px] font-bold text-emerald-600 uppercase">Paid</span>
                                    <p class="text-sm font-black text-emerald-600 mt-0.5">₹{{ number_format($dPaidSum, 2) }}</p>
                                </div>
                                <div class="p-2 rounded-lg bg-white border border-gray-100">
                                    <span class="text-[10px] font-bold text-amber-600 uppercase">Balance</span>
                                    <p class="text-sm font-black text-amber-600 mt-0.5">₹{{ number_format($dBalance, 2) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Customer & Turf Overview -->
                        <div class="grid grid-cols-2 gap-4 p-4 rounded-xl bg-gray-50 border border-gray-100">
                            <div>
                                <span class="text-[10px] font-bold uppercase text-gray-400">Customer Info</span>
                                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $bDetail->user?->name ?? 'Guest User' }}</p>
                                <p class="text-xs text-gray-500">{{ $bDetail->user?->mobile ?? 'No Mobile' }}</p>
                                <p class="text-xs text-gray-500 truncate">{{ $bDetail->user?->email ?? '' }}</p>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold uppercase text-gray-400">Turf & Session Type</span>
                                <p class="text-sm font-bold text-gray-900 mt-0.5">{{ $bDetail->turf?->name }}</p>
                                <span class="inline-block mt-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-100 text-indigo-800">
                                    {{ ucfirst($bDetail->booking_type) }} Session
                                </span>
                            </div>
                        </div>

                        <!-- Dates & Slots Timeline -->
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Booked Dates & Slots</h3>
                            <div class="space-y-2 max-h-60 overflow-y-auto pr-1">
                                @foreach ($bDetail->bookingDates as $bd)
                                    @php
                                        $bdPaidSum = (float) Payment::where('booking_date_id', $bd->id)->where('status', 'Success')->sum('amount');
                                    @endphp
                                    <div class="p-3 rounded-xl border border-gray-100 bg-white flex items-center justify-between">
                                        <div>
                                            <div class="font-bold text-xs text-gray-900">📅 {{ $bd->booking_date }}</div>
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
                                            <div class="font-bold text-xs text-gray-900">₹{{ number_format($bd->amount, 2) }}</div>
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
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3">Payments History</h3>
                            <div class="space-y-2">
                                @forelse ($bDetail->payments as $pay)
                                    <div class="p-3 rounded-xl bg-gray-50 border border-gray-100 flex items-center justify-between text-xs">
                                        <div>
                                            <span class="font-bold text-gray-900">₹{{ number_format($pay->amount, 2) }}</span>
                                            <span class="ml-2 text-[10px] font-semibold px-2 py-0.5 rounded bg-gray-200 text-gray-700">
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
                    <div class="pt-4 border-t border-gray-100 flex items-center gap-2">
                        @if ($dBalance > 0 && $bDetail->status !== 'Cancelled')
                            @php $firstUnpaidDate = $bDetail->bookingDates->firstWhere('payment_status', '!=', 'Paid'); @endphp
                            @if ($firstUnpaidDate)
                                <button wire:click="openPaymentModal({{ $firstUnpaidDate->id }})" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1 shadow-xs">
                                    + Record Pay
                                </button>
                            @endif
                        @endif

                        @if ($bDetail->status !== 'Cancelled')
                            <button wire:click="openCancelModal({{ $bDetail->id }})" class="py-2.5 px-4 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-xs font-bold transition">
                                Cancel
                            </button>
                        @endif

                        <button wire:click="closeDetails" class="py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition">
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
