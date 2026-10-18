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

    public ?int $selectedPayoutId = null;
    public string $payUtr = '';
    public string $payDate = '';
    public string $payNote = '';
    public string $rejectReason = '';
    public bool $showPayModal = false;
    public bool $showRejectModal = false;
    public ?string $modalError = null;

    public function openPayModal(int $payoutId): void
    {
        $this->selectedPayoutId = $payoutId;
        $this->payUtr = '';
        $this->payDate = date('Y-m-d');
        $this->payNote = '';
        $this->modalError = null;
        $this->showPayModal = true;
    }

    public function closePayModal(): void
    {
        $this->showPayModal = false;
        $this->selectedPayoutId = null;
        $this->modalError = null;
    }

    public function submitMarkPaid(): void
    {
        $this->modalError = null;
        $payout = TurfPayout::find($this->selectedPayoutId);
        if (!$payout) {
            $this->modalError = 'Payout record not found.';
            return;
        }

        try {
            $payoutService = new PayoutService();
            $payoutService->markManualPayoutPaid(
                $payout,
                auth()->user(),
                $this->payUtr,
                $this->payDate,
                $this->payNote
            );

            $this->showPayModal = false;
            $this->selectedPayoutId = null;
            session()->flash('status', "Payout #{$payout->id} marked as paid successfully.");
        } catch (\Exception $e) {
            $this->modalError = $e->getMessage();
        }
    }

    public function openRejectModal(int $payoutId): void
    {
        $this->selectedPayoutId = $payoutId;
        $this->rejectReason = '';
        $this->modalError = null;
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
        $this->selectedPayoutId = null;
        $this->modalError = null;
    }

    public function submitReject(): void
    {
        $this->modalError = null;
        $payout = TurfPayout::find($this->selectedPayoutId);
        if (!$payout) {
            $this->modalError = 'Payout record not found.';
            return;
        }

        try {
            $payoutService = new PayoutService();
            $payoutService->rejectManualPayout(
                $payout,
                auth()->user(),
                $this->rejectReason
            );

            $this->showRejectModal = false;
            $this->selectedPayoutId = null;
            session()->flash('status', "Payout #{$payout->id} rejected successfully and ₹" . number_format($payout->requested_amount, 2) . " returned to wallet.");
        } catch (\Exception $e) {
            $this->modalError = $e->getMessage();
        }
    }

    public function retryPayout(int $payoutId)
    {
        $payout = TurfPayout::find($payoutId);
        if (!$payout) {
            session()->flash('error', 'Payout record not found.');
            return;
        }

        if ($payout->status !== 'failed' || $payout->mode !== 'razorpayx') {
            session()->flash('error', 'Only failed RazorpayX payouts can be retried.');
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

    public function runClearMaturedEntries()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('wallet:clear-matured-entries');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            session()->flash('status', $output ?: 'Matured wallet entries cleared successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear matured entries: ' . $e->getMessage());
        }
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Turf Payouts & Webhooks</h1>
                <p class="text-xs text-gray-500">Monitor all turf admin payout requests and RazorpayX webhook logs.</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Clear Matured Entries Button -->
            <button wire:click="runClearMaturedEntries" 
                    wire:loading.attr="disabled"
                    type="button" 
                    class="inline-flex items-center gap-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-xl shadow-xs transition-all cursor-pointer">
                <svg wire:loading.remove wire:target="runClearMaturedEntries" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg wire:loading wire:target="runClearMaturedEntries" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="runClearMaturedEntries">Clear Matured Entries</span>
                <span wire:loading wire:target="runClearMaturedEntries">Clearing...</span>
            </button>

            <!-- Tab switcher -->
            <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl border border-gray-200">
                <button wire:click="$set('tab', 'payouts')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $tab === 'payouts' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500' }}">
                    Payout Requests
                </button>
                <button wire:click="$set('tab', 'webhooks')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $tab === 'webhooks' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500' }}">
                    RazorpayX Webhooks
                </button>
            </div>
        </div>
    </div>

    <!-- Flash alerts -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif
    @if (session('error'))
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    @if ($tab === 'payouts')
        @php
            $pendingTransfers = TurfPayout::with('user')
                ->where('mode', 'manual')
                ->where('status', 'pending')
                ->oldest('created_at')
                ->get();
            $payouts = TurfPayout::with('user')->latest()->paginate(15);
        @endphp

        <!-- Pending transfers section -->
        <div class="bg-white rounded-3xl border border-amber-200/80 p-6 space-y-4 shadow-xs">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-amber-50 text-amber-600 rounded-xl">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-black text-gray-900">Pending Transfers</h3>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                {{ $pendingTransfers->count() }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500">Manual payouts awaiting bank transfer and UTR recording</p>
                    </div>
                </div>
            </div>

            @if ($pendingTransfers->isEmpty())
                <div class="py-8 text-center text-gray-400 text-xs font-semibold">
                    No payouts waiting.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-amber-50/60 text-gray-500 font-extrabold uppercase tracking-wider border-b border-amber-100">
                            <tr>
                                <th class="p-3">Payout #</th>
                                <th class="p-3">Turf Owner</th>
                                <th class="p-3 text-right">Requested</th>
                                <th class="p-3 text-right">Fee</th>
                                <th class="p-3 text-right font-black">Net to Transfer</th>
                                <th class="p-3">Account Details</th>
                                <th class="p-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-700">
                            @foreach ($pendingTransfers as $pt)
                                <tr class="hover:bg-amber-50/20 transition">
                                    <td class="p-3">
                                        <span class="font-bold text-gray-900 block font-mono">#{{ $pt->id }}</span>
                                        <span class="text-[10px] text-gray-400">{{ $pt->created_at->format('d M Y, h:i A') }}</span>
                                    </td>
                                    <td class="p-3">
                                        <span class="font-bold block text-gray-900">{{ $pt->user->name ?? 'User #' . $pt->user_id }}</span>
                                        <span class="text-[10px] text-gray-500 block">{{ $pt->user->email ?? '' }}</span>
                                        @if (!empty($pt->user->mobile))
                                            <span class="text-[10px] text-gray-400 font-mono block">{{ $pt->user->mobile }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3 font-mono text-right text-gray-900">₹{{ number_format($pt->requested_amount, 2) }}</td>
                                    <td class="p-3 font-mono text-right text-amber-600">₹{{ number_format($pt->charge_applied, 2) }}</td>
                                    <td class="p-3 font-mono text-right font-black text-emerald-600 text-sm">₹{{ number_format($pt->net_amount, 2) }}</td>
                                    <td class="p-3">
                                        <div class="space-y-1">
                                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-bold uppercase {{ $pt->payout_method === 'upi' ? 'bg-indigo-50 text-indigo-700' : 'bg-blue-50 text-blue-700' }}">
                                                {{ $pt->payout_method }}
                                            </span>
                                            @if ($pt->payout_method === 'bank')
                                                <div class="text-[11px] leading-tight space-y-0.5 text-gray-600">
                                                    @if (!empty($pt->account_details['account_holder_name']))
                                                        <div><span class="text-gray-400 text-[10px]">Name:</span> <span class="font-semibold text-gray-800">{{ $pt->account_details['account_holder_name'] }}</span></div>
                                                    @endif
                                                    <div><span class="text-gray-400 text-[10px]">A/C:</span> <span class="font-mono font-bold text-gray-900">{{ $pt->account_details['account_number'] ?? '—' }}</span></div>
                                                    <div><span class="text-gray-400 text-[10px]">IFSC:</span> <span class="font-mono font-semibold">{{ $pt->account_details['ifsc'] ?? '—' }}</span></div>
                                                    @if (!empty($pt->account_details['bank_name']))
                                                        <div><span class="text-gray-400 text-[10px]">Bank:</span> {{ $pt->account_details['bank_name'] }}</div>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="text-[11px]">
                                                    <span class="text-gray-400 text-[10px]">UPI:</span> <span class="font-mono font-bold text-gray-900">{{ $pt->account_details['upi_id'] ?? '—' }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button wire:click="openPayModal({{ $pt->id }})" type="button"
                                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer">
                                                Mark as paid
                                            </button>
                                            <button wire:click="openRejectModal({{ $pt->id }})" type="button"
                                                class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 active:scale-95 rounded-xl text-xs font-bold transition cursor-pointer">
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <!-- History table -->
        <div class="bg-white rounded-3xl border border-gray-200 p-6 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-gray-900">All Turf Payouts</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="p-3">ID & Date</th>
                            <th class="p-3">Turf Admin</th>
                            <th class="p-3">Mode</th>
                            <th class="p-3">Triggered By</th>
                            <th class="p-3 text-right">Requested</th>
                            <th class="p-3 text-right">Fee Charge</th>
                            <th class="p-3 text-right font-black">Net Payout</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">UTR</th>
                            <th class="p-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse ($payouts as $p)
                            <tr class="hover:bg-gray-50/50">
                                <td class="p-3">
                                    <span class="font-bold block text-gray-900 font-mono">#{{ $p->id }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $p->created_at->format('d M Y, h:i A') }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="font-bold block text-gray-900">{{ $p->user->name ?? 'User #' . $p->user_id }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $p->user->email ?? '' }}</span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $p->mode === 'manual' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                        {{ $p->mode === 'manual' ? 'Manual' : 'RazorpayX' }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $p->triggered_by === 'scheduled' ? 'bg-purple-100 text-purple-700 ' : 'bg-gray-100 text-gray-700 ' }}">
                                        {{ $p->triggered_by }}
                                    </span>
                                </td>
                                <td class="p-3 font-bold font-mono text-right">₹{{ number_format($p->requested_amount, 2) }}</td>
                                <td class="p-3 font-mono text-right text-amber-600">₹{{ number_format($p->charge_applied, 2) }}</td>
                                <td class="p-3 font-bold font-mono text-right text-emerald-600">₹{{ number_format($p->net_amount, 2) }}</td>
                                <td class="p-3">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase
                                        {{ $p->status === 'completed' ? 'bg-emerald-100 text-emerald-800' :
                                           (in_array($p->status, ['failed', 'rejected']) ? 'bg-red-100 text-red-800' :
                                           'bg-amber-100 text-amber-800') }}">
                                        {{ $p->status }}
                                    </span>
                                    @if ($p->failure_reason)
                                        <span class="text-[10px] text-red-500 block mt-1 leading-tight">{{ $p->failure_reason }}</span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    <span class="font-mono text-gray-700 text-[11px]">{{ $p->utr ?? '—' }}</span>
                                </td>
                                <td class="p-3 text-right">
                                    @if ($p->status === 'failed' && $p->mode === 'razorpayx')
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
                                <td colspan="10" class="p-6 text-center text-gray-400">No payout records found.</td>
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

        <div class="bg-white rounded-3xl border border-gray-200 p-6 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-gray-900">RazorpayX Webhook Log Feed</h3>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="p-3">Event ID</th>
                            <th class="p-3">Event Type</th>
                            <th class="p-3">Received At</th>
                            <th class="p-3">Dedupe Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse ($webhooks as $wh)
                            <tr class="hover:bg-gray-50/50">
                                <td class="p-3 font-mono font-bold text-gray-900">{{ $wh->event_id }}</td>
                                <td class="p-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase bg-indigo-100 text-indigo-700">
                                        {{ $wh->event_type ?? 'N/A' }}
                                    </span>
                                </td>
                                <td class="p-3 text-gray-500">{{ $wh->created_at->format('d M Y, h:i A') }}</td>
                                <td class="p-3">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase {{ $wh->processed ? 'bg-emerald-100 text-emerald-800 ' : 'bg-amber-100 text-amber-800' }}">
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

    <!-- Modal: Mark as Paid -->
    @if ($showPayModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-100 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-black text-gray-900">Mark Payout as Paid</h3>
                    <button wire:click="closePayModal" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if ($modalError)
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
                        {{ $modalError }}
                    </div>
                @endif

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">UTR / Bank Reference <span class="text-red-500">*</span></label>
                        <input wire:model="payUtr" type="text" placeholder="e.g. UTR1234567890"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Transfer Date <span class="text-red-500">*</span></label>
                        <input wire:model="payDate" type="date" max="{{ date('Y-m-d') }}"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Admin Note <span class="text-gray-400 font-normal">(optional)</span></label>
                        <textarea wire:model="payNote" rows="2" placeholder="e.g. Transferred via bank netbanking"
                            class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                    <button wire:click="closePayModal" type="button" class="px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button wire:click="submitMarkPaid" type="button" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs cursor-pointer">
                        Confirm Paid
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal: Reject Payout -->
    @if ($showRejectModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs">
            <div class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-gray-100 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <h3 class="text-base font-black text-rose-700">Reject Payout Request</h3>
                    <button wire:click="closeRejectModal" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                @if ($modalError)
                    <div class="p-3 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold">
                        {{ $modalError }}
                    </div>
                @endif

                <p class="text-xs text-gray-600">
                    Rejecting this payout will return the full requested amount back to the turf owner's wallet balance.
                </p>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Rejection Reason <span class="text-red-500">*</span></label>
                    <textarea wire:model="rejectReason" rows="3" placeholder="e.g. Incorrect bank account number provided. Please update your details."
                        class="w-full px-3 py-2 text-xs rounded-xl border border-gray-300 focus:border-rose-500 focus:ring-1 focus:ring-rose-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                    <button wire:click="closeRejectModal" type="button" class="px-4 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer">
                        Cancel
                    </button>
                    <button wire:click="submitReject" type="button" class="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs cursor-pointer">
                        Confirm Rejection
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
