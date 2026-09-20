<?php

use App\Models\Booking;
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

    public string $activeTab = 'bookings'; // 'bookings', 'turfs', 'settlements'
    public string $search = '';
    public string $paymentMethodFilter = 'all';
    public string $datePreset = 'all';

    public function updatingSearch() { $this->resetPage(); }
    public function updatingPaymentMethodFilter() { $this->resetPage(); }
    public function updatingDatePreset() { $this->resetPage(); }
    public function updatingActiveTab() { $this->resetPage(); }

    public function with(): array
    {
        // Platform Commission KPI Totals
        $totalCommissionEarned = (float) Payment::where('status', 'Success')->sum('commission_amount');
        $totalCommissionGst = (float) Payment::where('status', 'Success')->sum('commission_gst_amount');
        $totalGrossBookingVolume = (float) Payment::where('status', 'Success')->sum('amount');
        $totalCommissionDue = abs((float) User::where('commission_wallet_balance', '<', 0)->sum('commission_wallet_balance'));

        // 1. Commission by Bookings Query
        $bookingsQuery = Booking::with(['turf.location.user', 'user', 'payments'])
            ->whereHas('payments', function ($q) {
                $q->where('status', 'Success');
            })
            ->latest();

        if ($this->search !== '') {
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

        if ($this->datePreset === 'today') {
            $bookingsQuery->whereDate('created_at', Carbon::today());
        } elseif ($this->datePreset === 'yesterday') {
            $bookingsQuery->whereDate('created_at', Carbon::yesterday());
        } elseif ($this->datePreset === 'week') {
            $bookingsQuery->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($this->datePreset === 'month') {
            $bookingsQuery->whereMonth('created_at', Carbon::now()->month)->whereYear('created_at', Carbon::now()->year);
        }

        $bookings = $bookingsQuery->paginate(15);

        // 2. Turf Managers Summary Query
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

        // 3. Commission Settlements Query (Debts paid by managers)
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
            'totalCommissionEarned' => $totalCommissionEarned,
            'totalCommissionGst' => $totalCommissionGst,
            'totalGrossBookingVolume' => $totalGrossBookingVolume,
            'totalCommissionDue' => $totalCommissionDue,
            'bookings' => $bookings,
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
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Platform Commission</h1>
                <p class="text-xs text-gray-500">Track platform booking commissions, GST breakdown, collected revenue, and manager commission dues.</p>
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

    <!-- STATS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Commission Earned -->
        <div class="bg-white p-6 rounded-3xl border border-emerald-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">TOTAL COMMISSION</span>
                <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-emerald-600">
                ₹{{ number_format($totalCommissionEarned, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Net platform fee earned across bookings</p>
        </div>

        <!-- Commission GST Collected -->
        <div class="bg-white p-6 rounded-3xl border border-indigo-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">COMMISSION GST</span>
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-indigo-600">
                ₹{{ number_format($totalCommissionGst, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">CGST, SGST & IGST on platform fees</p>
        </div>

        <!-- Gross Volume -->
        <div class="bg-white p-6 rounded-3xl border border-blue-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">GROSS BOOKING VOLUME</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-blue-600">
                ₹{{ number_format($totalGrossBookingVolume, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Total customer transactions processed</p>
        </div>

        <!-- Commission Due (Offline Cash) -->
        <div class="bg-white p-6 rounded-3xl border border-amber-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">COMMISSION DUE (CASH)</span>
                <span class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-amber-600">
                ₹{{ number_format($totalCommissionDue, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Accrued fees due from offline cash bookings</p>
        </div>
    </div>

    <!-- TABS & TABLE -->
    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-4">
            <!-- Tabs -->
            <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl border border-gray-200">
                <button wire:click="$set('activeTab', 'bookings')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'bookings' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    Bookings Commission
                </button>
                <button wire:click="$set('activeTab', 'turfs')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'turfs' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    Turf Managers Breakdown
                </button>
                <button wire:click="$set('activeTab', 'settlements')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'settlements' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    Debt Settlements
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search..."
                           class="w-48 sm:w-64 pl-9 pr-3 py-2 text-xs rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-emerald-500">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                @if ($activeTab === 'bookings')
                    <select wire:model.live="paymentMethodFilter" class="py-2 px-3 text-xs rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="all">All Methods</option>
                        <option value="App">Online (App)</option>
                        <option value="Pay at Venue">Pay at Venue (Cash)</option>
                    </select>

                    <select wire:model.live="datePreset" class="py-2 px-3 text-xs rounded-xl border border-gray-200 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                @endif
            </div>
        </div>

        @if ($activeTab === 'bookings')
            <!-- Bookings Commission Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Booking Ref</th>
                            <th class="py-3 px-4">Turf Name</th>
                            <th class="py-3 px-4">Customer</th>
                            <th class="py-3 px-4">Booking Total</th>
                            <th class="py-3 px-4">Rate (%)</th>
                            <th class="py-3 px-4">Commission</th>
                            <th class="py-3 px-4">GST</th>
                            <th class="py-3 px-4">Method</th>
                            <th class="py-3 px-4">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($bookings as $b)
                            @php
                                $bPayMethod = $b->payments->firstWhere('status', 'Success')?->payment_method ?? 'App';
                                $commAmount = (float)($b->commission_amount ?? $b->payments->where('status', 'Success')->sum('commission_amount'));
                                $commGst = (float)($b->commission_gst_amount ?? $b->payments->where('status', 'Success')->sum('commission_gst_amount'));
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-emerald-700">
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
                                <td class="py-3.5 px-4 font-black text-emerald-600 text-sm">
                                    ₹{{ number_format($commAmount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 font-medium">
                                    ₹{{ number_format($commGst, 2) }}
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
                                <td colspan="9" class="py-12 text-center text-gray-400">
                                    No commission records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $bookings->links() }}
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
