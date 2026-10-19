<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Turf;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\TurfPayout;
use Carbon\Carbon;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $period = 'month'; // 'day', 'month', 'year', 'all'
    public string $cursorDate = '';
    public string $startDate = '';
    public string $endDate = '';
    public string $turfFilter = 'all';
    public string $statusFilter = 'all';
    public string $paymentStatusFilter = 'all';
    public string $search = '';

    public function mount()
    {
        $now = Carbon::now('Asia/Kolkata');
        $this->cursorDate = $now->toDateString();
        $this->updateDateRange();
    }

    public function setPeriod(string $p)
    {
        $this->period = $p;
        $this->resetPage();
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
        $this->resetPage();
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
        $this->resetPage();
        $this->updateDateRange();
    }

    public function goToToday()
    {
        $this->cursorDate = Carbon::now('Asia/Kolkata')->toDateString();
        if ($this->period === 'all') {
            $this->period = 'month';
        }
        $this->resetPage();
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

    public function updatingSearch() { $this->resetPage(); }
    public function updatingTurfFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingPaymentStatusFilter() { $this->resetPage(); }

    public function with(): array
    {
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

        // Fetch period-scoped bookings for 8 KPI Cards
        $periodBookingsQuery = Booking::with(['bookingDates', 'payments', 'turf', 'user'])
            ->where(function ($q) {
                $q->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                  ->orWhereHas('bookingDates', function ($bq) {
                      $bq->whereBetween('booking_date', [$this->startDate, $this->endDate]);
                  });
            });

        if ($this->turfFilter !== 'all') {
            $periodBookingsQuery->where('turf_id', $this->turfFilter);
        }

        $periodBookings = $periodBookingsQuery->get();
        $confirmedPeriodBookings = $periodBookings->where('status', 'Confirmed');

        // Financial KPIs
        $totalGmv = 0.00;
        $saasEarnings = 0.00;
        foreach ($confirmedPeriodBookings as $b) {
            $totalGmv += (float)($b->total_amount ?: $b->bookingDates->where('status', '!=', 'Cancelled')->sum('amount'));
            $saasEarnings += (float)$b->commission_amount + (float)$b->platform_fee;
        }

        $commissionPct = $totalGmv > 0 ? round(($saasEarnings / $totalGmv) * 100, 1) : 0.0;
        $pendingPayouts = (float)TurfPayout::where('status', 'pending')->sum('net_amount');
        $confirmedCount = $confirmedPeriodBookings->count();
        $avgOrderValue = $confirmedCount > 0 ? ($totalGmv / $confirmedCount) : 0.00;

        // Operational KPIs
        $allTurfs = Turf::orderBy('name')->get();
        $totalTurfsCount = $allTurfs->count();
        $activeTurfsCount = $confirmedPeriodBookings->pluck('turf_id')->unique()->filter()->count();
        $activeTurfsPct = $totalTurfsCount > 0 ? round(($activeTurfsCount / $totalTurfsCount) * 100) : 0;

        $distinctPlayersCount = $confirmedPeriodBookings->pluck('user_id')->unique()->filter()->count();
        $repeatPlayersCount = $confirmedPeriodBookings->groupBy('user_id')->filter(fn($g) => $g->count() > 1)->count();

        $cancelledPeriodBookings = $periodBookings->where('status', 'Cancelled');
        $cancellationsCount = $cancelledPeriodBookings->count();
        $cancellationRate = $periodBookings->count() > 0 ? round(($cancellationsCount / $periodBookings->count()) * 100, 1) : 0.0;

        // Ledger Table Query
        $ledgerQuery = Booking::with(['turf', 'user', 'bookingDates', 'payments'])
            ->where(function ($q) {
                $q->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                  ->orWhereHas('bookingDates', function ($bq) {
                      $bq->whereBetween('booking_date', [$this->startDate, $this->endDate]);
                  });
            });

        if ($this->turfFilter !== 'all') {
            $ledgerQuery->where('turf_id', $this->turfFilter);
        }
        if ($this->statusFilter !== 'all') {
            $ledgerQuery->where('status', $this->statusFilter);
        }
        if ($this->paymentStatusFilter !== 'all') {
            $ledgerQuery->where('payment_status', $this->paymentStatusFilter);
        }
        if (!empty($this->search)) {
            $term = '%' . $this->search . '%';
            $ledgerQuery->where(function ($q) use ($term) {
                $q->where('booking_number', 'like', $term)
                  ->orWhereHas('user', function ($uq) use ($term) {
                      $uq->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('mobile', 'like', $term);
                  })
                  ->orWhereHas('turf', function ($tq) use ($term) {
                      $tq->where('name', 'like', $term);
                  });
            });
        }

        $ledgerBookings = $ledgerQuery->orderBy('created_at', 'desc')->paginate(15);

        return compact(
            'periodLabel',
            'totalGmv',
            'saasEarnings',
            'commissionPct',
            'pendingPayouts',
            'avgOrderValue',
            'confirmedCount',
            'activeTurfsCount',
            'totalTurfsCount',
            'activeTurfsPct',
            'distinctPlayersCount',
            'repeatPlayersCount',
            'cancellationsCount',
            'cancellationRate',
            'allTurfs',
            'ledgerBookings'
        );
    }
}; ?>

