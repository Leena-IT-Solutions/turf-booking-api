@props(['data'])

<div class="space-y-6">
    <!-- Notice Card -->
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-indigo-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="text-xs text-indigo-900 leading-relaxed">
            <p class="font-bold">Account-Wide Software & Commission Summary</p>
            <p class="mt-0.5 text-indigo-700">Aggregated monthly across your entire owner account. Sourced directly from your ledger transactions with effective retention rate analysis.</p>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total Gross Revenue</span>
            <p class="text-2xl font-bold text-gray-900 mt-1">₹{{ number_format($data['overall_gross'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Platform Fee Total</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['overall_platform_fee'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Commission Total</span>
            <p class="text-2xl font-bold text-rose-600 mt-1">₹{{ number_format($data['overall_commission'], 2) }}</p>
        </div>
        <div class="bg-white rounded-xl p-5 border border-gray-200 shadow-sm">
            <span class="text-xs font-medium text-gray-500">Total SaaS Cut & Rate</span>
            <p class="text-2xl font-bold text-indigo-600 mt-1">₹{{ number_format($data['overall_total_cut'], 2) }}</p>
            <span class="text-[10px] text-gray-400">Effective: {{ number_format($data['overall_effective_rate'], 2) }}% of gross</span>
        </div>
    </div>

    <!-- Monthly Summary Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">Monthly Deductions Breakdown</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs uppercase bg-gray-50 text-gray-700">
                    <tr>
                        <th class="px-4 py-3">Month</th>
                        <th class="px-4 py-3 text-right">Gross Revenue</th>
                        <th class="px-4 py-3 text-right">Platform Fee</th>
                        <th class="px-4 py-3 text-right">Commission</th>
                        <th class="px-4 py-3 text-right">PG Charges</th>
                        <th class="px-4 py-3 text-right">Total Deductions</th>
                        <th class="px-4 py-3 text-right">Effective Rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($data['months'] as $m)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $m['month_label'] }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-gray-900 whitespace-nowrap">₹{{ number_format($m['gross_revenue'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format($m['platform_fee'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format($m['commission'], 2) }}</td>
                            <td class="px-4 py-3 text-right text-rose-600 whitespace-nowrap">₹{{ number_format($m['pg_charges'], 2) }}</td>
                            <td class="px-4 py-3 font-bold text-right text-rose-700 whitespace-nowrap">₹{{ number_format($m['total_cut'], 2) }}</td>
                            <td class="px-4 py-3 font-semibold text-right text-indigo-600 whitespace-nowrap">{{ number_format($m['effective_rate'], 2) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No monthly transaction records found for this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
