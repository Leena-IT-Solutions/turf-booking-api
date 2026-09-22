@props(['data'])

<div class="space-y-6">
    <!-- Notice Card -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-indigo-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-xs text-indigo-900 leading-relaxed">
            <p class="font-bold">Account-Wide Bank Payout History</p>
            <p class="mt-0.5 text-indigo-700">Bank payouts are disbursed at the owner account level and transferred directly to your configured bank account/UPI ID.</p>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Requested Amount</span>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['total_requested'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Payout Processing Fees</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['total_charges'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Net Disbursed</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">₹{{ number_format($data['total_net'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Successful Payouts</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($data['successful_net'], 2) }}</p>
        </div>
    </div>

    <!-- Payouts Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Payout Disbursements (Showing {{ $data['payouts']->count() }} of {{ $data['total_count'] }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Payout ID</th>
                        <th class="px-4 py-3">Requested At</th>
                        <th class="px-4 py-3 text-right">Requested</th>
                        <th class="px-4 py-3 text-right">Charges</th>
                        <th class="px-4 py-3 text-right">Net Amount</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3">Gateway Ref</th>
                        <th class="px-4 py-3">Processed At</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['payouts'] as $p)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">#PO-{{ $p->id }}</td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ $p->created_at->format('d M Y') }}</span>
                                <span class="text-gray-400 block">{{ $p->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-900 whitespace-nowrap">₹{{ number_format((float)$p->requested_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format((float)$p->charge_applied, 2) }}</td>
                            <td class="px-4 py-3 font-bold text-right text-emerald-600 whitespace-nowrap">₹{{ number_format((float)$p->net_amount, 2) }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $p->status === 'Processed' ? 'bg-emerald-100 text-emerald-800' : ($p->status === 'Failed' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ $p->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $p->razorpay_payout_id ?? '-' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $p->processed_at ? $p->processed_at->format('d M Y, h:i A') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                No bank payout records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