<div class="py-6 w-full">
    <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Banner -->
        <div class="bg-white p-6 shadow-sm rounded-3xl border border-gray-100 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('Network Financial & Operations Reports') }}
                </h1>
                <p class="text-xs text-gray-500 mt-1.5">{{ __('Complete cross-venue platform analytics, revenue turnover, commission settlements, and ledger logs.') }}</p>
            </div>
            <div class="flex items-center gap-2 px-4 py-1.5 bg-emerald-50 text-emerald-700 rounded-full text-xs font-black uppercase tracking-wider border border-emerald-100">
                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                {{ __('Network Overview') }}
            </div>
        </div>

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

            <!-- Row 1: PLATFORM FINANCIAL SUMMARY -->
            <div class="space-y-3">
                <div class="text-xs font-black uppercase tracking-wider text-gray-500 flex items-center gap-1.5">
                    <span>PLATFORM FINANCIAL SUMMARY</span>
                    <span class="text-emerald-600 font-bold lowercase">({{ $periodLabel }})</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- TOTAL GMV -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-blue-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">TOTAL GMV</span>
                            <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($totalGmv, 2) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Billed across network</div>
                        </div>
                    </div>

                    <!-- SAAS EARNINGS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-emerald-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">SAAS EARNINGS</span>
                            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($saasEarnings, 2) }}</div>
                            <div class="text-xs font-bold text-emerald-600 mt-1">{{ $commissionPct }}% platform take-rate</div>
                        </div>
                    </div>

                    <!-- PENDING PAYOUTS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-rose-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">PENDING PAYOUTS</span>
                            <span class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($pendingPayouts, 2) }}</div>
                            <div class="text-xs font-bold text-rose-600 mt-1">Due to turf owners</div>
                        </div>
                    </div>

                    <!-- AVG ORDER VALUE -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-purple-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">AVG ORDER VALUE</span>
                            <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($avgOrderValue, 2) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Per network booking</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: PLATFORM & NETWORK OPERATIONS -->
            <div class="space-y-3">
                <div class="text-xs font-black uppercase tracking-wider text-gray-500">
                    PLATFORM & NETWORK OPERATIONS
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- NETWORK BOOKINGS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-cyan-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">NETWORK BOOKINGS</span>
                            <span class="w-8 h-8 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ number_format($confirmedCount) }}</div>
                            <div class="text-xs text-gray-400 mt-1">Confirmed reservations</div>
                        </div>
                    </div>

                    <!-- ACTIVE TURFS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-amber-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">ACTIVE TURFS</span>
                            <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ $activeTurfsCount }} / {{ $totalTurfsCount }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $activeTurfsPct }}% active venues</div>
                        </div>
                    </div>

                    <!-- ACTIVE PLAYERS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-teal-500 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">ACTIVE PLAYERS</span>
                            <span class="w-8 h-8 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ number_format($distinctPlayersCount) }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $repeatPlayersCount }} repeat customer(s)</div>
                        </div>
                    </div>

                    <!-- PLATFORM CANCELLATIONS -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm border-l-4 border-l-slate-700 relative transition hover:shadow-md">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">CANCELLATIONS</span>
                            <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </span>
                        </div>
                        <div class="mt-3">
                            <div class="text-2xl sm:text-3xl font-black text-gray-900 font-mono tracking-tight">{{ number_format($cancellationsCount) }}</div>
                            <div class="text-xs text-gray-400 mt-1">{{ $cancellationRate }}% cancellation rate</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ledger Filters & Table -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm space-y-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-bold text-gray-900">{{ __('Network Bookings Ledger') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Detailed record of all transactions for the selected period') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Search input -->
                    <div class="relative w-full sm:w-64">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search customer, booking #..." 
                               class="w-full text-xs rounded-xl border-gray-200 pl-8 pr-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    <!-- Turf Filter -->
                    <select wire:model.live="turfFilter" class="text-xs rounded-xl border-gray-200 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Turfs</option>
                        @foreach ($allTurfs as $t)
                            <option value="{{ $t->id }}">{{ $t->name }}</option>
                        @endforeach
                    </select>

                    <!-- Status Filter -->
                    <select wire:model.live="statusFilter" class="text-xs rounded-xl border-gray-200 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Statuses</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Cancelled">Cancelled</option>
                        <option value="Completed">Completed</option>
                    </select>

                    <!-- Payment Status Filter -->
                    <select wire:model.live="paymentStatusFilter" class="text-xs rounded-xl border-gray-200 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Payments</option>
                        <option value="Paid">Paid</option>
                        <option value="Unpaid">Unpaid</option>
                        <option value="Partially Paid">Partially Paid</option>
                    </select>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-400 uppercase tracking-wider font-semibold text-[10px]">
                            <th class="py-3 px-3">Booking #</th>
                            <th class="py-3 px-3">Venue / Turf</th>
                            <th class="py-3 px-3">Customer</th>
                            <th class="py-3 px-3">Booked Date</th>
                            <th class="py-3 px-3 text-right">Gross Total</th>
                            <th class="py-3 px-3 text-right">Commission</th>
                            <th class="py-3 px-3 text-right">Venue Payout</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Payment</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-gray-700">
                        @forelse ($ledgerBookings as $b)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-3 font-mono font-bold text-gray-900">{{ $b->booking_number }}</td>
                                <td class="py-3.5 px-3 font-semibold text-gray-800">{{ $b->turf->name ?? 'N/A' }}</td>
                                <td class="py-3.5 px-3">
                                    <div class="font-medium text-gray-900">{{ $b->user->name ?? 'Guest' }}</div>
                                    <div class="text-[10px] text-gray-400 font-mono">{{ $b->user->mobile ?? $b->user->email ?? 'N/A' }}</div>
                                </td>
                                <td class="py-3.5 px-3 font-mono text-gray-600">
                                    {{ \Carbon\Carbon::parse($b->created_at)->format('d M Y') }}
                                </td>
                                <td class="py-3.5 px-3 text-right font-mono font-bold text-gray-900">
                                    ₹{{ number_format($b->total_amount, 2) }}
                                </td>
                                <td class="py-3.5 px-3 text-right font-mono text-emerald-600 font-bold">
                                    ₹{{ number_format($b->commission_amount + $b->platform_fee, 2) }}
                                </td>
                                <td class="py-3.5 px-3 text-right font-mono text-indigo-600 font-bold">
                                    ₹{{ number_format($b->turf_payout_amount, 2) }}
                                </td>
                                <td class="py-3.5 px-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $b->status === 'Confirmed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($b->status === 'Cancelled' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-gray-100 text-gray-700') }}">
                                        {{ $b->status }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-3 text-center">
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $b->payment_status === 'Paid' ? 'bg-emerald-50 text-emerald-700' : ($b->payment_status === 'Unpaid' ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700') }}">
                                        {{ $b->payment_status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-gray-400">
                                    <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                    No bookings found in this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Links -->
            @if ($ledgerBookings->hasPages())
                <div class="pt-4 border-t border-gray-100">
                    {{ $ledgerBookings->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
