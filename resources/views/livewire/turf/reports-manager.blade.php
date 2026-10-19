<?php

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\Turf;
use App\Services\ReportService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $activeTab = 'bookings';
    public string $period = 'month'; // 'day', 'month', 'year', 'all'
    public string $cursorDate = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $statusFilter = 'all';
    public string $paymentStatusFilter = 'all';

    #[On('global-context-updated')]
    public function refreshContext()
    {
        // Auto refreshes
    }

    public function mount()
    {
        $now = Carbon::now('Asia/Kolkata');
        $this->cursorDate = $now->toDateString();
        $this->updateDateRange();
    }

    public function setPeriod(string $p)
    {
        $this->period = $p;
        $this->updateDateRange();
    }

    public function prevPeriod()
    {
        if ($this->period === 'all') return;
        $d = Carbon::parse($this->cursorDate ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
        if ($this->period === 'day') {
            $this->cursorDate = $d->subDay()->toDateString();
        } elseif ($this->period === 'month') {
            $this->cursorDate = $d->subMonth()->toDateString();
        } elseif ($this->period === 'year') {
            $this->cursorDate = $d->subYear()->toDateString();
        }
        $this->updateDateRange();
    }

    public function nextPeriod()
    {
        if ($this->period === 'all') return;
        $d = Carbon::parse($this->cursorDate ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
        if ($this->period === 'day') {
            $this->cursorDate = $d->addDay()->toDateString();
        } elseif ($this->period === 'month') {
            $this->cursorDate = $d->addMonth()->toDateString();
        } elseif ($this->period === 'year') {
            $this->cursorDate = $d->addYear()->toDateString();
        }
        $this->updateDateRange();
    }

    public function goToToday()
    {
        $this->cursorDate = Carbon::now('Asia/Kolkata')->toDateString();
        if ($this->period === 'all') {
            $this->period = 'month';
        }
        $this->updateDateRange();
    }

    protected function updateDateRange()
    {
        $d = Carbon::parse($this->cursorDate ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
        if ($this->period === 'day') {
            $this->startDate = $d->copy()->startOfDay()->toDateString();
            $this->endDate = $d->copy()->endOfDay()->toDateString();
        } elseif ($this->period === 'month') {
            $this->startDate = $d->copy()->startOfMonth()->toDateString();
            $this->endDate = $d->copy()->endOfMonth()->toDateString();
        } elseif ($this->period === 'year') {
            $this->startDate = $d->copy()->startOfYear()->toDateString();
            $this->endDate = $d->copy()->endOfYear()->toDateString();
        } else { // 'all'
            $this->startDate = '2020-01-01';
            $this->endDate = Carbon::now('Asia/Kolkata')->endOfDay()->toDateString();
        }
    }

    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
    }

    public function setQuickFilter(string $range)
    {
        if ($range === 'today') {
            $this->setPeriod('day');
        } elseif ($range === 'week') {
            $now = Carbon::now('Asia/Kolkata');
            $this->startDate = $now->copy()->startOfWeek()->toDateString();
            $this->endDate = $now->copy()->endOfWeek()->toDateString();
        } elseif ($range === 'month') {
            $this->setPeriod('month');
        }
    }
}; ?>

<div class="w-full">
    <div class="w-full space-y-6">
        @php
            $user = auth()->user();
            $activeTurfId = session('active_turf_id');
            $manageableTurfIds = Turf::manageable($user)->pluck('id')->toArray();
            $reportService = app(ReportService::class);

            // Compute dynamic Period Label
            $d = Carbon::parse($this->cursorDate ?: Carbon::now('Asia/Kolkata')->toDateString(), 'Asia/Kolkata');
            if ($this->period === 'day') {
                $periodLabel = $d->isToday() ? 'Today (' . $d->format('d M Y') . ')' : $d->format('d M Y');
            } elseif ($this->period === 'month') {
                $periodLabel = $d->isCurrentMonth() ? 'This Month (' . $d->format('F Y') . ')' : $d->format('F Y');
            } elseif ($this->period === 'year') {
                $periodLabel = $d->isCurrentYear() ? 'This Year (' . $d->format('Y') . ')' : $d->format('Y');
            } else {
                $periodLabel = 'All Time';
            }

            // Fetch statistics for bookings tab
            $bookingsQuery = Booking::with(['bookingDates.bookingSlots', 'payments', 'turf', 'user', 'bookingCancellations'])
                ->where(function ($q) {
                    $q->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                      ->orWhereHas('bookingDates', function ($bq) {
                          $bq->whereBetween('booking_date', [$this->startDate, $this->endDate]);
                      });
                });

            if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
                $bookingsQuery->where('turf_id', $activeTurfId);
            } else {
                $bookingsQuery->whereIn('turf_id', $manageableTurfIds);
            }

            $allScopeBookings = (clone $bookingsQuery)->get();

            if ($this->statusFilter !== 'all') {
                $bookingsQuery->where('status', $this->statusFilter);
            }
            if ($this->paymentStatusFilter !== 'all') {
                $bookingsQuery->where('payment_status', $this->paymentStatusFilter);
            }

            $bookings = $bookingsQuery->orderBy('created_at', 'desc')->get();

            // Financial Metrics Calculations
            $confirmedBookings = $allScopeBookings->where('status', 'Confirmed');
            $totalBilling = 0.00;
            $totalReceived = 0.00;
            $slotsUtilized = 0;

            foreach ($confirmedBookings as $b) {
                $totalBilling += (float)($b->total_amount ?: $b->bookingDates->where('status', '!=', 'Cancelled')->sum('amount'));
                $totalReceived += (float)$b->payments->where('status', 'Success')->sum('amount');
                foreach ($b->bookingDates as $bd) {
                    if ($bd->status !== 'Cancelled') {
                        $slotsUtilized += $bd->bookingSlots->count() ?: 1;
                    }
                }
            }

            $pendingPayments = max(0.00, $totalBilling - $totalReceived);
            $collectionPercentage = $totalBilling > 0 ? round(($totalReceived / $totalBilling) * 100, 1) : 0.0;
            $confirmedBookingsCount = $confirmedBookings->count();
            $avgBookingValue = $confirmedBookingsCount > 0 ? ($totalBilling / $confirmedBookingsCount) : 0.00;

            // Operational Metrics
            $daysInPeriod = max(1, Carbon::parse($this->startDate)->diffInDays(Carbon::parse($this->endDate)) + 1);
            $turfsCountInScope = max(1, count($activeTurfId && in_array($activeTurfId, $manageableTurfIds) ? [$activeTurfId] : $manageableTurfIds));
            $totalCapacitySlots = $this->period === 'all' ? max(1, $slotsUtilized) : ($daysInPeriod * 16 * $turfsCountInScope);
            $pitchOccupancy = $totalCapacitySlots > 0 ? min(100.0, round(($slotsUtilized / $totalCapacitySlots) * 100, 1)) : 0.0;

            $checkedInOrPaidCount = $confirmedBookings->filter(function($b) {
                return $b->payment_status === 'Paid' || (float)$b->balance_amount <= 0;
            })->count();
            $showUpRate = $confirmedBookingsCount > 0 ? round(($checkedInOrPaidCount / $confirmedBookingsCount) * 100, 1) : 100.0;

            $cancelledBookings = $allScopeBookings->where('status', 'Cancelled');
            $cancellationsCount = $cancelledBookings->count();
            $totalRefunded = $cancelledBookings->sum('refund_amount');

            $totalBookingsCount = $bookings->count();
            $totalOutstandingBalance = $pendingPayments;
            $totalAmountSum = $totalBilling;
            $totalPaidSum = $totalReceived;

            // Fetch tab data on demand
            $passbookData = $activeTab === 'passbook' ? $reportService->getWalletPassbookSummary($user, $this->startDate, $this->endDate) : null;
            $turfEarningsData = $activeTab === 'turf-earnings' ? $reportService->getTurfEarningsSummary($user, $activeTurfId, $this->startDate, $this->endDate) : null;
            $gstData = $activeTab === 'gst' ? $reportService->getTurfGstSummary($user, $activeTurfId, $this->startDate, $this->endDate) : null;
            $cancellationData = $activeTab === 'cancellations' ? $reportService->getCancellationSummary($user, $activeTurfId, $this->startDate, $this->endDate) : null;
            $commissionData = $activeTab === 'commission' ? $reportService->getCommissionFeeSummary($user, $this->startDate, $this->endDate) : null;
            $payoutData = $activeTab === 'payouts' ? $reportService->getPayoutHistorySummary($user, $this->startDate, $this->endDate) : null;
        @endphp

        <!-- Modern Period Analytics Dashboard -->
        <div class="space-y-6">
            <!-- Period Filter Controller Bar -->
            <div class="bg-white rounded-3xl p-4 sm:p-5 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <!-- Left: Period Pills -->
                <div class="flex items-center gap-2.5">
                    <span class="text-xs font-black uppercase text-gray-500 tracking-wider">PERIOD:</span>
                    <div class="inline-flex p-1 bg-gray-100/90 rounded-full border border-gray-200/80">
                        <button type="button" wire:click="setPeriod('day')" class="px-4 py-1.5 rounded-full text-xs font-bold transition {{ $period === 'day' ? 'bg-emerald-600 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">Day</button>
                        <button type="button" wire:click="setPeriod('month')" class="px-4 py-1.5 rounded-full text-xs font-bold transition {{ $period === 'month' ? 'bg-emerald-600 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">Month</button>
                        <button type="button" wire:click="setPeriod('year')" class="px-4 py-1.5 rounded-full text-xs font-bold transition {{ $period === 'year' ? 'bg-emerald-600 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">Year</button>
                        <button type="button" wire:click="setPeriod('all')" class="px-4 py-1.5 rounded-full text-xs font-bold transition {{ $period === 'all' ? 'bg-emerald-600 text-white shadow-xs' : 'text-gray-600 hover:text-gray-900' }}">All Time</button>
                    </div>
                </div>

                <!-- Right: Prev / Calendar Pill / Next / Today -->
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="prevPeriod" class="px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 shadow-2xs transition flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        Prev
                    </button>

                    <div class="px-4 py-1.5 rounded-full border border-gray-200 text-xs font-bold text-gray-800 bg-white flex items-center gap-2 shadow-2xs">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ $periodLabel }}</span>
                    </div>

                    <button type="button" wire:click="nextPeriod" class="px-3.5 py-1.5 rounded-full border border-gray-200 text-xs font-semibold text-gray-700 bg-white hover:bg-gray-50 shadow-2xs transition flex items-center gap-1">
                        Next
                        <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>

                    <button type="button" wire:click="goToToday" class="px-4 py-1.5 rounded-full border border-gray-200 text-xs font-bold text-gray-700 bg-white hover:bg-gray-50 shadow-2xs transition">
                        Today
                    </button>
                </div>
            </div>

            <!-- Row 1: FINANCIAL SUMMARY -->
            <div class="space-y-3">
                <div class="text-xs font-black uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                    <span>FINANCIAL SUMMARY</span>
                    <span class="text-emerald-600 font-bold lowercase">({{ $periodLabel }})</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- TOTAL BILLING -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-blue-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">TOTAL BILLING</span>
                            <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($totalBilling, 2) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Billed in selected period</div>
                        </div>
                    </div>

                    <!-- TOTAL RECEIVED -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-emerald-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">TOTAL RECEIVED</span>
                            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($totalReceived, 2) }}</div>
                            <div class="text-xs font-bold text-emerald-600 mt-1">{{ $collectionPercentage }}% collected</div>
                        </div>
                    </div>

                    <!-- PENDING PAYMENTS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-rose-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">PENDING PAYMENTS</span>
                            <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($pendingPayments, 2) }}</div>
                            <div class="text-xs font-bold text-rose-600 mt-1">Uncollected balance</div>
                        </div>
                    </div>

                    <!-- AVG BOOKING VALUE -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-purple-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">AVG BOOKING VALUE</span>
                            <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($avgBookingValue, 2) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Per booking ticket size</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: BOOKINGS & PITCH OPERATIONS -->
            <div class="space-y-3">
                <div class="text-xs font-black uppercase tracking-wider text-gray-500">
                    BOOKINGS & PITCH OPERATIONS
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- TOTAL BOOKINGS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-cyan-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">TOTAL BOOKINGS</span>
                            <span class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ number_format($confirmedBookingsCount) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Confirmed reservations</div>
                        </div>
                    </div>

                    <!-- PITCH OCCUPANCY -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-amber-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">PITCH OCCUPANCY</span>
                            <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ $pitchOccupancy }}%</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $slotsUtilized }} slot(s) utilized</div>
                        </div>
                    </div>

                    <!-- SHOW-UP RATE -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-teal-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">SHOW-UP RATE</span>
                            <span class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ $showUpRate }}%</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $checkedInOrPaidCount }} players checked-in</div>
                        </div>
                    </div>

                    <!-- CANCELLATIONS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-slate-700 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">CANCELLATIONS</span>
                            <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ number_format($cancellationsCount) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Cancelled bookings {{ $totalRefunded > 0 ? '(₹' . number_format($totalRefunded, 0) . ' refunded)' : '' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports & Financial Statements
                </h1>
                <p class="text-sm text-gray-600 mt-1">
                    Export audit-ready CSV reports, review GST tax liabilities, and monitor cashflow settlements.
                </p>
            </div>

            <!-- Dynamic Export Button for Active Tab -->
            <div class="flex flex-wrap items-center gap-2">
                @if ($activeTab === 'bookings')
                    <a href="{{ route('reports.export-bookings', ['start_date' => $startDate, 'end_date' => $endDate, 'status' => $statusFilter, 'payment_status' => $paymentStatusFilter]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Bookings CSV
                    </a>
                    <a href="{{ route('reports.export-revenue', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Export Revenue Summary CSV
                    </a>
                @elseif ($activeTab === 'passbook')
                    <a href="{{ route('reports.export-passbook', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Wallet Passbook CSV
                    </a>
                @elseif ($activeTab === 'turf-earnings')
                    <a href="{{ route('reports.export-turf-earnings', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Turf Earnings CSV
                    </a>
                @elseif ($activeTab === 'gst')
                    <a href="{{ route('reports.export-gst', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export GSTR-1 CSV
                    </a>
                @elseif ($activeTab === 'cancellations')
                    <a href="{{ route('reports.export-cancellations', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Cancellations CSV
                    </a>
                @elseif ($activeTab === 'commission')
                    <a href="{{ route('reports.export-commission-summary', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Commission Summary CSV
                    </a>
                @elseif ($activeTab === 'payouts')
                    <a href="{{ route('reports.export-payouts', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Payouts CSV
                    </a>
                @endif
            </div>
        </div>

        <!-- Navigation Tabs Strip -->
        <div class="border-b border-gray-200">
            <div class="flex space-x-2 overflow-x-auto pb-2 scrollbar-none">
                <button wire:click="switchTab('bookings')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'bookings' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    Bookings Ledger
                </button>
                <button wire:click="switchTab('passbook')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'passbook' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    Wallet Passbook
                </button>
                <button wire:click="switchTab('turf-earnings')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'turf-earnings' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    Turf Earnings Breakdown
                </button>
                <button wire:click="switchTab('gst')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'gst' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    GST Output Tax (GSTR-1)
                </button>
                <button wire:click="switchTab('cancellations')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'cancellations' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    Cancellations & Refunds
                </button>
                <button wire:click="switchTab('commission')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'commission' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    Monthly Commission Summary
                </button>
                <button wire:click="switchTab('payouts')" type="button"
                    class="px-4 py-2 text-xs font-semibold rounded-lg transition whitespace-nowrap {{ $activeTab === 'payouts' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-gray-600 hover:bg-gray-100 border border-gray-200' }}">
                    Bank Payout History
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-4 border-b border-gray-100">
                <div class="flex gap-2">
                    <button wire:click="setQuickFilter('today')" type="button" 
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition {{ $startDate === Carbon::today('Asia/Kolkata')->toDateString() && $endDate === Carbon::today('Asia/Kolkata')->toDateString() ? 'bg-indigo-50 border-indigo-300 text-indigo-700 ' : 'bg-gray-50 border-gray-200 text-gray-700 ' }}">
                        Today
                    </button>
                    <button wire:click="setQuickFilter('week')" type="button" 
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition bg-gray-50 border-gray-200 text-gray-700 hover:border-gray-300">
                        This Week
                    </button>
                    <button wire:click="setQuickFilter('month')" type="button" 
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition bg-gray-50 border-gray-200 text-gray-700 hover:border-gray-300">
                        This Month
                    </button>
                </div>

                @if (in_array($activeTab, ['passbook', 'commission', 'payouts']))
                    <div class="text-xs text-indigo-700 bg-indigo-50 px-3 py-1.5 rounded-lg border border-indigo-100 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Showing account-wide records for owner wallet
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 {{ $activeTab === 'bookings' ? 'md:grid-cols-4' : 'md:grid-cols-2' }} gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Start Date</label>
                    <input type="date" wire:model.live="startDate" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">End Date</label>
                    <input type="date" wire:model.live="endDate" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                @if ($activeTab === 'bookings')
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Booking Status</label>
                        <select wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All Statuses</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Partially Cancelled">Partially Cancelled</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Payment Status</label>
                        <select wire:model.live="paymentStatusFilter" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="all">All Payment Statuses</option>
                            <option value="Paid">Paid (Full)</option>
                            <option value="Partially Paid">Partially Paid</option>
                            <option value="Unpaid">Unpaid</option>
                        </select>
                    </div>
                @endif
            </div>
        </div>

        <!-- Tab Contents -->
        @if ($activeTab === 'bookings')
            <!-- Summary Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <span class="text-xs font-medium text-gray-500">Total Bookings</span>
                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($totalBookingsCount) }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <span class="text-xs font-medium text-gray-500">Total Value</span>
                    <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($totalAmountSum, 2) }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <span class="text-xs font-medium text-gray-500">Collected Revenue</span>
                    <p class="text-2xl font-bold text-emerald-600 mt-1">₹{{ number_format($totalPaidSum, 2) }}</p>
                </div>
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
                    <span class="text-xs font-medium text-gray-500">Outstanding Balance</span>
                    <p class="text-2xl font-bold text-amber-600 mt-1">₹{{ number_format($totalOutstandingBalance, 2) }}</p>
                </div>
            </div>

            <!-- Bookings Ledger Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Bookings Ledger ({{ $bookings->count() }})</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left text-gray-600">
                        <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                            <tr>
                                <th class="px-4 py-3">Ref</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Turf</th>
                                <th class="px-4 py-3">Date(s)</th>
                                <th class="px-4 py-3">Total</th>
                                <th class="px-4 py-3">Paid</th>
                                <th class="px-4 py-3">Balance</th>
                                <th class="px-4 py-3">Payment Status</th>
                                <th class="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($bookings as $b)
                                @php
                                    $totalAmt = (float)$b->bookingDates->where('status', '!=', 'Cancelled')->sum('amount');
                                    $paidAmt = (float)$b->payments->where('status', 'Success')->sum('amount');
                                    $balAmt = max(0.00, $totalAmt - $paidAmt);
                                @endphp
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-4 py-3 font-semibold text-gray-900">
                                        {{ $b->booking_reference ?? ('#' . $b->id) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $b->user?->name ?? 'Guest' }}</div>
                                        <div class="text-xs text-gray-500">{{ $b->user?->mobile ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3">{{ $b->turf?->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3 text-xs">{{ $b->bookingDates->pluck('booking_date')->implode(', ') }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-900">₹{{ number_format($totalAmt, 2) }}</td>
                                    <td class="px-4 py-3 text-emerald-600 font-semibold">₹{{ number_format($paidAmt, 2) }}</td>
                                    <td class="px-4 py-3 text-amber-600 font-semibold">₹{{ number_format($balAmt, 2) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $b->payment_status === 'Paid' ? 'bg-emerald-100 text-emerald-800 ' : ($b->payment_status === 'Partially Paid' ? 'bg-amber-100 text-amber-800 ' : 'bg-red-100 text-red-800 ') }}">
                                            {{ $b->payment_status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $b->status === 'Confirmed' ? 'bg-blue-100 text-blue-800 ' : 'bg-gray-100 text-gray-800 ' }}">
                                            {{ $b->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                        No booking records found for the selected date range.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif ($activeTab === 'passbook' && $passbookData)
            @include('livewire.turf.reports._passbook-tab', ['data' => $passbookData])
        @elseif ($activeTab === 'turf-earnings' && $turfEarningsData)
            @include('livewire.turf.reports._turf-earnings-tab', ['data' => $turfEarningsData])
        @elseif ($activeTab === 'gst' && $gstData)
            @include('livewire.turf.reports._gst-tab', ['data' => $gstData])
        @elseif ($activeTab === 'cancellations' && $cancellationData)
            @include('livewire.turf.reports._cancellations-tab', ['data' => $cancellationData])
        @elseif ($activeTab === 'commission' && $commissionData)
            @include('livewire.turf.reports._commission-tab', ['data' => $commissionData])
        @elseif ($activeTab === 'payouts' && $payoutData)
            @include('livewire.turf.reports._payouts-tab', ['data' => $payoutData])
        @endif
    </div>
</div>
