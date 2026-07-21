<?php

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;
use App\Models\Turf;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
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
        $this->startDate = Carbon::now('Asia/Kolkata')->startOfMonth()->toDateString();
        $this->endDate = Carbon::now('Asia/Kolkata')->endOfMonth()->toDateString();
    }

    public function setQuickFilter(string $range)
    {
        $now = Carbon::now('Asia/Kolkata');
        if ($range === 'today') {
            $this->startDate = $now->toDateString();
            $this->endDate = $now->toDateString();
        } elseif ($range === 'week') {
            $this->startDate = $now->copy()->startOfWeek()->toDateString();
            $this->endDate = $now->copy()->endOfWeek()->toDateString();
        } elseif ($range === 'month') {
            $this->startDate = $now->copy()->startOfMonth()->toDateString();
            $this->endDate = $now->copy()->endOfMonth()->toDateString();
        }
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @php
            $activeTurfId = session('active_turf_id');
            $turf = $activeTurfId ? Turf::manageable()->find($activeTurfId) : null;

            // Fetch statistics for selected range
            $bookingsQuery = Booking::with(['bookingDates', 'payments'])
                ->whereHas('bookingDates', function ($q) {
                    $q->whereBetween('booking_date', [$this->startDate, $this->endDate]);
                });

            if ($activeTurfId) {
                $bookingsQuery->where('turf_id', $activeTurfId);
            }

            if ($this->statusFilter !== 'all') {
                $bookingsQuery->where('status', $this->statusFilter);
            }
            if ($this->paymentStatusFilter !== 'all') {
                $bookingsQuery->where('payment_status', $this->paymentStatusFilter);
            }

            $bookings = $bookingsQuery->orderBy('created_at', 'desc')->get();

            $totalBookingsCount = $bookings->count();
            $totalAmountSum = 0.00;
            $totalPaidSum = 0.00;

            foreach ($bookings as $b) {
                $totalAmountSum += (float)$b->bookingDates->where('status', '!=', 'Cancelled')->sum('amount');
                $totalPaidSum += (float)$b->payments->where('status', 'Success')->sum('amount');
            }
            $totalOutstandingBalance = max(0.00, $totalAmountSum - $totalPaidSum);
        @endphp

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                    <svg class="w-7 h-7 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Reports & Analytics Dashboard
                </h1>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Filter booking ledgers, revenue breakdown, and export CSV reports.
                </p>
            </div>

            <!-- Export Buttons -->
            <div class="flex flex-wrap gap-2">
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
            </div>
        </div>

        <!-- Filter Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-4 border-b border-gray-100 dark:border-gray-700">
                <div class="flex gap-2">
                    <button wire:click="setQuickFilter('today')" type="button" 
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition {{ $startDate === Carbon::today('Asia/Kolkata')->toDateString() && $endDate === Carbon::today('Asia/Kolkata')->toDateString() ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 dark:border-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600' }}">
                        Today
                    </button>
                    <button wire:click="setQuickFilter('week')" type="button" 
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition bg-gray-50 border-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 hover:border-gray-300">
                        This Week
                    </button>
                    <button wire:click="setQuickFilter('month')" type="button" 
                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border transition bg-gray-50 border-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 hover:border-gray-300">
                        This Month
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                    <input type="date" wire:model.live="startDate" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                    <input type="date" wire:model.live="endDate" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Booking Status</label>
                    <select wire:model.live="statusFilter" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Statuses</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Partially Cancelled">Partially Cancelled</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Payment Status</label>
                    <select wire:model.live="paymentStatusFilter" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="all">All Payment Statuses</option>
                        <option value="Paid">Paid (Full)</option>
                        <option value="Partially Paid">Partially Paid</option>
                        <option value="Unpaid">Unpaid</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Summary Metric Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Bookings</span>
                <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ number_format($totalBookingsCount) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Value</span>
                <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">₹{{ number_format($totalAmountSum, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Collected Revenue</span>
                <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">₹{{ number_format($totalPaidSum, 2) }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Outstanding Balance</span>
                <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-1">₹{{ number_format($totalOutstandingBalance, 2) }}</p>
            </div>
        </div>

        <!-- Bookings Ledger Table -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Bookings Ledger ({{ $bookings->count() }})</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                    <thead class="text-xs uppercase bg-gray-50 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300">
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
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($bookings as $b)
                            @php
                                $totalAmt = (float)$b->bookingDates->where('status', '!=', 'Cancelled')->sum('amount');
                                $paidAmt = (float)$b->payments->where('status', 'Success')->sum('amount');
                                $balAmt = max(0.00, $totalAmt - $paidAmt);
                            @endphp
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                    {{ $b->booking_reference ?? ('#' . $b->id) }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ $b->user?->name ?? 'Guest' }}</div>
                                    <div class="text-xs text-gray-500">{{ $b->user?->mobile ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3">{{ $b->turf?->name ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-xs">{{ $b->bookingDates->pluck('booking_date')->implode(', ') }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">₹{{ number_format($totalAmt, 2) }}</td>
                                <td class="px-4 py-3 text-emerald-600 dark:text-emerald-400 font-semibold">₹{{ number_format($paidAmt, 2) }}</td>
                                <td class="px-4 py-3 text-amber-600 dark:text-amber-400 font-semibold">₹{{ number_format($balAmt, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $b->payment_status === 'Paid' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : ($b->payment_status === 'Partially Paid' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300') }}">
                                        {{ $b->payment_status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $b->status === 'Confirmed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $b->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    No booking records found for the selected date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
