@props(['data'])

<div class="space-y-6">
    <!-- GSTIN Header Card -->
    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Output Tax Filing (GSTR-1 Ready)</span>
            <p class="text-sm font-bold text-slate-900 mt-0.5">
                @if ($data['is_single_turf'])
                    {{ $data['company_name'] ?? 'Turf Business' }} &bull; GSTIN: <span class="font-mono text-indigo-600">{{ $data['turf_gstin'] }}</span>
                @else
                    Consolidated Multiple Turfs
                @endif
            </p>
        </div>
        <div class="text-xs text-slate-500">
            GST on turf slots collected from players
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Taxable Value</span>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['total_taxable'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total CGST Collected</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($data['total_cgst'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total SGST Collected</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($data['total_sgst'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total GST Output Liability</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['total_gst'], 2) }}</p>
            <span class="text-[10px] text-gray-400">Gross: ₹{{ number_format($data['total_gross'], 2) }}</span>
        </div>
    </div>

    <!-- GST Breakdown Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Taxable Booking Slots (Showing {{ $data['booking_dates']->count() }} of {{ $data['total_count'] }})</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Booking Ref</th>
                        <th class="px-4 py-3">Slot Date</th>
                        <th class="px-4 py-3">Turf</th>
                        @if (!$data['is_single_turf'])
                            <th class="px-4 py-3">GSTIN</th>
                        @endif
                        <th class="px-4 py-3 text-right">Taxable Value</th>
                        <th class="px-4 py-3 text-right">CGST</th>
                        <th class="px-4 py-3 text-right">SGST</th>
                        <th class="px-4 py-3 text-right">Total GST</th>
                        <th class="px-4 py-3 text-right">Gross Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['booking_dates'] as $bd)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-semibold text-gray-900">
                                {{ $bd->booking?->booking_reference ?? ('#' . $bd->booking_id) }}
                            </td>
                            <td class="px-4 py-3 text-xs whitespace-nowrap">{{ $bd->booking_date }}</td>
                            <td class="px-4 py-3 text-xs">{{ $bd->booking?->turf?->name ?? 'N/A' }}</td>
                            @if (!$data['is_single_turf'])
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">
                                    {{ $bd->booking?->turf?->turfSetting?->gst_number ?? 'Not registered' }}
                                </td>
                            @endif
                            <td class="px-4 py-3 font-semibold text-right text-gray-900 whitespace-nowrap">₹{{ number_format((float)$bd->taxable_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">₹{{ number_format((float)$bd->turf_cgst_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-600 whitespace-nowrap">₹{{ number_format((float)$bd->turf_sgst_amount, 2) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-indigo-600 whitespace-nowrap">₹{{ number_format((float)$bd->turf_gst_amount, 2) }}</td>
                            <td class="px-4 py-3 font-bold text-right text-gray-900 whitespace-nowrap">₹{{ number_format((float)$bd->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $data['is_single_turf'] ? 8 : 9 }}" class="px-4 py-8 text-center text-gray-500">
                                No booking dates found for the selected period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
