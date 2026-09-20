<?php

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\CommissionSettlement;
use App\Models\Payment;
use App\Models\Turf;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $activeTab = 'bookings'; // 'bookings', 'cancellations', 'turfs', 'settlements'
    public string $search = '';
    public string $paymentMethodFilter = 'all';

    // Timeframe filter state: default 'month'
    public string $filterMode = 'month'; // 'day', 'month', 'year'
    public string $selectedDate = '';

    public function mount(): void
    {
        if (empty($this->selectedDate)) {
            $this->selectedDate = Carbon::today()->format('Y-m-d');
        }
    }

    public function setFilterMode(string $mode): void
    {
        $this->filterMode = $mode;
        $this->resetPage();
    }

    public function prevPeriod(): void
    {
        $dt = Carbon::parse($this->selectedDate ?: Carbon::today());
        if ($this->filterMode === 'day') {
            $this->selectedDate = $dt->subDay()->format('Y-m-d');
        } elseif ($this->filterMode === 'month') {
            $this->selectedDate = $dt->subMonth()->format('Y-m-d');
        } elseif ($this->filterMode === 'year') {
            $this->selectedDate = $dt->subYear()->format('Y-m-d');
        }
        $this->resetPage();
    }

    public function nextPeriod(): void
    {
        $dt = Carbon::parse($this->selectedDate ?: Carbon::today());
        if ($this->filterMode === 'day') {
            $this->selectedDate = $dt->addDay()->format('Y-m-d');
        } elseif ($this->filterMode === 'month') {
            $this->selectedDate = $dt->addMonth()->format('Y-m-d');
        } elseif ($this->filterMode === 'year') {
            $this->selectedDate = $dt->addYear()->format('Y-m-d');
        }
        $this->resetPage();
    }

    public function resetToCurrent(): void
    {
        $this->selectedDate = Carbon::today()->format('Y-m-d');
        $this->resetPage();
    }

    public function updatingSearch() { $this->resetPage(); }
    public function updatingPaymentMethodFilter() { $this->resetPage(); }
    public function updatingActiveTab() { $this->resetPage(); }

    public function with(): array
    {
        $currentDate = Carbon::parse($this->selectedDate ?: Carbon::today());

        if ($this->filterMode === 'day') {
            $startDate = $currentDate->copy()->startOfDay();
            $endDate = $currentDate->copy()->endOfDay();
            $periodLabel = $currentDate->format('d M Y');
            $isCurrent = $currentDate->isToday();
            $badgeLabel = 'Today';
        } elseif ($this->filterMode === 'year') {
            $startDate = $currentDate->copy()->startOfYear();
            $endDate = $currentDate->copy()->endOfYear();
            $periodLabel = $currentDate->format('Y');
            $isCurrent = $currentDate->isCurrentYear();
            $badgeLabel = 'This Year';
        } else { // default 'month'
            $startDate = $currentDate->copy()->startOfMonth();
            $endDate = $currentDate->copy()->endOfMonth();
            $periodLabel = $currentDate->format('F Y');
            $isCurrent = $currentDate->isCurrentMonth() && $currentDate->isCurrentYear();
            $badgeLabel = 'This Month';
        }

        // 1. Commission earned from successful booking payments in timeframe
        $totalCommissionEarned = (float) Payment::where('status', 'Success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('commission_amount');
        $totalCommissionGst = (float) Payment::where('status', 'Success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('commission_gst_amount');

        // 2. Platform Fees collected from successful bookings in timeframe
        $totalPlatformFeeEarned = (float) Booking::whereHas('payments', function ($q) {
                $q->where('status', 'Success');
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('platform_fee');

        // 3. Pure SaaS Cancellation Fee charge (strictly SaaS cancellation fee, excluding retained platform fee)
        $totalCancellationFeeEarned = (float) BookingCancellation::whereBetween('created_at', [$startDate, $endDate])
            ->sum('saas_cancellation_fee');
        if ($totalCancellationFeeEarned <= 0 && BookingCancellation::whereBetween('created_at', [$startDate, $endDate])->count() > 0) {
            $totalCancellationFeeEarned = (float) BookingCancellation::whereBetween('created_at', [$startDate, $endDate])->get()->sum(function($c) {
                return (float) ($c->deductions_breakup['saas_cancellation_fee'] ?? 0);
            });
        }

        // 4. Combined Total SaaS Booking & Cancellation Revenue
        $totalCombinedSaaSEarnings = round($totalCommissionEarned + $totalPlatformFeeEarned + $totalCancellationFeeEarned, 2);

        $totalGrossBookingVolume = (float) Payment::where('status', 'Success')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');
        $totalCommissionDue = abs((float) User::where('commission_wallet_balance', '<', 0)->sum('commission_wallet_balance'));

        // 1. Commission by Bookings Query (scoped to timeframe)
        $bookingsQuery = Booking::with(['turf.location.user', 'user', 'payments'])
            ->whereHas('payments', function ($q) {
                $q->where('status', 'Success');
            })
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest();

        if ($this->search !== '' && $this->activeTab === 'bookings') {
            $term = '%' . $this->search . '%';
            $bookingsQuery->where(function ($q) use ($term) {
                $q->where('booking_reference', 'like', $term)
                  ->orWhere('id', 'like', $term)
                  ->orWhereHas('user', function ($uq) use ($term) {
                      $uq->where('name', 'like', $term)->orWhere('phone', 'like', $term);
                  })
                  ->orWhereHas('turf', function ($tq) use ($term) {
                      $tq->where('name', 'like', $term);
                  });
            });
        }

        if ($this->paymentMethodFilter !== 'all') {
            $bookingsQuery->whereHas('payments', function ($pq) {
                $pq->where('payment_method', $this->paymentMethodFilter)->where('status', 'Success');
            });
        }

        $bookings = $bookingsQuery->paginate(15);

        // 2. Cancellations Revenue Query (scoped to timeframe)
        $cancellationsQuery = BookingCancellation::with(['booking.turf.location.user', 'booking.user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->latest();

        if ($this->search !== '' && $this->activeTab === 'cancellations') {
            $term = '%' . $this->search . '%';
            $cancellationsQuery->where(function ($q) use ($term) {
                $q->where('reason', 'like', $term)
                  ->orWhereHas('booking', function ($bq) use ($term) {
                      $bq->where('booking_reference', 'like', $term)
                         ->orWhere('id', 'like', $term);
                  })
                  ->orWhereHas('booking.turf', function ($tq) use ($term) {
                      $tq->where('name', 'like', $term);
                  });
            });
        }

        $cancellations = $cancellationsQuery->paginate(15);

        // 3. Turf Managers Summary Query
        $managersQuery = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['turf-admin', 'manager', 'admin']);
            })
            ->with(['locations.turfs'])
            ->orderBy('name');

        if ($this->search !== '' && $this->activeTab === 'turfs') {
            $term = '%' . $this->search . '%';
            $managersQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
            });
        }

        $managers = $managersQuery->paginate(15);

        // 4. Commission Settlements Query (Debts paid by managers)
        $settlementsQuery = CommissionSettlement::with('user')->latest();
        if ($this->search !== '' && $this->activeTab === 'settlements') {
            $term = '%' . $this->search . '%';
            $settlementsQuery->where(function ($q) use ($term) {
                $q->where('razorpay_payment_id', 'like', $term)
                  ->orWhereHas('user', function ($uq) use ($term) {
                      $uq->where('name', 'like', $term);
                  });
            });
        }
        $settlements = $settlementsQuery->paginate(15);

        return [
            'filterMode' => $this->filterMode,
            'periodLabel' => $periodLabel,
            'isCurrent' => $isCurrent,
            'badgeLabel' => $badgeLabel,
            'totalCombinedSaaSEarnings' => $totalCombinedSaaSEarnings,
            'totalCommissionEarned' => $totalCommissionEarned,
            'totalCommissionGst' => $totalCommissionGst,
            'totalPlatformFeeEarned' => $totalPlatformFeeEarned,
            'totalCancellationFeeEarned' => $totalCancellationFeeEarned,
            'totalGrossBookingVolume' => $totalGrossBookingVolume,
            'totalCommissionDue' => $totalCommissionDue,
            'bookings' => $bookings,
            'cancellations' => $cancellations,
            'managers' => $managers,
            'settlements' => $settlements,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Platform Earnings & Commission</h1>
                <p class="text-xs text-gray-500">Comprehensive breakdown of platform revenue: Booking Commissions, Platform Fees, and Cancellation Charges.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('saas.payouts') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-900 hover:bg-black active:scale-95 text-white text-xs font-bold rounded-2xl shadow-xs transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                <span>View Turf Payouts</span>
            </a>
        </div>
    </div>

    <!-- PERIOD FILTER BAR (ABOVE TILES) -->
    <div class="bg-white p-4 rounded-3xl border border-gray-200 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <!-- Filter Mode Selector: Day (Date) | Month | Year -->
        <div class="inline-flex items-center p-1 bg-gray-100 rounded-2xl border border-gray-200/70 self-start md:self-auto">
            <button wire:click="setFilterMode('day')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $filterMode === 'day' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}">
                Day (Date)
            </button>
            <button wire:click="setFilterMode('month')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $filterMode === 'month' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}">
                Month
            </button>
            <button wire:click="setFilterMode('year')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $filterMode === 'year' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-900' }}">
                Year
            </button>
        </div>

        <!-- Period Navigator (Previous / Next / Current Indicator) -->
        <div class="flex items-center gap-2 self-stretch md:self-auto justify-between md:justify-end">
            <button wire:click="prevPeriod" type="button" title="Previous"
                class="px-3 py-2 text-xs font-bold rounded-xl bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                <span class="hidden sm:inline">Previous</span>
            </button>

            <div class="flex items-center gap-2 px-4 py-2 rounded-xl bg-gray-50 border border-gray-200 min-w-[170px] sm:min-w-[210px] justify-center text-center">
                <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span class="text-xs font-black text-gray-900 tracking-tight">
                    {{ $periodLabel }}
                </span>
                @if ($isCurrent)
                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-emerald-100 text-emerald-800">
                        {{ $badgeLabel }}
                    </span>
                @endif
            </div>

            <button wire:click="nextPeriod" type="button" title="Next"
                class="px-3 py-2 text-xs font-bold rounded-xl bg-gray-50 hover:bg-gray-100 border border-gray-200 text-gray-700 transition flex items-center gap-1.5 cursor-pointer active:scale-95">
                <span class="hidden sm:inline">Next</span>
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
            </button>

            @if (!$isCurrent)
                <button wire:click="resetToCurrent" type="button"
                    class="px-3 py-2 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 transition cursor-pointer">
                    Current
                </button>
            @endif
        </div>
    </div>

    <!-- 4 GRAND STATS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Combined Total Earnings -->
        <div class="bg-white p-6 rounded-3xl border border-emerald-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">TOTAL SAAS EARNINGS</span>
                <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-emerald-600">
                ₹{{ number_format($totalCombinedSaaSEarnings, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Commission + Platform Fee + Cancellation Fee</p>
        </div>

        <!-- 2. Booking Commission -->
        <div class="bg-white p-6 rounded-3xl border border-indigo-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">BOOKING COMMISSION</span>
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-indigo-600">
                ₹{{ number_format($totalCommissionEarned, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Platform % fee on slots (GST: ₹{{ number_format($totalCommissionGst, 2) }})</p>
        </div>

        <!-- 3. Platform Fees -->
        <div class="bg-white p-6 rounded-3xl border border-purple-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">PLATFORM FEES</span>
                <span class="p-2 bg-purple-50 text-purple-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-purple-600">
                ₹{{ number_format($totalPlatformFeeEarned, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Direct booking platform fees collected</p>
        </div>

        <!-- 4. Cancellation Fees -->
        <div class="bg-white p-6 rounded-3xl border border-amber-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">CANCELLATION FEES</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-amber-600">
                ₹{{ number_format($totalCancellationFeeEarned, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">SaaS cancellation charges on refunds</p>
        </div>
    </div>

    <!-- TABS & TABLE CARD -->
    <div class="bg-white rounded-3xl border border-gray-200 shadow-xs overflow-hidden">
        <!-- Control Bar: Tabs & Search/Filters -->
        <div class="p-6 border-b border-gray-100 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Segmented Tabs (Never wraps onto 2 rows; horizontally scrollable on mobile) -->
                <div class="flex items-center gap-1.5 p-1 bg-gray-100 rounded-2xl border border-gray-200/70 overflow-x-auto max-w-full whitespace-nowrap self-start">
                    <button wire:click="$set('activeTab', 'bookings')" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'bookings' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-800' }}">
                        <svg class="w-3.5 h-3.5 {{ $activeTab === 'bookings' ? 'text-emerald-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span>Bookings</span>
                    </button>
                    <button wire:click="$set('activeTab', 'cancellations')" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'cancellations' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-800' }}">
                        <svg class="w-3.5 h-3.5 {{ $activeTab === 'cancellations' ? 'text-rose-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Cancellations</span>
                    </button>
                    <button wire:click="$set('activeTab', 'turfs')" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'turfs' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-800' }}">
                        <svg class="w-3.5 h-3.5 {{ $activeTab === 'turfs' ? 'text-indigo-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <span>Turf Managers</span>
                    </button>
                    <button wire:click="$set('activeTab', 'settlements')" type="button"
                        class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'settlements' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-800' }}">
                        <svg class="w-3.5 h-3.5 {{ $activeTab === 'settlements' ? 'text-amber-600' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        <span>Debt Settlements</span>
                    </button>
                </div>

                <!-- Filters & Search Toolbar -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <div class="relative min-w-[180px] flex-1 sm:flex-initial">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search records..."
                               class="w-full sm:w-56 h-10 pl-9 pr-3 text-xs rounded-xl border border-gray-200 bg-gray-50/60 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>

                    @if ($activeTab === 'bookings')
                        <select wire:model.live="paymentMethodFilter" class="h-10 py-2 px-3 text-xs rounded-xl border border-gray-200 bg-gray-50/60 focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 transition cursor-pointer">
                            <option value="all">All Payment Methods</option>
                            <option value="App">Online (App)</option>
                            <option value="Pay at Venue">Pay at Venue (Cash)</option>
                        </select>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6">
        @if ($activeTab === 'bookings')
            <!-- Bookings Revenue Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Booking Ref</th>
                            <th class="py-3 px-4">Turf Name</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Booking Total</th>
                            <th class="py-3 px-4">Commission %</th>
                            <th class="py-3 px-4">Commission Earned</th>
                            <th class="py-3 px-4">Platform Fee</th>
                            <th class="py-3 px-4 text-emerald-700">Total SaaS Cut</th>
                            <th class="py-3 px-4">Method</th>
                            <th class="py-3 px-4">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($bookings as $b)
                            @php
                                $bPayMethod = $b->payments->firstWhere('status', 'Success')?->payment_method ?? 'App';
                                $commAmount = (float)($b->commission_amount ?? $b->payments->where('status', 'Success')->sum('commission_amount'));
                                $platFee = (float)($b->platform_fee ?? 0);
                                $totalSaaSCut = round($commAmount + $platFee, 2);
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-gray-900">
                                    {{ $b->booking_reference ?? ('#' . $b->id) }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-gray-900">
                                    {{ $b->turf?->name ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-gray-900">{{ $b->user?->name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $b->user?->phone }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-gray-900">
                                    ₹{{ number_format($b->total_amount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-gray-600">
                                    {{ (float)($b->commission_rate ?? $b->turf?->commission_percentage ?? 7.00) }}%
                                </td>
                                <td class="py-3.5 px-4 font-black text-indigo-600">
                                    ₹{{ number_format($commAmount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-purple-600">
                                    ₹{{ number_format($platFee, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-emerald-700 text-sm bg-emerald-50/30">
                                    ₹{{ number_format($totalSaaSCut, 2) }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold
                                        {{ $bPayMethod === 'App' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ $bPayMethod === 'App' ? 'Online' : 'Cash (Venue)' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                    {{ $b->created_at->format('d M Y, h:i A') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-12 text-center text-gray-400">
                                    No booking revenue records found for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
            </div>
        @elseif ($activeTab === 'cancellations')
            <!-- Cancellations Revenue Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Booking Ref</th>
                            <th class="py-3 px-4">Turf Name</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Gross Cancelled</th>
                            <th class="py-3 px-4">Turf Fee</th>
                            <th class="py-3 px-4">Plat Fee Retained</th>
                            <th class="py-3 px-4">SaaS Cancel Charge</th>
                            <th class="py-3 px-4 text-amber-700">Total SaaS Cut</th>
                            <th class="py-3 px-4">Refund Issued</th>
                            <th class="py-3 px-4">Refund Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($cancellations as $c)
                            @php
                                $cBreakup = $c->deductions_breakup;
                                $saasTotalCut = round(($cBreakup['platform_fee_retained'] ?? 0) + ($cBreakup['saas_fee'] ?? 0), 2);
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-gray-900">
                                    {{ $c->booking?->booking_reference ?? ('#' . $c->booking_id) }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-gray-900">
                                    {{ $c->booking?->turf?->name ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-gray-900">{{ $c->booking?->user?->name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $c->booking?->user?->phone }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-gray-900">
                                    ₹{{ number_format($c->gross_cancelled_amount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-gray-600">
                                    ₹{{ number_format($cBreakup['turf_fee'] ?? 0, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-purple-600">
                                    ₹{{ number_format($cBreakup['platform_fee_retained'] ?? 0, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-amber-600">
                                    ₹{{ number_format($cBreakup['saas_fee'] ?? 0, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-amber-700 text-sm bg-amber-50/30">
                                    ₹{{ number_format($saasTotalCut, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-emerald-600">
                                    ₹{{ number_format($c->refund_amount, 2) }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                        {{ in_array($c->refund_status, ['Refunded', 'Resolved']) ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        {{ $c->refund_status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="py-12 text-center text-gray-400">
                                    No cancellation fee records found for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $cancellations->links() }}
            </div>
        @elseif ($activeTab === 'turfs')
            <!-- Turf Managers Summary Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Manager Name</th>
                            <th class="py-3 px-4">Email / Phone</th>
                            <th class="py-3 px-4">Default Comm Rate</th>
                            <th class="py-3 px-4">Turfs Owned</th>
                            <th class="py-3 px-4">Wallet Balance / Debt</th>
                            <th class="py-3 px-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($managers as $mgr)
                            @php
                                $mgrBalance = (float) $mgr->commission_wallet_balance;
                                $turfsCount = $mgr->locations->flatMap(fn($l) => $l->turfs)->count();
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4 font-bold text-gray-900">
                                    {{ $mgr->name }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-600">
                                    <div>{{ $mgr->email }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $mgr->phone }}</div>
                                </td>
                                <td class="py-3.5 px-4 font-semibold text-emerald-700">
                                    {{ (float)($mgr->commission_percentage ?? 7.00) }}%
                                </td>
                                <td class="py-3.5 px-4 font-semibold">
                                    {{ $turfsCount }} Turf(s)
                                </td>
                                <td class="py-3.5 px-4 font-black">
                                    @if ($mgrBalance < 0)
                                        <span class="text-red-600">Due: ₹{{ number_format(abs($mgrBalance), 2) }}</span>
                                    @elseif ($mgrBalance > 0)
                                        <span class="text-emerald-600">₹{{ number_format($mgrBalance, 2) }}</span>
                                    @else
                                        <span class="text-gray-400">₹0.00</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <a href="{{ route('saas.turfs') }}" wire:navigate class="text-indigo-600 hover:text-indigo-800 font-bold hover:underline">
                                        View Turfs &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-400">
                                    No turf managers found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $managers->links() }}
            </div>
        @else
            <!-- Commission Settlements Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Settlement #</th>
                            <th class="py-3 px-4">Manager</th>
                            <th class="py-3 px-4">Amount Paid</th>
                            <th class="py-3 px-4">Razorpay Payment ID</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($settlements as $st)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-gray-700">
                                    #{{ $st->id }}
                                </td>
                                <td class="py-3.5 px-4 font-bold text-gray-900">
                                    {{ $st->user?->name ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4 font-black text-emerald-600 text-sm">
                                    ₹{{ number_format($st->amount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-gray-500">
                                    {{ $st->razorpay_payment_id ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                    {{ $st->created_at->format('d M Y, h:i A') }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        {{ ucfirst($st->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-12 text-center text-gray-400">
                                    No commission settlements found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $settlements->links() }}
            </div>
        @endif
        </div>
    </div>
</div>
