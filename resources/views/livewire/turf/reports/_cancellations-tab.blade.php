@props(['data'])

<div class="space-y-6">
    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Gross Cancelled Value</span>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['total_gross'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Turf Retained Fee</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1">₹{{ number_format($data['total_turf_fee'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">SaaS Cancellation Fee</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['total_saas_fee'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Refunded to Users</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($data['total_refund'], 2) }}</p>
        </div>
    </div>

    <!-- Cancellations Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Cancelled Bookings (Showing {{ $data['cancellations']->count() }} of {{ $data['total_count'] }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Cancellation ID</th>
                        <th class="px-4 py-3">Cancelled At</th>
                        <th class="px-4 py-3">Booking / Turf</th>
                        <th class="px-4 py-3">Cancelled By</th>
                        <th class="px-4 py-3 text-right">Gross</th>
                        <th class="px-4 py-3 text-right">Turf Retained</th>
                        <th class="px-4 py-3 text-right">SaaS Fee</th>
                        <th class="px-4 py-3 text-right">Refund Amount</th>
                        <th class="px-4 py-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['cancellations'] as $c)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-mono text-xs text-gray-500">#CAN-{{ $c->id }}</td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">
                                <span class="font-semibold text-gray-900">{{ $c->created_at->format('d M Y') }}</span>
                                <span class="text-gray-400 block">{{ $c->created_at->format('h:i A') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $c->booking?->booking_reference ?? ('#' . $c->booking_id) }}</div>
                                <div class="text-xs text-gray-500">{{ $c->booking?->turf?->name ?? 'N/A' }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-700">
                                    {{ $c->canceller_role ?? ($c->cancelledByUser?->name ?? 'User') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-900 whitespace-nowrap">₹{{ number_format((float)$c->gross_cancelled_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600 whitespace-nowrap">₹{{ number_format((float)$c->turf_cancellation_fee, 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format((float)$c->saas_cancellation_fee, 2) }}</td>
                            <td class="px-4 py-3 font-bold text-right text-indigo-600 whitespace-nowrap">₹{{ number_format((float)$c->refund_amount, 2) }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 text-xs font-semibold rounded-full {{ in_array($c->refund_status, ['Processed', 'Success']) ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                    {{ $c->refund_status ?? 'Pending' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                No cancellation records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
