<?php

use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\Turf;
use App\Models\TurfSubscription;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $activeTab = 'payments'; // 'payments' or 'turfs'
    public string $search = '';
    public string $billingCycleFilter = 'all';
    public string $statusFilter = 'all';

    public function updatingSearch() { $this->resetPage(); }
    public function updatingBillingCycleFilter() { $this->resetPage(); }
    public function updatingStatusFilter() { $this->resetPage(); }
    public function updatingActiveTab() { $this->resetPage(); }

    public function with(): array
    {
        $totalRevenue = (float) SubscriptionPayment::where('status', 'Success')->sum('amount');
        $activeSubscriptionsCount = TurfSubscription::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })->count();
        $totalSubscribedTurfs = TurfSubscription::distinct('turf_id')->count('turf_id');
        $activePackagesCount = SubscriptionPackage::where('is_active', true)->count();

        // 1. Subscription Payments Query
        $paymentsQuery = SubscriptionPayment::with(['user', 'package'])
            ->latest();

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $paymentsQuery->where(function ($q) use ($term) {
                $q->where('razorpay_payment_id', 'like', $term)
                  ->orWhere('razorpay_order_id', 'like', $term)
                  ->orWhereHas('user', function ($uq) use ($term) {
                      $uq->where('name', 'like', $term)->orWhere('email', 'like', $term);
                  })
                  ->orWhereHas('package', function ($pq) use ($term) {
                      $pq->where('name', 'like', $term);
                  });
            });
        }

        if ($this->billingCycleFilter !== 'all') {
            $paymentsQuery->where('billing_cycle', $this->billingCycleFilter);
        }

        if ($this->statusFilter !== 'all') {
            $paymentsQuery->where('status', $this->statusFilter);
        }

        $payments = $paymentsQuery->paginate(15);

        // 2. Subscribed Turfs Query
        $turfSubsQuery = TurfSubscription::with(['turf.location.user', 'package', 'subscriptionPayment'])
            ->latest('starts_at');

        if ($this->search !== '') {
            $term = '%' . $this->search . '%';
            $turfSubsQuery->where(function ($q) use ($term) {
                $q->whereHas('turf', function ($tq) use ($term) {
                    $tq->where('name', 'like', $term);
                })->orWhereHas('turf.location.user', function ($uq) use ($term) {
                    $uq->where('name', 'like', $term);
                })->orWhereHas('package', function ($pq) use ($term) {
                    $pq->where('name', 'like', $term);
                });
            });
        }

        if ($this->billingCycleFilter !== 'all') {
            $turfSubsQuery->where('billing_cycle', $this->billingCycleFilter);
        }

        if ($this->statusFilter !== 'all') {
            $turfSubsQuery->where('status', $this->statusFilter);
        }

        $turfSubscriptions = $turfSubsQuery->paginate(15);

        return [
            'totalRevenue' => $totalRevenue,
            'activeSubscriptionsCount' => $activeSubscriptionsCount,
            'totalSubscribedTurfs' => $totalSubscribedTurfs,
            'activePackagesCount' => $activePackagesCount,
            'payments' => $payments,
            'turfSubscriptions' => $turfSubscriptions,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Subscription Revenue</h1>
                <p class="text-xs text-gray-500">Track recurring revenue from turf subscription packages, active plans, and payments.</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('saas.subscription-packages') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white text-xs font-bold rounded-2xl shadow-xs transition-all">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                <span>Manage Packages</span>
            </a>
        </div>
    </div>

    <!-- STATS CARDS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Total Revenue -->
        <div class="bg-white p-6 rounded-3xl border border-emerald-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">TOTAL REVENUE</span>
                <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-emerald-600">
                ₹{{ number_format($totalRevenue, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Collected from all subscription payments</p>
        </div>

        <!-- Active Subscriptions -->
        <div class="bg-white p-6 rounded-3xl border border-indigo-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">ACTIVE SUBSCRIPTIONS</span>
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-indigo-600">
                {{ $activeSubscriptionsCount }}
            </div>
            <p class="text-[11px] text-gray-500">Currently active and paid turf subscriptions</p>
        </div>

        <!-- Subscribed Turfs -->
        <div class="bg-white p-6 rounded-3xl border border-blue-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">SUBSCRIBED TURFS</span>
                <span class="p-2 bg-blue-50 text-blue-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-blue-600">
                {{ $totalSubscribedTurfs }}
            </div>
            <p class="text-[11px] text-gray-500">Distinct turfs covered under subscriptions</p>
        </div>

        <!-- Active Packages -->
        <div class="bg-white p-6 rounded-3xl border border-purple-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">PLANS AVAILABLE</span>
                <span class="p-2 bg-purple-50 text-purple-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-purple-600">
                {{ $activePackagesCount }}
            </div>
            <p class="text-[11px] text-gray-500">Live subscription packages configured</p>
        </div>
    </div>

    <!-- TABS & FILTERS -->
    <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-xs space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-4">
            <!-- Tabs -->
            <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl border border-gray-200">
                <button wire:click="$set('activeTab', 'payments')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'payments' ? 'bg-white text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    Payments History
                </button>
                <button wire:click="$set('activeTab', 'turfs')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $activeTab === 'turfs' ? 'bg-white text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    Subscribed Turfs
                </button>
            </div>

            <!-- Filters -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search..."
                           class="w-48 sm:w-64 pl-9 pr-3 py-2 text-xs rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>

                <select wire:model.live="billingCycleFilter" class="py-2 px-3 text-xs rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">All Cycles</option>
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>

                <select wire:model.live="statusFilter" class="py-2 px-3 text-xs rounded-xl border border-gray-200 focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="all">All Statuses</option>
                    <option value="Success">Success / Active</option>
                    <option value="Pending">Pending</option>
                    <option value="expired">Expired</option>
                </select>
            </div>
        </div>

        @if ($activeTab === 'payments')
            <!-- Payments Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Ref #</th>
                            <th class="py-3 px-4">Turf Manager</th>
                            <th class="py-3 px-4">Package</th>
                            <th class="py-3 px-4">Cycle</th>
                            <th class="py-3 px-4">Turfs</th>
                            <th class="py-3 px-4">Amount Paid</th>
                            <th class="py-3 px-4">Gateway ID</th>
                            <th class="py-3 px-4">Date</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($payments as $payment)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-indigo-600">
                                    #{{ $payment->id }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-gray-900">{{ $payment->user?->name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $payment->user?->email }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        {{ $payment->package?->name ?? 'Custom Plan' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 capitalize font-semibold">
                                    {{ $payment->billing_cycle }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700">
                                        {{ $payment->turf_count ?? count($payment->turf_ids ?? []) }} Turf(s)
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-black text-gray-900 text-sm">
                                    ₹{{ number_format($payment->amount, 2) }}
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-gray-500">
                                    {{ $payment->razorpay_payment_id ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                    {{ $payment->created_at->format('d M Y, h:i A') }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                        {{ $payment->status === 'Success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $payment->status === 'Success' ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                                        {{ $payment->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="py-12 text-center text-gray-400">
                                    No subscription payments found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $payments->links() }}
            </div>
        @else
            <!-- Subscribed Turfs Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-400 font-bold uppercase text-[10px] tracking-wider">
                            <th class="py-3 px-4">Turf Name</th>
                            <th class="py-3 px-4">Location / City</th>
                            <th class="py-3 px-4">Manager</th>
                            <th class="py-3 px-4">Package</th>
                            <th class="py-3 px-4">Cycle</th>
                            <th class="py-3 px-4">Started At</th>
                            <th class="py-3 px-4">Expires At</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-700">
                        @forelse ($turfSubscriptions as $sub)
                            @php
                                $isExpired = $sub->expires_at && $sub->expires_at->isPast();
                                $daysLeft = $sub->expires_at ? now()->diffInDays($sub->expires_at, false) : null;
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="py-3.5 px-4">
                                    <div class="font-bold text-gray-900">{{ $sub->turf?->name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-gray-400">ID: #{{ $sub->turf_id }}</div>
                                </td>
                                <td class="py-3.5 px-4 text-gray-600">
                                    {{ $sub->turf?->location?->city ?? 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4">
                                    <div class="font-semibold text-gray-900">{{ $sub->turf?->location?->user?->name ?? 'N/A' }}</div>
                                    <div class="text-[11px] text-gray-400">{{ $sub->turf?->location?->user?->email }}</div>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        {{ $sub->package?->name ?? 'Custom' }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 capitalize font-semibold">
                                    {{ $sub->billing_cycle }}
                                </td>
                                <td class="py-3.5 px-4 text-gray-500 text-[11px]">
                                    {{ $sub->starts_at ? $sub->starts_at->format('d M Y') : 'N/A' }}
                                </td>
                                <td class="py-3.5 px-4 text-[11px]">
                                    @if ($sub->expires_at)
                                        <div class="font-semibold {{ $isExpired ? 'text-red-600' : ($daysLeft !== null && $daysLeft <= 7 ? 'text-amber-600' : 'text-gray-900') }}">
                                            {{ $sub->expires_at->format('d M Y') }}
                                        </div>
                                        <div class="text-[10px] {{ $isExpired ? 'text-red-500' : 'text-gray-400' }}">
                                            {{ $isExpired ? 'Expired' : ($daysLeft . ' days left') }}
                                        </div>
                                    @else
                                        <span class="text-gray-400">Lifetime / N/A</span>
                                    @endif
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase
                                        {{ $sub->status === 'active' && !$isExpired ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $sub->status === 'active' && !$isExpired ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                                        {{ $isExpired ? 'Expired' : $sub->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-12 text-center text-gray-400">
                                    No subscribed turfs found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $turfSubscriptions->links() }}
            </div>
        @endif
    </div>
</div>
