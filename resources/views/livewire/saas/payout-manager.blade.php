<?php

use App\Models\TurfPayout;
use App\Models\PayoutWebhook;
use App\Services\PayoutService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $tab = 'payouts'; // 'payouts' or 'webhooks'

    public function retryPayout(int $payoutId)
    {
        $payout = TurfPayout::find($payoutId);
        if (!$payout) {
            session()->flash('error', 'Payout record not found.');
            return;
        }

        if ($payout->status !== 'failed') {
            session()->flash('error', 'Only failed payouts can be retried.');
            return;
        }

        try {
            $payoutService = new PayoutService();
            $newPayout = $payoutService->requestPayout($payout->user, (float)$payout->requested_amount, 'manual');
            
            session()->flash('status', "Retry payout #{$newPayout->id} initiated successfully!");
        } catch (\Exception $e) {
            session()->flash('error', 'Retry failed: ' . $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Turf Payouts & Webhooks</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Monitor all turf admin payout requests and RazorpayX webhook logs.</p>
            </div>
        </div>

        <!-- Tab switcher -->
        <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-900 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-700">
            <button wire:click="$set('tab', 'payouts')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $tab === 'payouts' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500' }}">
                Payout Requests
            </button>
            <button wire:click="$set('tab', 'webhooks')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $tab === 'webhooks' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500' }}">
                RazorpayX Webhooks
            </button>
        </div>
    </div>

    <!-- Flash alerts -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-700/60 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-2xl bg-red-50 dark:bg-red-950/60 border border-red-200 dark:border-red-700/60 text-red-800 dark:text-red-300 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    @if ($tab === 'payouts')
        @php
            $payouts = TurfPayout::with('user')->latest()->paginate(15);
        @endphp

        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-gray-900 dark:text-white">All Turf Payouts</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100 dark:border-gray-700/50">
                        <tr>
                            <th class="p-3">ID & Date</th>
                            <th class="p-3">Turf Admin</th>
                            <th class="p-3">Triggered By</th>
                            <th class="p-3">Requested</th>
                            <th class="p-3">Fee Charge</th>
                            <th class="p-3">Net Payout</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300">
                        @forelse ($payouts as $p)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/30">
                                <td class="p-3">
                                    <span class="font-bold block text-gray-900 dark:text-white">#{{ $p->id }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $p->created_at->format('d M Y, h:i A') }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="font-bold block text-gray-900 dark:text-white">{{ $p->user->name ?? 'User #' . $p->user_id }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $p->user->email ?? '' }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $p->triggered_by === 'scheduled' ? 'bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300' }}">
                                        {{ $p->triggered_by }}
                                    </span>
                                </td>
                                <td class="p-3 font-bold font-mono">₹{{ number_format($p->requested_amount, 2) }}</td>
                                <td class="p-3 font-mono text-amber-600">₹{{ number_format($p->charge_applied, 2) }}</td>
                                <td class="p-3 font-bold font-mono text-emerald-600">₹{{ number_format($p->net_amount, 2) }}</td>
                                <td class="p-3">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase {{ $p->status === 'completed' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : ($p->status === 'failed' ? 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300') }}">
                                        {{ $p->status }}
                                    </span>
                                    @if ($p->failure_reason)
                                        <span class="text-[10px] text-red-500 block mt-1 leading-tight">{{ $p->failure_reason }}</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if ($p->status === 'failed')
                                        <button wire:click="retryPayout({{ $p->id }})" type="button"
                                            class="px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[10px] font-bold transition shadow-xs cursor-pointer">
                                            Retry Payout
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-[10px]">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="p-6 text-center text-gray-400">No payout records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-2">
                {{ $payouts->links() }}
            </div>
        </div>
    @else
        @php
            $webhooks = PayoutWebhook::latest()->paginate(15);
        @endphp

        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-gray-900 dark:text-white">RazorpayX Webhook Log Feed</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100 dark:border-gray-700/50">
                        <tr>
                            <th class="p-3">Event ID</th>
                            <th class="p-3">Event Type</th>
                            <th class="p-3">Received At</th>
                            <th class="p-3">Dedupe Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300">
                        @forelse ($webhooks as $wh)
                            <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/30">
                                <td class="p-3 font-mono font-bold text-gray-900 dark:text-white">{{ $wh->event_id }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
                                        {{ $wh->event_type ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500">{{ $wh->created_at->format('d M Y, h:i A') }}</td>
                                <td class="p-3">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase {{ $wh->processed ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800' }}">
                                        {{ $wh->processed ? 'Processed' : 'Pending' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-6 text-center text-gray-400">No payout webhook events logged yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-2">
                {{ $webhooks->links() }}
            </div>
        </div>
    @endif
</div>
