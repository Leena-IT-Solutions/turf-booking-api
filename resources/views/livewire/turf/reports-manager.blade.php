<?php

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\Turf;
use App\Services\BookingsLedgerService;
use App\Services\ReportService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $activeTab = 'bookings';
    public string $period = 'month'; // 'day', 'month', 'year', 'all'
    public string $cursorDate = '';
    public string $startDate = '';
    public string $endDate = '';

    #[Url]
    public string $statusFilter = 'all';
    #[Url]
    public string $paymentStatusFilter = 'all';
    #[Url]
    public string $channelFilter = 'all'; // 'all', 'online', 'venue'
    #[Url]
    public string $dateBasis = 'session'; // 'session', 'booked'
    #[Url]
    public string $search = '';

    public int $bookingsPerPage = 25;

    public function loadMoreBookings(): void
    {
        $this->bookingsPerPage += 25;
    }

    public function updatedStatusFilter(): void
    {
        $this->bookingsPerPage = 25;
    }

    public function updatedPaymentStatusFilter(): void
    {
        $this->bookingsPerPage = 25;
    }

    public function updatedChannelFilter(): void
    {
        $this->bookingsPerPage = 25;
    }

    public function updatedDateBasis(): void
    {
        $this->bookingsPerPage = 25;
    }

    public function updatedSearch(): void
    {
        $this->bookingsPerPage = 25;
    }

    public int $passbookPerPage = 25;
    public string $passbookGroupFilter = 'all';
    public bool $passbookIncludeZero = false;

    public function loadMorePassbook(): void
    {
        $this->passbookPerPage += 25;
    }

    public function updatedPassbookGroupFilter(): void
    {
        $this->passbookPerPage = 25;
    }

    public function updatedPassbookIncludeZero(): void
    {
        $this->passbookPerPage = 25;
    }

    #[On('global-context-updated')]
    public function refreshContext()
    {
        // Auto refreshes
    }

    public function mount()
    {
        $currentRoute = request()->route() ? request()->route()->getName() : '';
        if ($currentRoute === 'turf.reports.bookings') {
            $this->activeTab = 'bookings';
        } elseif ($currentRoute === 'turf.reports.passbook') {
            $this->activeTab = 'passbook';
        } elseif ($currentRoute === 'turf.reports.turf-earnings') {
            $this->activeTab = 'turf-earnings';
        } elseif ($currentRoute === 'turf.reports.gst') {
            $this->activeTab = 'gst';
        } elseif ($currentRoute === 'turf.reports.cancellations') {
            $this->activeTab = 'cancellations';
        } elseif ($currentRoute === 'turf.reports.commission') {
            $this->activeTab = 'commission';
        } elseif ($currentRoute === 'turf.reports.payouts') {
            $this->activeTab = 'payouts';
        } else {
            $this->activeTab = 'overview';
        }

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
        $this->passbookPerPage = 25;
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
        $this->passbookPerPage = 25;
        if ($range === 'today') {
            $this->setPeriod('day');
        } elseif ($range === 'week') {
            $now = Carbon::now('Asia/Kolkata');
            $this->startDate = $now->copy()->startOfWeek()->toDateString();
            $this->endDate = $now->copy()->endOfWeek()->toDateString();
            $this->period = 'custom';
        } elseif ($range === 'month' || $range === 'this_month') {
            $now = Carbon::now('Asia/Kolkata');
            $this->cursorDate = $now->toDateString();
            $this->setPeriod('month');
        } elseif ($range === 'last_month') {
            $lm = Carbon::now('Asia/Kolkata')->subMonth();
            $this->cursorDate = $lm->toDateString();
            $this->setPeriod('month');
        } elseif ($range === 'last_3_months') {
            $now = Carbon::now('Asia/Kolkata');
            $this->startDate = $now->copy()->subMonths(2)->startOfMonth()->toDateString();
            $this->endDate = $now->copy()->endOfMonth()->toDateString();
            $this->period = 'custom';
        }
    }
}; ?>

