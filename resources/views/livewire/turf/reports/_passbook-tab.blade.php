@props(['data'])

<div class="space-y-6">
    <!-- Notice Card -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-indigo-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-xs text-indigo-900 leading-relaxed">
            <p class="font-bold">Account-Wide Wallet Passbook</p>
            <p class="mt-0.5 text-indigo-700">This statement reflects your master wallet balance across all turfs you own. Sequential running balance is maintained strictly chronologically.</p>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Current Wallet Balance</span>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['current_balance'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Credits (Period)</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">₹{{ number_format($data['total_credits'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Debits (Period)</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['total_debits'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Transactions</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ number_format($data['total_count']) }}</p>
        </div>
    </div>

    <!-- Passbook Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Wallet Transactions Preview (Showing {{ $data['transactions']->count() }} of {{ $data['total_count'] }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Tx ID</th>
                        <th class="px-4 py-3">Date & Time</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3 text-right">Amount</th>
                        <th class="px-4 py-3 text-right">Running Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['transactions'] as $tx)
                        @php
                            $amount = (float) $tx->amount;
                            $isCredit = $amount >= 0;
                        @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">#TX-{{ $tx->id }}</td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ $tx->created_at->format('d M Y') }}</span>
                                <span class="text-gray-400 block">{{ $tx->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $isCredit ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                    {{ \App\Models\CommissionWalletTransaction::typeLabel($tx->type, $amount) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-700 max-w-xs truncate">{{ $tx->description ?? '-' }}</td>
                            <td class="px-4 py-3 font-semibold text-right whitespace-nowrap {{ $isCredit ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ $isCredit ? '+' : '' }}₹{{ number_format($amount, 2) }}
                            </td>
                            <td class="px-4 py-3 font-bold text-right whitespace-nowrap text-gray-900">
                                ₹{{ number_format((float)$tx->balance_after, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                No wallet transactions recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
