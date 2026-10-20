@props(['data'])

@php
    $summary = $data['summary'];
    $transactions = $data['transactions'];
    $groupLabels = [
        'earnings' => 'Booking earnings',
        'deductions' => 'Platform fees & commission',
        'returns' => 'Returned on cancellation',
        'refunds' => 'Refunds to players',
        'payouts' => 'Bank payouts',
        'other' => 'Other',
    ];
@endphp

<div class="space-y-6">
    <!-- Subtitle -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <p class="text-xs text-gray-500 font-medium">All turfs you own, newest first.</p>
        
        <!-- Quick Periods -->
        <div class="flex items-center gap-1.5">
            <button
                type="button"
                wire:click="setQuickFilter('this_month')"
                class="px-2.5 py-1 text-xs font-semibold rounded-lg border transition {{ $this->period === 'month' && Carbon\Carbon::parse($this->cursorDate)->isCurrentMonth() ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
            >
                This month
            </button>
            <button
                type="button"
                wire:click="setQuickFilter('last_month')"
                class="px-2.5 py-1 text-xs font-semibold rounded-lg border transition {{ $this->period === 'month' && Carbon\Carbon::parse($this->cursorDate)->isLastMonth() ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
            >
                Last month
            </button>
            <button
                type="button"
                wire:click="setQuickFilter('last_3_months')"
                class="px-2.5 py-1 text-xs font-semibold rounded-lg border transition {{ $this->period === 'custom' ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' }}"
            >
                Last 3 months
            </button>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Opening Balance ({{ \Carbon\Carbon::parse($this->startDate)->format('d M Y') }})</span>
            <p class="text-2xl font-bold font-mono text-gray-900 mt-1">₹{{ number_format($summary['opening_balance'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Credits (Period)</span>
            <p class="text-2xl font-bold font-mono text-emerald-600 mt-1">+₹{{ number_format($summary['period_credits'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Debits (Period)</span>
            <p class="text-2xl font-bold font-mono text-rose-600 mt-1">−₹{{ number_format($summary['period_debits'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Closing Balance ({{ \Carbon\Carbon::parse($this->endDate)->format('d M Y') }})</span>
            <p class="text-2xl font-bold font-mono text-gray-900 mt-1">₹{{ number_format($summary['closing_balance'], 2) }}</p>
        </div>
    </div>

    <!-- Mathematical check & Group Totals -->
    <div class="bg-gray-50/75 rounded-xl p-4 border border-gray-200 space-y-3">
        <div class="text-xs text-gray-600 flex flex-wrap items-center gap-1.5 font-medium">
            <span>Opening <strong class="text-gray-900 font-mono">₹{{ number_format($summary['opening_balance'], 2) }}</strong></span>
            <span class="text-gray-400">+</span>
            <span>credits <strong class="text-emerald-700 font-mono">₹{{ number_format($summary['period_credits'], 2) }}</strong></span>
            <span class="text-gray-400">−</span>
            <span>debits <strong class="text-rose-700 font-mono">₹{{ number_format($summary['period_debits'], 2) }}</strong></span>
            <span class="text-gray-400">=</span>
            <span>closing <strong class="text-gray-900 font-mono">₹{{ number_format($summary['closing_balance'], 2) }}</strong></span>
            @if (\Carbon\Carbon::parse($this->endDate)->isToday() || \Carbon\Carbon::parse($this->endDate)->isFuture())
                <span class="text-gray-300">·</span>
                <span>Current wallet balance <strong class="text-gray-900 font-mono">₹{{ number_format($summary['current_balance'], 2) }}</strong></span>
            @endif
        </div>

        <!-- Group totals row -->
        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-gray-200/60">
            @foreach ($summary['by_group'] as $grp => $grpTotal)
                @if (abs($grpTotal) > 0.001)
                    @php
                        $badgeColor = \App\Models\CommissionWalletTransaction::groupColor($grp);
                    @endphp
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border {{ $badgeColor }}">
                        <span class="text-gray-600">{{ $groupLabels[$grp] ?? ucfirst($grp) }}:</span>
                        <span class="font-bold font-mono">
                            {{ $grpTotal > 0 ? '+' : '' }}₹{{ number_format($grpTotal, 2) }}
                        </span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    <!-- Info Notes -->
    @if ($summary['pending_clearance'] > 0)
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-3.5 flex items-start gap-3 text-xs text-amber-900">
            <svg class="w-4 h-4 text-amber-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <div>
                <span>₹{{ number_format($summary['pending_clearance'], 2) }} of online payments is still pending clearance. It's added to your wallet automatically on each session's date.</span>
                <a href="{{ route('turf.business') }}" class="underline font-semibold hover:text-amber-950 ml-1">See Business & Earnings</a>
            </div>
        </div>
    @endif

    @if ($summary['current_balance'] < 0)
        <div class="bg-rose-50 border border-rose-200 rounded-xl p-3.5 flex items-start gap-3 text-xs text-rose-900">
            <svg class="w-4 h-4 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                A negative balance is platform fee and commission owed on cash (pay at venue) bookings. It's recovered from your future online earnings.
            </div>
        </div>
    @endif

    <!-- Passbook Container -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <!-- Controls bar -->
        <div class="p-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-semibold text-gray-900 text-sm">
                    Showing {{ $transactions->count() }} of {{ $transactions->total() }} entries
                </h3>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <!-- Group filter -->
                <div class="flex items-center gap-1.5">
                    <label for="passbookGroupFilter" class="text-xs font-medium text-gray-500">Type:</label>
                    <select
                        id="passbookGroupFilter"
                        wire:model.live="passbookGroupFilter"
                        class="text-xs rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 py-1.5 pl-2.5 pr-8 bg-white"
                    >
                        <option value="all">All Types</option>
                        <option value="earnings">Booking earnings</option>
                        <option value="deductions">Platform fees & commission</option>
                        <option value="returns">Returned on cancellation</option>
                        <option value="refunds">Refunds to players</option>
                        <option value="payouts">Bank payouts</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <!-- Show zero toggle -->
                <label class="inline-flex items-center gap-2 cursor-pointer text-xs text-gray-600 font-medium select-none">
                    <input
                        type="checkbox"
                        wire:model.live="passbookIncludeZero"
                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    >
                    <span>Show ₹0 pay-at-venue notes</span>
                </label>
            </div>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto passbook-table">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Booking</th>
                        <th class="px-4 py-3">Turf</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Balance After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($transactions as $tx)
                        @php
                            $amount = (float) $tx->amount;
                        @endphp
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="font-semibold text-gray-900 block">{{ $tx->created_at->format('d M Y') }}</span>
                                <span class="text-gray-400 block text-[11px]">{{ $tx->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @include('livewire.turf.partials.wallet-entry-cells', ['tx' => $tx, 'part' => 'booking'])
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @include('livewire.turf.partials.wallet-entry-cells', ['tx' => $tx, 'part' => 'turf'])
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @include('livewire.turf.partials.wallet-entry-cells', ['tx' => $tx, 'part' => 'badge'])
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 whitespace-normal break-words max-w-xs" title="#TX-{{ $tx->id }}">
                                {{ $tx->description ?? '—' }}
                            </td>
                            <td class="px-4 py-3 font-bold font-mono text-right whitespace-nowrap text-xs {{ $amount > 0 ? 'text-emerald-600' : ($amount < 0 ? 'text-rose-600' : 'text-gray-500') }}">
                                @if ($amount > 0)
                                    +₹{{ number_format($amount, 2) }}
                                @elseif ($amount < 0)
                                    −₹{{ number_format(abs($amount), 2) }}
                                @else
                                    ₹0.00
                                @endif
                            </td>
                            <td class="px-4 py-3 font-bold font-mono text-right whitespace-nowrap text-xs text-gray-900">
                                ₹{{ number_format((float)$tx->balance_after, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-500 text-xs">
                                No wallet transactions recorded for the selected filter and period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Phone Card View -->
        <div class="block sm:hidden divide-y divide-gray-100">
            @forelse ($transactions as $tx)
                @php
                    $amount = (float) $tx->amount;
                @endphp
                <div class="p-4 space-y-2.5 passbook-card">
                    <!-- Line 1: Date & Type badge -->
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs text-gray-500">
                            <span class="font-semibold text-gray-900">{{ $tx->created_at->format('d M Y') }}</span>
                            <span class="text-gray-400">· {{ $tx->created_at->format('h:i A') }}</span>
                        </div>
                        <div>
                            @include('livewire.turf.partials.wallet-entry-cells', ['tx' => $tx, 'part' => 'badge'])
                        </div>
                    </div>

                    <!-- Line 2: Booking link & Turf -->
                    <div class="flex items-center justify-between text-xs gap-2">
                        <div class="font-semibold">
                            @include('livewire.turf.partials.wallet-entry-cells', ['tx' => $tx, 'part' => 'booking'])
                        </div>
                        <div class="text-gray-500 truncate text-[11px]">
                            @include('livewire.turf.partials.wallet-entry-cells', ['tx' => $tx, 'part' => 'turf'])
                        </div>
                    </div>

                    <!-- Line 3: Description -->
                    <div class="text-xs text-gray-700 whitespace-normal break-words" title="#TX-{{ $tx->id }}">
                        {{ $tx->description ?? '—' }}
                    </div>

                    <!-- Line 4: Amount & Balance after -->
                    <div class="flex items-center justify-end gap-3 pt-1 border-t border-gray-50 text-right">
                        <div>
                            <div class="text-sm font-bold font-mono {{ $amount > 0 ? 'text-emerald-600' : ($amount < 0 ? 'text-rose-600' : 'text-gray-500') }}">
                                @if ($amount > 0)
                                    +₹{{ number_format($amount, 2) }}
                                @elseif ($amount < 0)
                                    −₹{{ number_format(abs($amount), 2) }}
                                @else
                                    ₹0.00
                                @endif
                            </div>
                            <div class="text-[11px] text-gray-500 font-mono">
                                Bal ₹{{ number_format((float)$tx->balance_after, 2) }}
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 text-xs">
                    No wallet transactions recorded for the selected filter and period.
                </div>
            @endforelse
        </div>

        <!-- Footer / Pagination -->
        <div class="p-4 border-t border-gray-100 bg-gray-50/50">
            @if ($transactions->hasMorePages())
                <div class="text-center">
                    <button
                        type="button"
                        wire:click="loadMorePassbook"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg border border-indigo-200 transition"
                    >
                        <span wire:loading.remove wire:target="loadMorePassbook">Load more</span>
                        <span wire:loading wire:target="loadMorePassbook" class="inline-flex items-center gap-1.5">
                            <span class="animate-spin inline-block w-3 h-3 border-2 border-indigo-600 border-t-transparent rounded-full"></span>
                            Loading older entries...
                        </span>
                    </button>
                </div>
            @else
                <div class="text-center text-xs text-gray-500 font-medium">
                    End of passbook · {{ $transactions->total() }} entries
                </div>
            @endif
        </div>
    </div>
</div>
