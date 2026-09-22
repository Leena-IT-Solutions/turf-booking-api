@props(['data'])

<div class="space-y-6">
    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Gross Revenue</span>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['gross_revenue'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Deductions (SaaS + PG)</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['total_platform_fee'] + $data['total_commission'] + $data['total_pg_charges'], 2) }}</p>
            <span class="text-[10px] text-gray-400">Plat: ₹{{ number_format($data['total_platform_fee'], 2) }} | Comm: ₹{{ number_format($data['total_commission'], 2) }} | PG: ₹{{ number_format($data['total_pg_charges'], 2) }}</span>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Net Turf Payout</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">₹{{ number_format($data['total_net_payout'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Pending Wallet Clearance</span>
            <p class="text-2xl font-bold text-amber-600 mt-1">₹{{ number_format($data['pending_clearance'], 2) }}</p>
            <span class="text-[10px] text-gray-400">Clears at 06:00 AM on slot date</span>
        </div>
    </div>

    <!-- Earnings Breakdown Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Payments & Deductions (Showing {{ $data['payments']->count() }} of {{ $data['total_count'] }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Payment Ref</th>
                        <th class="px-4 py-3">Paid At</th>
                        <th class="px-4 py-3">Booking / Turf</th>
                        <th class="px-4 py-3 text-right">Gross</th>
                        <th class="px-4 py-3 text-right">Platform Fee</th>
                        <th class="px-4 py-3 text-right">Commission</th>
                        <th class="px-4 py-3 text-right">PG Charges</th>
                        <th class="px-4 py-3 text-right">Net Payout</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['payments'] as $p)
                        @php
                            $platFee = 0.0;
                            if ($p->deductions_settled_at && $p->booking) {
                                $platFee = (float)($p->booking->platform_fee + ($p->booking->platform_fee_gst ?? 0));
                            }
                        @endphp
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">#PAY-{{ $p->id }}</td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ Carbon\Carbon::parse($p->paid_at)->format('d M Y') }}</span>
                                <span class="text-gray-400 block">{{ Carbon\Carbon::parse($p->paid_at)->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $p->booking?->booking_reference ?? ('#' . $p->booking_id) }}</div>
                                <div class="text-xs text-gray-500">{{ $p->booking?->turf?->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-900 whitespace-nowrap">₹{{ number_format((float)$p->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format($platFee, 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format((float)$p->commission_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format((float)$p->gateway_charge_amount, 2) }}</td>
                            <td class="px-4 py-3 font-bold text-right text-emerald-600 whitespace-nowrap">₹{{ number_format((float)$p->turf_payout_amount, 2) }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ $p->wallet_cleared_at ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $p->wallet_cleared_at ? 'Cleared' : 'Pending' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                No payments recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
