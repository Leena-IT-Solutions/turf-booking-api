<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Turf;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\TurfPayout;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    public string $period = 'month'; // 'day', 'month', 'year', 'all'
    public string $cursorDate = '';
    public string $startDate = '';
    public string $endDate = '';

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

        // Fetch period-scoped bookings for the 8 KPI Cards
        $periodBookings = Booking::with(['bookingDates', 'payments', 'turf', 'user'])
            ->where(function ($q) {
                $q->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                  ->orWhereHas('bookingDates', function ($bq) {
                      $bq->whereBetween('booking_date', [$this->startDate, $this->endDate]);
                  });
            })->get();

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
        $totalTurfsCount = Turf::count();
        $activeTurfsCount = $confirmedPeriodBookings->pluck('turf_id')->unique()->filter()->count();
        $activeTurfsPct = $totalTurfsCount > 0 ? round(($activeTurfsCount / $totalTurfsCount) * 100) : 0;

        $distinctPlayersCount = $confirmedPeriodBookings->pluck('user_id')->unique()->filter()->count();
        $repeatPlayersCount = $confirmedPeriodBookings->groupBy('user_id')->filter(fn($g) => $g->count() > 1)->count();

        $cancelledPeriodBookings = $periodBookings->where('status', 'Cancelled');
        $cancellationsCount = $cancelledPeriodBookings->count();
        $cancellationRate = $periodBookings->count() > 0 ? round(($cancellationsCount / $periodBookings->count()) * 100, 1) : 0.0;

        // Existing Dashboard Statistics
        $totalCustomers = User::count();
        $unreadContacts = \Schema::hasTable('contact_messages') ? \App\Models\ContactMessage::where('is_read', false)->count() : 0;

        // Booking Types breakdown
        $dayBookings = $confirmedPeriodBookings->where('booking_type', 'day')->count();
        $longBookings = $confirmedPeriodBookings->where('booking_type', 'long')->count();
        $scatteredBookings = $confirmedPeriodBookings->where('booking_type', 'scattered')->count();

        $totalBreakdown = max(1, $dayBookings + $longBookings + $scatteredBookings);
        $dayPercentage = round(($dayBookings / $totalBreakdown) * 100);
        $longPercentage = round(($longBookings / $totalBreakdown) * 100);
        $scatteredPercentage = round(($scatteredBookings / $totalBreakdown) * 100);

        // Top performing turfs
        $topTurfs = Turf::with('location')
            ->get()
            ->map(function ($turf) {
                $revenue = BookingDate::whereHas('booking', function ($q) use ($turf) {
                    $q->where('turf_id', $turf->id)->where('status', 'Confirmed');
                })->whereBetween('booking_date', [$this->startDate, $this->endDate])
                  ->sum('amount');

                $bookingsCount = Booking::where('turf_id', $turf->id)
                    ->where('status', 'Confirmed')
                    ->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59'])
                    ->count();

                return [
                    'name' => $turf->name,
                    'location' => $turf->location->name ?? 'N/A',
                    'revenue' => $revenue,
                    'bookings_count' => $bookingsCount,
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values()
            ->toArray();

        // Recent Bookings
        $recentBookings = Booking::with(['user', 'turf', 'bookingDates'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($booking) {
                $totalAmount = $booking->bookingDates->sum('amount');
                $datesList = $booking->bookingDates->pluck('booking_date')->map(function ($d) {
                    return \Carbon\Carbon::parse($d)->format('M d');
                })->join(', ');

                return [
                    'id' => $booking->id,
                    'customer_name' => $booking->user->name ?? 'Guest',
                    'customer_email' => $booking->user->email ?? 'N/A',
                    'turf_name' => $booking->turf->name ?? 'Unknown Turf',
                    'booking_type' => ucfirst($booking->booking_type),
                    'dates' => $datesList ?: 'N/A',
                    'total_price' => $totalAmount,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'created_at' => $booking->created_at->diffForHumans(),
                ];
            })
            ->toArray();

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
            'totalCustomers',
            'unreadContacts',
            'dayBookings',
            'longBookings',
            'scatteredBookings',
            'dayPercentage',
            'longPercentage',
            'scatteredPercentage',
            'topTurfs',
            'recentBookings'
        );
    }
}; ?>

<div class="py-6 w-full">
    <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
        
        <!-- Welcome banner -->
        <div class="bg-white p-6 shadow-sm rounded-3xl border border-gray-100 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('SaaS Business Analytics & Performance') }}</h2>
                <p class="text-xs text-gray-500 mt-1.5">{{ __('Real-time platform usage metrics, overall financial statistics, and multi-venue operations review.') }}</p>
            </div>
            <div class="flex items-center gap-2 px-4 py-1.5 bg-indigo-50 text-indigo-600 rounded-full text-xs font-black uppercase tracking-wider">
                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full animate-pulse"></span>
                {{ __('Platform Dashboard') }}
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


            <!-- Contact Messages Stat Card -->
            <a href="{{ route('saas.contact-messages') }}" wire:navigate class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm relative overflow-hidden group block hover:shadow-md transition duration-300">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-rose-50/50 rounded-full group-hover:scale-110 transition duration-300"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ __('Unread Inquiries') }}</span>
                    <span class="p-2 bg-rose-50 text-rose-600 rounded-xl">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0V9a2 2 0 00-2-2M4 11v4a2 2 0 002 2h12a2 2 0 002-2v-4M9 21h6" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4 relative z-10">
                    <span class="text-2xl font-black text-gray-900 font-mono">{{ number_format($unreadContacts) }}</span>
                    <span class="block text-[10px] text-gray-400 mt-1">{{ __('Unread contact us messages') }}</span>
                </div>
            </a>

        </div>

        <!-- Detail breakdowns -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Recent Bookings Table -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6 lg:col-span-2">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100">
                    <h3 class="text-sm font-bold text-gray-900">{{ __('Recent Bookings') }}</h3>
                    <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md uppercase tracking-wider">{{ __('Latest Activity') }}</span>
                </div>

                <div class="mt-4 overflow-x-auto">
                    @if(empty($recentBookings))
                        <div class="text-center py-12 text-gray-500">
                            <p class="text-xs font-semibold">{{ __('No bookings placed yet.') }}</p>
                        </div>
                    @else
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                                    <th class="pb-3">{{ __('Customer') }}</th>
                                    <th class="pb-3">{{ __('Turf / Court') }}</th>
                                    <th class="pb-3">{{ __('Date Details') }}</th>
                                    <th class="pb-3 text-right">{{ __('Amount') }}</th>
                                    <th class="pb-3 text-center">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentBookings as $bk)
                                    <tr class="border-b border-gray-100/50 last:border-0 hover:bg-gray-50/50 transition">
                                        <!-- Customer Info -->
                                        <td class="py-3.5 pr-2">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 text-white font-bold text-xs flex items-center justify-center">
                                                    {{ strtoupper(substr($bk['customer_name'], 0, 2)) }}
                                                </div>
                                                <div>
                                                    <span class="text-xs font-bold text-gray-800 block">{{ $bk['customer_name'] }}</span>
                                                    <span class="text-[9px] text-gray-400 block truncate max-w-[120px]">{{ $bk['customer_email'] }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Turf / Court -->
                                        <td class="py-3.5 px-2">
                                            <span class="text-xs font-bold text-gray-800 block">{{ $bk['turf_name'] }}</span>
                                            <span class="text-[9px] font-semibold text-gray-400 uppercase tracking-wider block mt-0.5">{{ $bk['booking_type'] }}</span>
                                        </td>
                                        <!-- Dates -->
                                        <td class="py-3.5 px-2">
                                            <span class="text-xs font-semibold text-gray-700 block">{{ $bk['dates'] }}</span>
                                            <span class="text-[9px] text-gray-400 block mt-0.5">{{ $bk['created_at'] }}</span>
                                        </td>
                                        <!-- Amount -->
                                        <td class="py-3.5 px-2 text-right">
                                            <span class="text-xs font-bold text-gray-950 font-mono">₹{{ number_format($bk['total_price'], 0) }}</span>
                                        </td>
                                        <!-- Status Badge -->
                                        <td class="py-3.5 pl-2 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $bk['status'] === 'Confirmed' ? 'bg-emerald-50 text-emerald-600 ' : 'bg-rose-50 text-rose-600 ' }}">
                                                {{ $bk['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <!-- Side Widgets: Types and Top Performing Turfs -->
            <div class="space-y-6">
                
                <!-- Booking Types distribution -->
                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100">{{ __('Booking Distribution') }}</h3>
                    
                    <div class="mt-4 space-y-4">
                        <!-- Day Bookings -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-600">
                                <span>{{ __('Day-to-day slots') }}</span>
                                <span class="font-mono text-gray-900">{{ $dayBookings }} ({{ $dayPercentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ $dayPercentage }}%"></div>
                            </div>
                        </div>

                        <!-- Long Bookings -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-600">
                                <span>{{ __('Long bookings') }}</span>
                                <span class="font-mono text-gray-900">{{ $longBookings }} ({{ $longPercentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                <div class="bg-violet-500 h-1.5 rounded-full" style="width: {{ $longPercentage }}%"></div>
                            </div>
                        </div>

                        <!-- Scattered Bookings -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-600">
                                <span>{{ __('Scattered blocks') }}</span>
                                <span class="font-mono text-gray-900">{{ $scatteredBookings }} ({{ $scatteredPercentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-1.5">
                                <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ $scatteredPercentage }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Turfs -->
                <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-900 pb-3 border-b border-gray-100">{{ __('Top Performing Courts') }}</h3>
                    
                    <div class="mt-4 space-y-4">
                        @if(empty($topTurfs))
                            <div class="text-center py-6 text-gray-500">
                                <p class="text-xs font-semibold">{{ __('No performing courts statistics yet.') }}</p>
                            </div>
                        @else
                            @foreach($topTurfs as $idx => $t)
                                <div class="flex items-center justify-between hover:bg-gray-50/50 p-2 rounded-xl transition duration-150">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-lg bg-indigo-50 text-indigo-600 text-xs font-black flex items-center justify-center">
                                            {{ $idx + 1 }}
                                        </div>
                                        <div>
                                            <span class="text-xs font-bold text-gray-800 block">{{ $t['name'] }}</span>
                                            <span class="text-[9px] text-gray-400 block">{{ $t['location'] }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-bold text-gray-900 font-mono block">₹{{ number_format($t['revenue'], 0) }}</span>
                                        <span class="text-[9px] text-gray-400 block">{{ $t['bookings_count'] }} {{ __('bookings') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
