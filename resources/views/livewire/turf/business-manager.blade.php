<?php

use App\Models\CommissionSettlement;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Services\PayoutService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    // Withdrawal / Payout input
    public string $payoutAmount = '';
    public string $payoutSchedule = 'manual';
    public ?int $payoutScheduleDay = 1; // 1 = Monday

    // Bank / UPI KYC inputs
    public string $payoutMethod = 'bank'; // 'bank' or 'upi'
    public string $bankAccountName = '';
    public string $bankAccountNumber = '';
    public string $bankIfsc = '';
    public string $upiId = '';

    // Commission Due Settle input
    public string $settleAmount = '';

    public string $ledgerTab = 'transactions'; // 'transactions' or 'bookings'

    public function mount()
    {
        $user = auth()->user();
        if (!$user) {
            return;
        }
        $this->payoutMethod = $user->payout_method ?: 'bank';
        $this->bankAccountName = $user->bank_account_name ?: '';
        $this->bankAccountNumber = $user->bank_account_number ?: '';
        $this->bankIfsc = $user->bank_ifsc ?: '';
        $this->upiId = $user->upi_id ?: '';
        $this->payoutSchedule = $user->payout_schedule ?: 'manual';
        $this->payoutScheduleDay = $user->payout_schedule_day ?? 1;

        $balance = (float) $user->commission_wallet_balance;
        if ($balance > 0) {
            $this->payoutAmount = (string) $balance;
        } elseif ($balance < 0) {
            $this->settleAmount = (string) abs($balance);
        }
    }

    public function with(): array
    {
        $user = auth()->user();

        $walletTransactions = $user 
            ? CommissionWalletTransaction::where('user_id', $user->id)
                ->with('reference')
                ->latest()
                ->paginate(10, ['*'], 'txPage')
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        $payments = $user ? Payment::with(['booking.turf', 'bookingDate'])
            ->whereHas('booking.turf.location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->latest()
            ->paginate(10, ['*'], 'pmtPage')
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);

        return [
            'walletTransactions' => $walletTransactions,
            'payments' => $payments,
        ];
    }


    public function saveKycDetails()
    {
        $rules = [
            'payoutMethod' => 'required|in:bank,upi',
        ];

        if ($this->payoutMethod === 'bank') {
            $rules['bankAccountName'] = 'required|string|max:150';
            $rules['bankAccountNumber'] = 'required|string|max:50';
            $rules['bankIfsc'] = 'required|string|max:20';
        } else {
            $rules['upiId'] = 'required|string|max:100';
        }

        $this->validate($rules);

        auth()->user()->update([
            'payout_method' => $this->payoutMethod,
            'bank_account_name' => $this->bankAccountName,
            'bank_account_number' => $this->bankAccountNumber,
            'bank_ifsc' => strtoupper($this->bankIfsc),
            'upi_id' => strtolower($this->upiId),
        ]);

        session()->flash('kyc_status', 'Payment payout details saved successfully!');
    }

    public function saveSchedulePreference()
    {
        $this->validate([
            'payoutSchedule' => 'required|in:manual,daily,weekly',
            'payoutScheduleDay' => 'nullable|integer|between:0,6',
        ]);

        auth()->user()->update([
            'payout_schedule' => $this->payoutSchedule,
            'payout_schedule_day' => $this->payoutSchedule === 'weekly' ? $this->payoutScheduleDay : null,
        ]);

        session()->flash('status', 'Payout schedule preference updated successfully.');
    }

    public function requestPayout()
    {
        $user = auth()->user();
        $amount = (float) $this->payoutAmount;

        try {
            $payoutService = new PayoutService();
            $payout = $payoutService->requestPayout($user, $amount, 'manual');

            session()->flash('status', "Payout request for ₹" . number_format($payout->requested_amount, 2) . " submitted successfully! (Net payout: ₹" . number_format($payout->net_amount, 2) . ")");
            $this->mount();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function initiateDueSettlement()
    {
        $user = auth()->user();
        $amount = (float) $this->settleAmount;

        if ($amount <= 0) {
            session()->flash('error', 'Please enter a valid settlement amount.');
            return;
        }

        $amountInPaise = (int) round($amount * 100);
        $saas = SaasSetting::first();
        $rzpKey = $saas?->razorpay_key ?: config('services.razorpay.key');
        $rzpSecret = $saas?->razorpay_secret ?: config('services.razorpay.secret');

        $orderId = null;
        if ($rzpKey && $rzpSecret) {
            try {
                $response = Http::withBasicAuth($rzpKey, $rzpSecret)
                    ->post('https://api.razorpay.com/v1/orders', [
                        'amount' => $amountInPaise,
                        'currency' => 'INR',
                        'receipt' => 'due_' . time() . '_' . $user->id,
                    ]);

                if ($response->successful()) {
                    $orderId = $response->json('id');
                }
            } catch (\Exception $e) {
                // Ignore API network errors for fallback
            }
        }

        $settlement = CommissionSettlement::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'razorpay_order_id' => $orderId,
            'status' => 'created',
        ]);

        $this->dispatch('open-settlement-checkout', [
            'key' => $rzpKey ?: '',
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'name' => config('app.name', 'TurfBooking'),
            'description' => "Commission Due Settlement: ₹" . number_format($amount, 2),
            'order_id' => $orderId,
            'prefill' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->mobile ?? '',
            ],
            'settlement_id' => $settlement->id,
        ]);
    }

    public function verifySettlementPayment($payload = null)
    {
        $data = is_array($payload) && isset($payload[0]) ? $payload[0] : $payload;
        $settlementId = is_array($data) ? ($data['settlement_id'] ?? null) : null;
        $paymentId = is_array($data) ? ($data['razorpay_payment_id'] ?? null) : null;
        $signature = is_array($data) ? ($data['razorpay_signature'] ?? null) : null;

        $settlement = CommissionSettlement::find($settlementId);
        if (!$settlement) {
            session()->flash('error', 'Settlement record not found.');
            return;
        }

        $saas = SaasSetting::first();
        $rzpSecret = $saas?->razorpay_secret ?: config('services.razorpay.secret');

        // HMAC Signature Verification if Razorpay Secret is configured
        if ($rzpSecret && $settlement->razorpay_order_id) {
            if (!$paymentId || !$signature) {
                session()->flash('error', 'Payment verification failed: Missing payment ID or signature.');
                return;
            }

            $expectedSignature = hash_hmac('sha256', $settlement->razorpay_order_id . '|' . $paymentId, $rzpSecret);
            if (!hash_equals($expectedSignature, $signature)) {
                session()->flash('error', 'Payment verification failed: Invalid Razorpay signature.');
                return;
            }
        }

        $user = auth()->user();
        $settlement->update([
            'razorpay_payment_id' => $paymentId ?: ('pay_simulated_' . time()),
            'razorpay_signature' => $signature ?: 'simulated_sig',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Credit settlement amount into wallet
        $walletService = new WalletService();
        $walletService->applyDelta($user, (float)$settlement->amount, 'commission_due_settlement', $settlement);

        session()->flash('status', "Commission due of ₹" . number_format($settlement->amount, 2) . " settled successfully!");
        $this->mount();
    }

    public function runClearMaturedEntries()
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('wallet:clear-matured-entries');
            $output = trim(\Illuminate\Support\Facades\Artisan::output());
            session()->flash('status', $output ?: 'Matured wallet entries cleared successfully!');
            $this->mount();
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to clear matured entries: ' . $e->getMessage());
        }
    }

}; ?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Business & Earnings</h1>
                <p class="text-xs text-gray-500">Track platform commission, withdraw earnings, and manage payout preferences.</p>
            </div>
        </div>

        <!-- Run Clear Matured Entries Action Button -->
        <div class="flex items-center gap-2">
            <button wire:click="runClearMaturedEntries" 
                    wire:loading.attr="disabled"
                    type="button" 
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-2xl shadow-xs transition-all cursor-pointer">
                <svg wire:loading.remove wire:target="runClearMaturedEntries" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <svg wire:loading wire:target="runClearMaturedEntries" class="animate-spin w-4 h-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="runClearMaturedEntries">Clear Matured Entries</span>
                <span wire:loading wire:target="runClearMaturedEntries">Clearing entries...</span>
            </button>
        </div>
    </div>

    @if (session()->has('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800 text-lg leading-none">&times;</button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm font-semibold flex items-center justify-between shadow-xs">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800 text-lg leading-none">&times;</button>
        </div>
    @endif

    @php
        $user = auth()->user();
        $saas = \App\Models\SaasSetting::first();
        $effectiveRate = $user?->commission_percentage ?? 7.00;
        $hasActiveSub = $user ? (bool) $user->activeSubscription : false;
        $balance = $user ? (float) $user->commission_wallet_balance : 0.00;

        // Pending Clearance: Online payments where booking date is in the future
        $pendingClearanceAmount = $user ? (float) Payment::whereHas('booking.turf.location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereNull('wallet_cleared_at')
            ->where('status', 'Success')
            ->where('turf_payout_amount', '>', 0)
            ->sum('turf_payout_amount') : 0.00;


        $totalCommissionEarned = $user ? (float) Payment::whereHas('booking.turf.location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'Success')->sum('commission_amount') : 0.00;

        $totalPayoutsReceived = $user ? (float) TurfPayout::where('user_id', $user->id)->where('status', 'completed')->sum('net_amount') : 0.00;

        $maxDue = (float) ($saas?->max_commission_due ?? 2000.00);
        $graceDays = (int) ($saas?->commission_due_grace_days ?? 7);
        $dueDays = ($user && $user->commission_due_since) ? now()->diffInDays($user->commission_due_since) : 0;
        $isOfflineLocked = $balance <= -$maxDue || ($balance < 0 && $dueDays >= $graceDays);
    @endphp


    <!-- WALLET SUMMARY BADGES -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- Available / Due Balance Badge -->
        <div class="bg-white p-6 rounded-3xl border {{ $balance < 0 ? 'border-red-300 shadow-md bg-red-50/20' : 'border-emerald-200 shadow-xs' }} space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">
                    {{ $balance >= 0 ? '🟢 AVAILABLE FOR WITHDRAWAL' : '🔴 COMMISSION DUE' }}
                </span>
                @if ($balance < 0)
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-red-100 text-red-700 border border-red-300">Action Required</span>
                @endif
            </div>
            <div class="text-3xl font-black {{ $balance < 0 ? 'text-red-600 ' : 'text-emerald-600 ' }}">
                ₹{{ number_format(abs($balance), 2) }}
            </div>
            <p class="text-[11px] text-gray-500">
                {{ $balance >= 0 ? 'Matured cleared earnings ready for payout' : 'Commission debt accrued from offline cash bookings' }}
            </p>
        </div>

        <!-- Pending Clearance Badge -->
        <div class="bg-white p-6 rounded-3xl border border-amber-200 shadow-xs space-y-2 flex flex-col justify-between">
            <div class="space-y-2">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">⏳ PENDING CLEARANCE</span>
                <div class="text-3xl font-black text-amber-600">
                    ₹{{ number_format($pendingClearanceAmount, 2) }}
                </div>
                <p class="text-[11px] text-gray-500">Online credits clearing after booking dates pass</p>
            </div>
            @if ($pendingClearanceAmount > 0)
                <button wire:click="runClearMaturedEntries" wire:loading.attr="disabled" type="button" class="pt-2 text-xs font-bold text-amber-700 hover:text-amber-800 hover:underline flex items-center gap-1.5 cursor-pointer">
                    <span wire:loading.remove wire:target="runClearMaturedEntries">Process matured now &rarr;</span>
                    <span wire:loading wire:target="runClearMaturedEntries">Processing...</span>
                </button>
            @endif
        </div>

        <!-- Lifetime Payouts Received -->
        <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-xs space-y-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">TOTAL PAYOUTS RECEIVED</span>
            <div class="text-3xl font-black text-gray-800">
                ₹{{ number_format($totalPayoutsReceived, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Lifetime completed bank/UPI transfers</p>
        </div>
    </div>


    <!-- Flash Notifications -->
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
    @if (session('kyc_status'))
        <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('kyc_status') }}
        </div>
    @endif

    <!-- OFFLINE BOOKING LOCK WARNING BANNER -->
    @if ($isOfflineLocked)
        <div class="p-5 rounded-3xl bg-red-500/10 border border-red-500/30 text-red-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-red-500/20 text-red-600 rounded-xl shrink-0">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <h4 class="text-xs font-black uppercase tracking-wide">Offline Bookings Locked!</h4>
                    <p class="text-xs mt-0.5">
                        Your commission debt of <strong>₹{{ number_format(abs($balance), 2) }}</strong> has reached the ₹{{ number_format($maxDue, 2) }} limit or exceeded the {{ $graceDays }}-day grace period. Recording manual offline cash bookings is locked until settled.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- ACTION PANELS: WITHDRAWAL / DUE SETTLEMENT -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        @if ($balance < 0)
            <!-- COMMISSION DUE SETTLEMENT PANEL -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-red-200 shadow-xs space-y-5">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-red-600">REQUIRED ACTION</span>
                    <h3 class="text-xl font-black text-gray-900">Settle Platform Commission Due</h3>
                    <p class="text-xs text-gray-500">Pay your accrued commission balance using online Razorpay gateway to unlock offline bookings.</p>
                </div>

                <div class="space-y-4 pt-2">
                    <div>
                        <x-input-label for="settleAmount" :value="__('Settlement Amount (₹)')" />
                        <x-text-input wire:model.live.debounce.250ms="settleAmount" id="settleAmount" type="number" step="0.01" min="1" class="mt-1.5 block w-full text-xs font-mono" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('settleAmount')" class="mt-2" />
                    </div>

                    <button wire:click="initiateDueSettlement" type="button"
                        class="w-full py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md cursor-pointer">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        <span>Settle Due Amount Now</span>
                    </button>
                </div>
            </div>
        @else
            <!-- QUICK BANKING & WITHDRAWAL CALLOUT -->
            <div class="bg-white p-6 sm:p-8 rounded-3xl border border-emerald-100 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="p-3.5 bg-emerald-50 text-emerald-600 rounded-2xl shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-base font-black text-gray-900">Manage Bank Accounts & Withdraw Cleared Earnings</h4>
                        <p class="text-xs text-gray-500">You have ₹{{ number_format(max(0, $balance), 2) }} available. Configure bank accounts/UPI or request manual and automatic payouts on the Banking page.</p>
                    </div>
                </div>
                <a href="{{ route('turf.banking') }}" wire:navigate
                   class="inline-flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-bold rounded-2xl shadow-xs transition-all shrink-0">
                    <span>Open Banking & Payouts &rarr;</span>
                </a>
            </div>
        @endif
    </div>

    <!-- WALLET STATEMENT & BOOKING COMMISSION TRANSACTIONS -->
    <div class="bg-white rounded-3xl border border-gray-200 p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-4">
            <div>
                <h3 class="text-base font-black text-gray-900 tracking-tight">Wallet Statement & Commission Ledger</h3>
                <p class="text-xs text-gray-500">Real-time debit/credit audit trail of wallet balance and booking contributions.</p>
            </div>

            <!-- Tab Switcher -->
            <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl border border-gray-200">
                <button wire:click="$set('ledgerTab', 'transactions')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $ledgerTab === 'transactions' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    📜 Wallet Statement (Passbook)
                </button>
                <button wire:click="$set('ledgerTab', 'bookings')" type="button"
                    class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $ledgerTab === 'bookings' ? 'bg-white text-emerald-700 shadow-xs' : 'text-gray-500 hover:text-gray-700' }}">
                    📊 Booking Commission Ledger
                </button>
            </div>
        </div>

        @if ($ledgerTab === 'transactions')
            <!-- WALLET TRANSACTIONS STATEMENT (PASSBOOK) -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="p-3">Date & Time</th>
                            <th class="p-3">Transaction Type</th>
                            <th class="p-3">Details / Reference</th>
                            <th class="p-3 text-right">Debit / Credit</th>
                            <th class="p-3 text-right">Balance After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse ($walletTransactions as $tx)
                            @php
                                $isCredit = (float)$tx->amount >= 0;
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="p-3 whitespace-nowrap">
                                    <span class="font-bold block text-gray-900">{{ $tx->created_at->format('d M Y') }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $tx->created_at->format('h:i A') }}</span>
                                </td>
                                <td class="p-3">
                                    @if ($tx->type === 'payment_credit')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            + Booking Credit
                                        </span>
                                    @elseif ($tx->type === 'platform_fee_debit')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            - Platform Fee
                                        </span>
                                    @elseif ($tx->type === 'commission_debit')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            - Commission
                                        </span>
                                    @elseif ($tx->type === 'gateway_charge_debit')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            - PG Charges
                                        </span>
                                    @elseif ($tx->type === 'offline_booking_record')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            Pay at Venue
                                        </span>
                                    @elseif ($tx->type === 'refund_adjustment')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            - Refund Deduction
                                        </span>
                                    @elseif ($tx->type === 'payout_debit')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                                            - Bank Payout
                                        </span>
                                    @elseif ($tx->type === 'payout_reversal')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                            + Payout Reversal
                                        </span>
                                    @elseif ($tx->type === 'commission_due_settlement')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            Debt Settlement
                                        </span>
                                    @elseif ($tx->type === 'payment_settlement')
                                        @if ((float)$tx->amount >= 0)
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                + Payout Credit
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                                - Fee & Commission
                                            </span>
                                        @endif
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-700">
                                            {{ ucfirst(str_replace('_', ' ', $tx->type)) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="p-3">
                                    @if ($tx->description)
                                        <div class="font-bold text-gray-900">
                                            {{ $tx->description }}
                                        </div>
                                        @if ($tx->type === 'payment_credit')
                                            <div class="text-[11px] text-gray-400">Gross online payment collected via payment gateway</div>
                                        @elseif ($tx->type === 'platform_fee_debit')
                                            <div class="text-[11px] text-gray-400">One-time software platform fee & GST</div>
                                        @elseif ($tx->type === 'commission_debit')
                                            <div class="text-[11px] text-gray-400">One-time platform commission & GST</div>
                                        @elseif ($tx->type === 'gateway_charge_debit')
                                            <div class="text-[11px] text-gray-400">Payment gateway processing fees & GST</div>
                                        @elseif ($tx->type === 'offline_booking_record')
                                            <div class="text-[11px] text-gray-400">Customer pays full amount directly at turf counter</div>
                                        @endif
                                    @elseif ($tx->type === 'commission_debit' || $tx->type === 'offline_commission_debit' || ($tx->type === 'payment_settlement' && (float)$tx->amount < 0))
                                        <div class="font-bold text-gray-900">
                                            Booking #{{ $tx->reference?->booking_id ?? ($tx->reference?->id ?? $tx->reference_id) }} Platform Fee & Commission
                                        </div>
                                        <div class="text-[11px] text-gray-400">Pay at Location booking charges debited from wallet</div>
                                    @elseif ($tx->type === 'payment_settlement')
                                        <div class="font-bold text-gray-900">
                                            Booking #{{ $tx->reference?->booking_id ?? $tx->reference_id }} Session Earnings
                                        </div>
                                        <div class="text-[11px] text-gray-400">Online payment credited to wallet after session date</div>
                                    @elseif ($tx->type === 'refund_adjustment')
                                        <div class="font-bold text-rose-900">
                                            Booking #{{ $tx->reference?->booking_id ?? $tx->reference_id }} Cancellation Refund Reversal
                                        </div>
                                        <div class="text-[11px] text-rose-600 font-medium">Customer refund adjusted from wallet balance</div>
                                    @elseif ($tx->type === 'payout_debit')
                                        <div class="font-bold text-gray-900">
                                            Withdrawal Payout #{{ $tx->reference_id }}
                                        </div>
                                        <div class="text-[11px] text-gray-400">Transferred to registered bank account / UPI</div>
                                    @elseif ($tx->type === 'payout_reversal')
                                        <div class="font-bold text-gray-900">
                                            Payout #{{ $tx->reference_id }} Reversal
                                        </div>
                                        <div class="text-[11px] text-gray-400">Payout failed and returned to wallet balance</div>
                                    @else
                                        <div class="font-bold text-gray-900">
                                            {{ ucfirst(str_replace('_', ' ', $tx->type)) }} #{{ $tx->reference_id }}
                                        </div>
                                    @endif
                                </td>
                                <td class="p-3 text-right font-black font-mono text-sm whitespace-nowrap {{ (float)$tx->amount > 0 ? 'text-emerald-600' : ((float)$tx->amount < 0 ? 'text-rose-600' : 'text-slate-500') }}">
                                    @if ((float)$tx->amount > 0)
                                        +₹{{ number_format((float)$tx->amount, 2) }}
                                    @elseif ((float)$tx->amount < 0)
                                        -₹{{ number_format(abs((float)$tx->amount), 2) }}
                                    @else
                                        ₹0.00
                                    @endif
                                </td>
                                <td class="p-3 text-right font-black font-mono text-sm whitespace-nowrap text-gray-900">
                                    ₹{{ number_format($tx->balance_after, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-400">
                                    No wallet ledger transactions recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-2">
                {{ $walletTransactions->links() }}
            </div>
        @else
            <!-- BOOKING COMMISSION BREAKDOWN TABLE -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-gray-50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                        <tr>
                            <th class="p-3">Ref & Date</th>
                            <th class="p-3">Method</th>
                            <th class="p-3">Amount</th>
                            <th class="p-3">Rate</th>
                            <th class="p-3">Commission Paid</th>
                            <th class="p-3">Cash Held</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        @forelse ($payments as $pmt)
                            @php
                                $isCancelled = $pmt->booking?->status === 'Cancelled';
                            @endphp
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="p-3">
                                    <span class="font-bold block text-gray-900">#{{ $pmt->booking_id }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $pmt->created_at->format('d M, h:i A') }}</span>
                                </td>
                                <td class="p-3 font-semibold">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $pmt->payment_method === 'App' ? 'bg-indigo-100 text-indigo-700 ' : 'bg-amber-100 text-amber-700 ' }}">
                                        {{ $pmt->payment_method }}
                                    </span>
                                </td>
                                <td class="p-3 font-bold">₹{{ number_format($pmt->amount, 2) }}</td>
                                <td class="p-3 font-mono text-gray-500">{{ number_format($pmt->commission_percentage ?? 7.00, 2) }}%</td>
                                <td class="p-3 font-mono font-bold text-red-600">-₹{{ number_format($pmt->commission_amount ?? 0, 2) }}</td>
                                <td class="p-3 font-mono text-gray-600">₹{{ number_format($pmt->cash_held_amount ?? 0, 2) }}</td>
                                <td class="p-3">
                                    @if ($isCancelled)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-rose-50 text-rose-700 border border-rose-200">
                                            Cancelled & Refunded
                                        </span>
                                    @elseif ($pmt->wallet_cleared_at)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Cleared
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                            Pending
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-gray-400">No payment transaction records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pt-2">
                {{ $payments->links() }}
            </div>
        @endif
    </div>
</div>

<!-- Razorpay Settlement Script Integration -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-settlement-checkout', (data) => {
            const payload = data[0] || data;
            
            // Fallback: If Razorpay keys are not configured, simulate successful settlement
            if (!payload.key) {
                if (confirm(`Razorpay key is not configured in SaaS Settings.\n\nWould you like to simulate successful commission settlement of ₹${(payload.amount/100).toFixed(2)}?`)) {
                    Livewire.dispatch('verify-settlement-payment', [{
                        razorpay_payment_id: 'pay_simulated_settle_' + Date.now(),
                        settlement_id: payload.settlement_id,
                        razorpay_signature: 'simulated_sig'
                    }]);
                }
                return;
            }

            const options = {
                "key": payload.key,
                "amount": payload.amount,
                "currency": payload.currency || "INR",
                "name": payload.name,
                "description": payload.description,
                "order_id": payload.order_id || "",
                "handler": function (response) {
                    Livewire.dispatch('verify-settlement-payment', [{
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id || payload.order_id,
                        razorpay_signature: response.razorpay_signature || "",
                        settlement_id: payload.settlement_id
                    }]);
                },
                "prefill": payload.prefill || {},
                "theme": {
                    "color": "#DC2626"
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                alert("Settlement Failed: " + (response.error.description || "Transaction cancelled."));
            });
            rzp.open();
        });
    });
</script>