<div class="space-y-6">
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

            // Bookings Ledger Service integration
            /** @var BookingsLedgerService $bookingsLedgerService */
            $bookingsLedgerService = app(BookingsLedgerService::class);
            $ledgerScopeTurfIds = ($activeTurfId && in_array($activeTurfId, $manageableTurfIds))
                ? [$activeTurfId]
                : $manageableTurfIds;

            $ledgerFilters = [
                'date_basis' => $this->dateBasis,
                'start' => $this->startDate,
                'end' => $this->endDate,
                'status' => $this->statusFilter,
                'payment_status' => $this->paymentStatusFilter,
                'channel' => $this->channelFilter,
                'search' => $this->search,
            ];

            $ledgerData = null;
            if ($activeTab === 'bookings') {
                $ledgerSummary = $bookingsLedgerService->summary($ledgerScopeTurfIds, $ledgerFilters);
                $ledgerQuery = $bookingsLedgerService->query($ledgerScopeTurfIds, $ledgerFilters);
                $totalLedgerCount = $ledgerSummary['count'];
                $ledgerBookings = $ledgerQuery->take($this->bookingsPerPage)->get();

                $ledgerData = [
                    'summary' => $ledgerSummary,
                    'bookings' => $ledgerBookings,
                    'total' => $totalLedgerCount,
                    'hasMore' => $totalLedgerCount > $ledgerBookings->count(),
                    'showingCount' => $ledgerBookings->count(),
                ];
            }

            // Fetch tab data on demand
            $passbookData = $activeTab === 'passbook'
                ? $reportService->getWalletPassbookSummary(
                    $user,
                    $this->startDate,
                    $this->endDate,
                    $this->passbookPerPage,
                    $this->passbookGroupFilter !== 'all' ? $this->passbookGroupFilter : null,
                    $this->passbookIncludeZero
                )
                : null;
            $turfEarningsData = $activeTab === 'turf-earnings' ? $reportService->getTurfEarningsSummary($user, $activeTurfId, $this->startDate, $this->endDate) : null;
            $gstData = $activeTab === 'gst' ? $reportService->getTurfGstSummary($user, $activeTurfId, $this->startDate, $this->endDate) : null;
            $cancellationData = $activeTab === 'cancellations' ? $reportService->getCancellationSummary($user, $activeTurfId, $this->startDate, $this->endDate) : null;
            $commissionData = $activeTab === 'commission' ? $reportService->getCommissionFeeSummary($user, $this->startDate, $this->endDate) : null;
            $payoutData = $activeTab === 'payouts' ? $reportService->getPayoutHistorySummary($user, $this->startDate, $this->endDate) : null;
        @endphp

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 uppercase tracking-wider mb-1">
                    <a href="{{ route('turf.reports.overview') }}" wire:navigate class="hover:underline">Reports</a>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    @if ($activeTab === 'overview')
                        <span class="text-gray-500 font-medium">Overview</span>
                    @elseif ($activeTab === 'bookings')
                        <span class="text-gray-500 font-medium">Bookings Ledger</span>
                    @elseif ($activeTab === 'passbook')
                        <span class="text-gray-500 font-medium">Wallet Passbook</span>
                    @elseif ($activeTab === 'turf-earnings')
                        <span class="text-gray-500 font-medium">Turf Earnings Breakdown</span>
                    @elseif ($activeTab === 'gst')
                        <span class="text-gray-500 font-medium">GST Output Tax (GSTR-1)</span>
                    @elseif ($activeTab === 'cancellations')
                        <span class="text-gray-500 font-medium">Cancellations & Refunds</span>
                    @elseif ($activeTab === 'commission')
                        <span class="text-gray-500 font-medium">Monthly Commission Summary</span>
                    @elseif ($activeTab === 'payouts')
                        <span class="text-gray-500 font-medium">Bank Payout History</span>
                    @endif
                </div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    @if ($activeTab === 'overview')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Financial & Operations Overview
                    @elseif ($activeTab === 'bookings')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Bookings Ledger
                    @elseif ($activeTab === 'passbook')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Account-Wide Wallet Passbook
                    @elseif ($activeTab === 'turf-earnings')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Turf Earnings Breakdown
                    @elseif ($activeTab === 'gst')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                        GST Output Tax (GSTR-1)
                    @elseif ($activeTab === 'cancellations')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Cancellations & Refunds
                    @elseif ($activeTab === 'commission')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        Monthly Commission Summary
                    @elseif ($activeTab === 'payouts')
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                        Bank Payout History
                    @endif
                </h1>
                <p class="text-sm text-gray-600 mt-1">
                    @if ($activeTab === 'overview')
                        Executive period analytics, billing breakdown, pitch occupancy, and cash collections.
                    @elseif ($activeTab === 'bookings')
                        Audit-ready booking transaction logs with payment modes, status breakdowns, and customer details.
                    @elseif ($activeTab === 'passbook')
                        Account-wide ledger entries for owner wallet balance credits, debits, online booking settlements, and payouts.
                    @elseif ($activeTab === 'turf-earnings')
                        Revenue breakdown across your turfs including gross volume, platform fee, GST collected, and operator net share.
                    @elseif ($activeTab === 'gst')
                        B2C and B2B GST tax collection audit report with CGST, SGST, IGST liability, and SAC compliance.
                    @elseif ($activeTab === 'cancellations')
                        Tracking of cancelled slots, refund amounts, retention fees, and cancellation reasons.
                    @elseif ($activeTab === 'commission')
                        Detailed monthly statement of SaaS platform fees, GST on service commissions, and official tax invoices.
                    @elseif ($activeTab === 'payouts')
                        Comprehensive log of automated bank payouts, UTR numbers, settlement dates, and transfer statuses.
                    @endif
                </p>
            </div>

            <!-- Dynamic Export Button for Active Tab -->
            <div class="flex flex-wrap items-center gap-2">
                @if ($activeTab === 'overview')
                    <a href="{{ route('reports.export-revenue', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Export Revenue Summary CSV
                    </a>
                    <a href="{{ route('reports.export-bookings', ['start_date' => $startDate, 'end_date' => $endDate]) }}" 
                       target="_blank"
                       class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg text-sm transition flex items-center gap-1.5 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export Bookings CSV
                    </a>
                @elseif ($activeTab === 'bookings')
                    <a href="{{ route('reports.export-bookings', [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'date_basis' => $dateBasis,
                        'status' => $statusFilter,
                        'payment_status' => $paymentStatusFilter,
                        'channel' => $channelFilter,
                        'search' => $search,
                    ]) }}" 
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
                    <a href="{{ route('reports.export-passbook', ['start_date' => $startDate, 'end_date' => $endDate, 'group' => $passbookGroupFilter, 'zero' => $passbookIncludeZero ? 1 : 0]) }}" 
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
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
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

        <!-- Period Filter Controller Bar (Universal on All Report Pages) -->
        <div class="bg-white rounded-2xl sm:rounded-3xl p-4 sm:p-5 border border-gray-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
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

        @if ($activeTab === 'overview')
        <!-- Modern Period Analytics Dashboard -->
        <div class="space-y-6">
            <!-- Row 1: FINANCIAL SUMMARY -->
            <div class="space-y-3">
                <div class="text-xs font-black uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                    <span>FINANCIAL SUMMARY</span>
                    <span class="text-emerald-600 font-bold lowercase">({{ $periodLabel }})</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- TOTAL BILLING -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm relative transition hover:shadow-md">
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
        

                <!-- Detailed Reports Hub -->
                <div class="mt-8 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-base font-bold text-gray-900">Financial Reports & Statements</h2>
                            <p class="text-xs text-gray-500">Access specialized ledgers, tax documentation, and settlement audits.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <!-- Bookings Ledger Card -->
                        <a href="{{ route('turf.reports.bookings') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">Bookings Ledger</h3>
                                <p class="text-xs text-gray-500 mt-1">Full audit trail of customer reservations, payment modes, and slot times.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>{{ number_format($confirmedBookingsCount) }} reservations</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>

                        <!-- Wallet Passbook Card -->
                        <a href="{{ route('turf.reports.passbook') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">Wallet Passbook</h3>
                                <p class="text-xs text-gray-500 mt-1">Chronological credit and debit entries for owner master wallet.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>Balance & Settlements</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>

                        <!-- Turf Earnings Breakdown Card -->
                        <a href="{{ route('turf.reports.turf-earnings') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">Turf Earnings</h3>
                                <p class="text-xs text-gray-500 mt-1">Net revenue share, commission, and tax breakdown across each turf.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>Turf-by-turf share</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>

                        <!-- GST Output Tax Card -->
                        <a href="{{ route('turf.reports.gst') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">GST Output Tax</h3>
                                <p class="text-xs text-gray-500 mt-1">Taxable values, CGST, SGST, IGST liabilities for official return filing.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>GSTR-1 audit</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>

                        <!-- Cancellations & Refunds Card -->
                        <a href="{{ route('turf.reports.cancellations') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">Cancellations & Refunds</h3>
                                <p class="text-xs text-gray-500 mt-1">Customer cancellation requests, retained fees, and refund statuses.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>{{ number_format($cancellationsCount) }} cancelled</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>

                        <!-- Monthly Commission Card -->
                        <a href="{{ route('turf.reports.commission') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">Monthly Commission</h3>
                                <p class="text-xs text-gray-500 mt-1">Platform service fee deductions, GST on commissions, and monthly invoices.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>Commission invoices</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>

                        <!-- Bank Payout History Card -->
                        <a href="{{ route('turf.reports.payouts') }}" wire:navigate class="bg-white p-5 rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md hover:border-indigo-300 transition group flex flex-col justify-between">
                            <div>
                                <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center mb-3 group-hover:scale-105 transition">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                                </div>
                                <h3 class="font-bold text-gray-900 group-hover:text-indigo-600 transition text-sm">Bank Payout History</h3>
                                <p class="text-xs text-gray-500 mt-1">Automated bank settlements, UTR tracking, and historical transfer records.</p>
                            </div>
                            <div class="mt-4 pt-3 border-t border-gray-100 flex items-center justify-between text-xs font-semibold text-indigo-600">
                                <span>UTR & Settlements</span>
                                <span class="group-hover:translate-x-1 transition">&rarr;</span>
                            </div>
                        </a>
                    </div>
                </div>
        </div>
        @else
            @if (in_array($activeTab, ['passbook', 'commission', 'payouts']))
                <div class="text-xs text-indigo-700 bg-indigo-50/80 px-4 py-2.5 rounded-xl border border-indigo-100 flex items-center gap-2 mb-4">
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Showing account-wide records for owner wallet across <strong>{{ $periodLabel }}</strong>.</span>
                </div>
            @endif

            @if ($activeTab === 'bookings' && $ledgerData)
                <!-- Bookings Sub-filters (Channel, Date Basis, Status, Payment, Search) -->
                <div class="bg-white rounded-2xl p-4 border border-gray-100 shadow-2xs mb-6 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <!-- Channel Filter -->
                            <div class="flex items-center gap-1.5">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Channel:</label>
                                <select wire:model.live="channelFilter" class="rounded-xl border-gray-200 text-xs font-semibold text-gray-700 py-1.5 pl-3 pr-8 focus:border-emerald-500 focus:ring-emerald-500 bg-gray-50/50">
                                    <option value="all">All channels</option>
                                    <option value="online">By App (online gateway)</option>
                                    <option value="venue">At Location (Cash / UPI / Counter)</option>
                                </select>
                            </div>

                            <!-- Date Basis Filter -->
                            <div class="flex items-center gap-1.5">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Date Basis:</label>
                                <select wire:model.live="dateBasis" class="rounded-xl border-gray-200 text-xs font-semibold text-gray-700 py-1.5 pl-3 pr-8 focus:border-emerald-500 focus:ring-emerald-500 bg-gray-50/50">
                                    <option value="session">Session Date</option>
                                    <option value="booked">Booking Date</option>
                                </select>
                            </div>

                            <!-- Status Filter -->
                            <div class="flex items-center gap-1.5">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Status:</label>
                                <select wire:model.live="statusFilter" class="rounded-xl border-gray-200 text-xs font-semibold text-gray-700 py-1.5 pl-3 pr-8 focus:border-emerald-500 focus:ring-emerald-500 bg-gray-50/50">
                                    <option value="all">All Statuses</option>
                                    <option value="Confirmed">Confirmed</option>
                                    <option value="Partially Cancelled">Partially Cancelled</option>
                                    <option value="Cancelled">Cancelled</option>
                                </select>
                            </div>

                            <!-- Payment Status Filter -->
                            <div class="flex items-center gap-1.5">
                                <label class="text-xs font-bold text-gray-500 uppercase tracking-wider">Payment:</label>
                                <select wire:model.live="paymentStatusFilter" class="rounded-xl border-gray-200 text-xs font-semibold text-gray-700 py-1.5 pl-3 pr-8 focus:border-emerald-500 focus:ring-emerald-500 bg-gray-50/50">
                                    <option value="all">All Payment Statuses</option>
                                    <option value="Paid">Paid (Full)</option>
                                    <option value="Partially Paid">Partially Paid</option>
                                    <option value="Unpaid">Unpaid</option>
                                </select>
                            </div>
                        </div>

                        <!-- Search Box -->
                        <div class="relative w-full sm:w-64">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search ref, player, mobile..." class="w-full pl-9 pr-3 py-1.5 rounded-xl border border-gray-200 text-xs text-gray-700 placeholder-gray-400 focus:border-emerald-500 focus:ring-emerald-500 bg-gray-50/50">
                        </div>
                    </div>

                    <div class="text-xs text-gray-500 pt-1 border-t border-gray-100 flex items-center justify-between">
                        <div>
                            Showing <strong>{{ $ledgerData['showingCount'] }}</strong> of <strong>{{ number_format($ledgerData['total']) }}</strong> bookings for <strong>{{ $periodLabel }}</strong>
                        </div>
                    </div>
                </div>
            @endif

        <!-- Tab Contents -->
        @if ($activeTab === 'bookings' && $ledgerData)
            <!-- Summary Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Card 1: Bookings -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-medium text-gray-500">Bookings</span>
                        <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format($ledgerData['summary']['count']) }}</p>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2 pt-2 border-t border-gray-100">
                        Online {{ $ledgerData['summary']['by_channel_count']['online'] }} · Venue {{ $ledgerData['summary']['by_channel_count']['venue'] }} · Mixed {{ $ledgerData['summary']['by_channel_count']['mixed'] }}
                    </p>
                </div>

                <!-- Card 2: Booking value -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-medium text-gray-500">Booking Value</span>
                        <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($ledgerData['summary']['booking_value'], 2) }}</p>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2 pt-2 border-t border-gray-100">
                        Original total order value
                    </p>
                </div>

                <!-- Card 3: Collected -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-medium text-gray-500">Collected</span>
                        <p class="text-2xl font-bold text-emerald-600 mt-1">₹{{ number_format($ledgerData['summary']['net_collected'], 2) }}</p>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2 pt-2 border-t border-gray-100 leading-relaxed" title="App ₹{{ number_format($ledgerData['summary']['online_collected'], 2) }} · Venue ₹{{ number_format($ledgerData['summary']['venue_collected'], 2) }} (Cash ₹{{ number_format($ledgerData['summary']['venue_split']['Cash'], 2) }} · UPI ₹{{ number_format($ledgerData['summary']['venue_split']['UPI'], 2) }} · Other ₹{{ number_format($ledgerData['summary']['venue_split']['Other'], 2) }}) · Refunded −₹{{ number_format($ledgerData['summary']['refunded'], 2) }}">
                        App ₹{{ number_format($ledgerData['summary']['online_collected'], 2) }} · Venue ₹{{ number_format($ledgerData['summary']['venue_collected'], 2) }}
                        <span class="text-gray-400">(Cash ₹{{ number_format($ledgerData['summary']['venue_split']['Cash'], 2) }} · UPI ₹{{ number_format($ledgerData['summary']['venue_split']['UPI'], 2) }} · Other ₹{{ number_format($ledgerData['summary']['venue_split']['Other'], 2) }})</span> · Refunded −₹{{ number_format($ledgerData['summary']['refunded'], 2) }}
                        @if ($ledgerData['summary']['refund_pending'] > 0)
                            · <span class="text-amber-600 font-semibold">Refund pending ₹{{ number_format($ledgerData['summary']['refund_pending'], 2) }}</span>
                        @endif
                    </p>
                </div>

                <!-- Card 4: Due at venue -->
                <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-xs flex flex-col justify-between">
                    <div>
                        <span class="text-xs font-medium text-gray-500">Due at Venue</span>
                        <p class="text-2xl font-bold {{ $ledgerData['summary']['due_at_venue'] > 0 ? 'text-amber-600' : 'text-gray-900' }} mt-1">
                            ₹{{ number_format($ledgerData['summary']['due_at_venue'], 2) }}
                        </p>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2 pt-2 border-t border-gray-100">
                        Balance to collect on active bookings
                    </p>
                </div>
            </div>

            <!-- Bookings Ledger Container -->
            <div class="bg-white rounded-xl shadow-xs border border-gray-200 overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 text-sm">
                        Bookings Ledger (Showing {{ $ledgerData['showingCount'] }} of {{ number_format($ledgerData['total']) }})
                    </h3>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden sm:block overflow-x-auto ledger-table">
                    <table class="w-full text-left text-xs text-gray-600">
                        <thead class="text-[11px] uppercase bg-gray-50 text-gray-700 tracking-wider">
                            <tr>
                                <th class="px-3.5 py-3 font-semibold">Booking</th>
                                <th class="px-3.5 py-3 font-semibold">Player</th>
                                <th class="px-3.5 py-3 font-semibold">Turf</th>
                                <th class="px-3.5 py-3 font-semibold">Session(s)</th>
                                <th class="px-3.5 py-3 font-semibold">Channel</th>
                                <th class="px-3.5 py-3 font-semibold">Value</th>
                                <th class="px-3.5 py-3 font-semibold">Paid</th>
                                <th class="px-3.5 py-3 font-semibold">Refunded</th>
                                <th class="px-3.5 py-3 font-semibold">Net</th>
                                <th class="px-3.5 py-3 font-semibold">Due</th>
                                <th class="px-3.5 py-3 font-semibold">Discount</th>
                                <th class="px-3.5 py-3 font-semibold">Booked by</th>
                                <th class="px-3.5 py-3 font-semibold">Status</th>
                                <th class="px-3.5 py-3 font-semibold text-right">Invoice</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($ledgerData['bookings'] as $b)
                                @php
                                    $metrics = $bookingsLedgerService->rowMetrics($b);
                                    $channelBadge = match($metrics['channel']) {
                                        'online' => ['label' => 'App', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                                        'venue' => ['label' => 'Venue', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                        'mixed' => ['label' => 'Mixed', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
                                        default => ['label' => '—', 'class' => 'bg-gray-50 text-gray-600 border-gray-200'],
                                    };
                                    $paidParts = [];
                                    if ($metrics['online_paid'] > 0) $paidParts[] = 'App ₹' . number_format($metrics['online_paid'], 2);
                                    if ($metrics['venue_paid'] > 0) $paidParts[] = 'Venue ₹' . number_format($metrics['venue_paid'], 2);
                                    $paidText = !empty($paidParts) ? implode(' · ', $paidParts) : '—';
                                @endphp
                                <tr class="hover:bg-gray-50/60 transition">
                                    <td class="px-3.5 py-3 font-semibold">
                                        <a href="{{ route('turf.bookings', ['search' => $b->booking_reference]) }}" class="text-indigo-600 hover:text-indigo-800 underline font-mono">
                                            {{ $b->booking_reference }}
                                        </a>
                                    </td>
                                    <td class="px-3.5 py-3">
                                        <div class="font-medium text-gray-900">{{ $b->user?->name ?? 'Guest' }}</div>
                                        <div class="text-[11px] text-gray-500 font-mono">{{ $b->user?->mobile ?? '—' }}</div>
                                    </td>
                                    <td class="px-3.5 py-3 font-medium text-gray-800">{{ $b->turf?->name ?? 'N/A' }}</td>
                                    <td class="px-3.5 py-3">
                                        <div class="space-y-0.5">
                                            @foreach ($metrics['sessions'] as $sessionLine)
                                                <div class="{{ str_contains($sessionLine, '(cancelled)') ? 'text-gray-400 line-through' : 'text-gray-800' }}">
                                                    {{ $sessionLine }}
                                                </div>
                                            @endforeach
                                            <div class="text-[10px] text-gray-500 font-semibold">{{ $metrics['hours_text'] }}</div>
                                        </div>
                                    </td>
                                    <td class="px-3.5 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold border {{ $channelBadge['class'] }}">
                                            {{ $channelBadge['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-3.5 py-3 font-semibold text-gray-900 font-mono">
                                        ₹{{ number_format($metrics['booking_value'], 2) }}
                                    </td>
                                    <td class="px-3.5 py-3 font-medium text-emerald-700">
                                        {{ $paidText }}
                                    </td>
                                    <td class="px-3.5 py-3 font-mono">
                                        @if ($metrics['refunded'] > 0)
                                            <span class="text-rose-600 font-semibold">−₹{{ number_format($metrics['refunded'], 2) }}</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3.5 py-3 font-bold text-gray-900 font-mono">
                                        ₹{{ number_format($metrics['net_paid'], 2) }}
                                    </td>
                                    <td class="px-3.5 py-3 font-mono">
                                        @if ($metrics['balance_due'] > 0)
                                            <span class="text-amber-600 font-bold">₹{{ number_format($metrics['balance_due'], 2) }}</span>
                                        @else
                                            <span class="text-gray-400">₹0.00</span>
                                        @endif
                                    </td>
                                    <td class="px-3.5 py-3">
                                        @if ($metrics['discount'] > 0)
                                            <div class="font-medium text-gray-900">₹{{ number_format($metrics['discount'], 2) }}</div>
                                            @if (!empty($metrics['coupon_codes']))
                                                <div class="text-[10px] text-gray-500 font-mono">{{ implode(', ', $metrics['coupon_codes']) }}</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-3.5 py-3 text-gray-700">
                                        {{ $metrics['booked_by_label'] }}
                                    </td>
                                    <td class="px-3.5 py-3 space-y-1">
                                        <div>
                                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded-full {{ $b->status === 'Confirmed' ? 'bg-blue-100 text-blue-800' : ($b->status === 'Partially Cancelled' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800') }}">
                                                {{ $b->status }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold rounded-full {{ $b->payment_status === 'Paid' ? 'bg-emerald-100 text-emerald-800' : ($b->payment_status === 'Partially Paid' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                                {{ $b->payment_status }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3.5 py-3 text-right">
                                        <a href="{{ route('turf.bookings.invoice', $b) }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                            Invoice
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="px-4 py-8 text-center text-gray-500">
                                        No booking records found for the selected filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Phone Cards View (below sm) -->
                <div class="sm:hidden divide-y divide-gray-100">
                    @forelse ($ledgerData['bookings'] as $b)
                        @php
                            $metrics = $bookingsLedgerService->rowMetrics($b);
                            $channelBadge = match($metrics['channel']) {
                                'online' => ['label' => 'App', 'class' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                                'venue' => ['label' => 'Venue', 'class' => 'bg-amber-50 text-amber-700 border-amber-200'],
                                'mixed' => ['label' => 'Mixed', 'class' => 'bg-purple-50 text-purple-700 border-purple-200'],
                                default => ['label' => '—', 'class' => 'bg-gray-50 text-gray-600 border-gray-200'],
                            };
                            $paidParts = [];
                            if ($metrics['online_paid'] > 0) $paidParts[] = 'App ₹' . number_format($metrics['online_paid'], 2);
                            if ($metrics['venue_paid'] > 0) $paidParts[] = 'Venue ₹' . number_format($metrics['venue_paid'], 2);
                            $paidText = !empty($paidParts) ? implode(' · ', $paidParts) : '—';
                        @endphp
                        <div class="p-4 ledger-card space-y-2.5">
                            <!-- Line 1: Reference and status badges -->
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('turf.bookings', ['search' => $b->booking_reference]) }}" class="font-bold text-sm text-indigo-600 hover:text-indigo-800 underline font-mono">
                                    {{ $b->booking_reference }}
                                </a>
                                <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $b->status === 'Confirmed' ? 'bg-blue-100 text-blue-800' : ($b->status === 'Partially Cancelled' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-800') }}">
                                        {{ $b->status }}
                                    </span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full {{ $b->payment_status === 'Paid' ? 'bg-emerald-100 text-emerald-800' : ($b->payment_status === 'Partially Paid' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">
                                        {{ $b->payment_status }}
                                    </span>
                                </div>
                            </div>

                            <!-- Line 2: Player & Turf -->
                            <div class="text-xs text-gray-700 flex items-center justify-between">
                                <div>
                                    <span class="font-semibold text-gray-900">{{ $b->user?->name ?? 'Guest' }}</span>
                                    <span class="text-gray-400 font-mono text-[11px]">({{ $b->user?->mobile ?? '—' }})</span>
                                </div>
                                <div class="text-gray-500 font-medium">{{ $b->turf?->name ?? 'N/A' }}</div>
                            </div>

                            <!-- Line 3: Sessions -->
                            <div class="text-xs bg-gray-50/80 p-2.5 rounded-lg space-y-0.5">
                                @foreach ($metrics['sessions'] as $sessionLine)
                                    <div class="{{ str_contains($sessionLine, '(cancelled)') ? 'text-gray-400 line-through' : 'text-gray-700' }}">
                                        {{ $sessionLine }}
                                    </div>
                                @endforeach
                                <div class="text-[10px] text-gray-500 font-semibold mt-1">{{ $metrics['hours_text'] }}</div>
                            </div>

                            <!-- Line 4: Channel badge with Paid / Refunded / Net / Due on one line -->
                            <div class="flex items-center justify-between gap-2 text-xs flex-wrap pt-1 border-t border-gray-100">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-semibold border {{ $channelBadge['class'] }}">
                                        {{ $channelBadge['label'] }}
                                    </span>
                                    <span class="text-gray-600">Paid: <strong class="text-emerald-700 font-medium">{{ $paidText }}</strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($metrics['refunded'] > 0)
                                        <span class="text-rose-600 font-medium">Ref: −₹{{ number_format($metrics['refunded'], 2) }}</span>
                                    @endif
                                    <span class="text-gray-900 font-bold">Net: ₹{{ number_format($metrics['net_paid'], 2) }}</span>
                                    @if ($metrics['balance_due'] > 0)
                                        <span class="text-amber-700 font-bold bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
                                            Due: ₹{{ number_format($metrics['balance_due'], 2) }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <!-- Line 5: Invoice link and booked by -->
                            <div class="pt-1 flex items-center justify-between text-xs text-gray-500 border-t border-gray-50">
                                <span>Booked by: <strong>{{ $metrics['booked_by_label'] }}</strong></span>
                                <a href="{{ route('turf.bookings.invoice', $b) }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:text-indigo-800 font-medium">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Invoice
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-gray-500 text-xs">
                            No booking records found for the selected filters.
                        </div>
                    @endforelse
                </div>

                <!-- Footer / Paging -->
                <div class="p-4 border-t border-gray-100 bg-gray-50/50">
                    @if ($ledgerData['hasMore'])
                        <div class="text-center">
                            <button
                                type="button"
                                wire:click="loadMoreBookings"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition"
                            >
                                <span wire:loading.remove wire:target="loadMoreBookings">Load more</span>
                                <span wire:loading wire:target="loadMoreBookings" class="inline-flex items-center gap-1.5">
                                    <span class="animate-spin inline-block w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full"></span>
                                    Loading older bookings...
                                </span>
                            </button>
                        </div>
                    @else
                        <div class="text-center text-xs text-gray-500 font-medium">
                            End of ledger · {{ number_format($ledgerData['total']) }} bookings
                        </div>
                    @endif
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
        @endif
</div>
