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

    public function mount()
    {
        $user = auth()->user();
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
}; ?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Business & Earnings</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Track platform commission, withdraw earnings, and manage payout preferences.</p>
            </div>
        </div>
    </div>

    @php
        $user = auth()->user();
        $saas = \App\Models\SaasSetting::first();
        $effectiveRate = $user->commission_percentage;
        $hasActiveSub = (bool) $user->activeSubscription;
        $balance = (float) $user->commission_wallet_balance;

        // Pending Clearance: Online payments where booking date is in the future
        $pendingClearanceAmount = (float) Payment::whereHas('booking.turf.location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->whereNull('wallet_cleared_at')
            ->where('status', 'Success')
            ->where('turf_payout_amount', '>', 0)
            ->sum('turf_payout_amount');

        $totalCommissionEarned = (float) Payment::whereHas('booking.turf.location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })->where('status', 'Success')->sum('commission_amount');

        $totalPayoutsReceived = (float) TurfPayout::where('user_id', $user->id)->where('status', 'completed')->sum('net_amount');

        $maxDue = (float) ($saas?->max_commission_due ?? 2000.00);
        $graceDays = (int) ($saas?->commission_due_grace_days ?? 7);
        $dueDays = $user->commission_due_since ? now()->diffInDays($user->commission_due_since) : 0;
        $isOfflineLocked = $balance <= -$maxDue || ($balance < 0 && $dueDays >= $graceDays);
    @endphp

    <!-- WALLET SUMMARY BADGES -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Available / Due Balance Badge -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border {{ $balance < 0 ? 'border-red-300 dark:border-red-800 shadow-md bg-red-50/20' : 'border-emerald-200 dark:border-emerald-800 shadow-xs' }} space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">
                    {{ $balance >= 0 ? '🟢 AVAILABLE FOR WITHDRAWAL' : '🔴 COMMISSION DUE' }}
                </span>
                @if ($balance < 0)
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300 border border-red-300">Action Required</span>
                @endif
            </div>
            <div class="text-3xl font-black {{ $balance < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                ₹{{ number_format(abs($balance), 2) }}
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                {{ $balance >= 0 ? 'Matured cleared earnings ready for payout' : 'Commission debt accrued from offline cash bookings' }}
            </p>
        </div>

        <!-- Pending Clearance Badge -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-amber-200 dark:border-amber-800/60 shadow-xs space-y-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">⏳ PENDING CLEARANCE</span>
            <div class="text-3xl font-black text-amber-600 dark:text-amber-400">
                ₹{{ number_format($pendingClearanceAmount, 2) }}
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">Online credits clearing after booking dates pass</p>
        </div>

        <!-- Effective Commission Rate -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-indigo-100 dark:border-indigo-800/60 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">EFFECTIVE COMMISSION</span>
                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full {{ $hasActiveSub ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-900 dark:text-gray-300' }}">
                    {{ $hasActiveSub ? 'Subscribed' : 'Default Rate' }}
                </span>
            </div>
            <div class="text-3xl font-black text-indigo-600 dark:text-indigo-400">
                {{ number_format($effectiveRate, 2) }}%
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">Offline payments get {{ number_format($saas?->payment_gateway_percentage ?? 2.00, 2) }}% gateway discount</p>
        </div>

        <!-- Lifetime Payouts Received -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs space-y-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">TOTAL PAYOUTS RECEIVED</span>
            <div class="text-3xl font-black text-gray-800 dark:text-gray-200">
                ₹{{ number_format($totalPayoutsReceived, 2) }}
            </div>
            <p class="text-[11px] text-gray-500 dark:text-gray-400">Lifetime completed bank/UPI transfers</p>
        </div>
    </div>

    <!-- Flash Notifications -->
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
    @if (session('kyc_status'))
        <div class="p-4 rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 border border-indigo-200 dark:border-indigo-700/60 text-indigo-800 dark:text-indigo-300 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('kyc_status') }}
        </div>
    @endif

    <!-- OFFLINE BOOKING LOCK WARNING BANNER -->
    @if ($isOfflineLocked)
        <div class="p-5 rounded-3xl bg-red-500/10 border border-red-500/30 text-red-800 dark:text-red-300 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-red-500/20 text-red-600 dark:text-red-400 rounded-xl shrink-0">
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
            <div class="bg-white dark:bg-gray-800 p-6 sm:p-8 rounded-3xl border border-red-200 dark:border-red-800/60 shadow-xs space-y-5">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-red-600 dark:text-red-400">REQUIRED ACTION</span>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Settle Platform Commission Due</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Pay your accrued commission balance using online Razorpay gateway to unlock offline bookings.</p>
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
            <!-- PAYOUT REQUEST PANEL -->
            <div class="bg-white dark:bg-gray-800 p-6 sm:p-8 rounded-3xl border border-emerald-200 dark:border-emerald-800/60 shadow-xs space-y-5">
                <div class="space-y-1">
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">WITHDRAWAL</span>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white">Request Payout</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Transfer available cleared earnings directly to your bank account or UPI ID.</p>
                </div>

                <div class="space-y-4 pt-2">
                    <div>
                        <x-input-label for="payoutAmount" :value="__('Withdrawal Amount (₹)')" />
                        <x-text-input wire:model.live.debounce.250ms="payoutAmount" id="payoutAmount" type="number" step="0.01" min="1" max="{{ $balance }}" class="mt-1.5 block w-full text-xs font-mono" placeholder="0.00" />
                        <span class="text-[10px] text-gray-400 font-semibold mt-1 block">Maximum available: ₹{{ number_format($balance, 2) }}</span>
                        <x-input-error :messages="$errors->get('payoutAmount')" class="mt-2" />
                    </div>

                    <button wire:click="requestPayout" type="button"
                        class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md cursor-pointer">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                        <span>Submit Payout Request</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- BANK / UPI KYC DETAILS FORM -->
        <div class="bg-white dark:bg-gray-800 p-6 sm:p-8 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs space-y-5">
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">ACCOUNT DETAILS</span>
                <h3 class="text-xl font-black text-gray-900 dark:text-white">Payout Receiving Details</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Provide bank account or UPI details to receive automated payouts.</p>
            </div>

            <form wire:submit="saveKycDetails" class="space-y-4 pt-2">
                <!-- Method selection -->
                <div class="flex items-center gap-4 bg-gray-100 dark:bg-gray-900 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-700">
                    <button type="button" wire:click="$set('payoutMethod', 'bank')"
                        class="flex-1 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $payoutMethod === 'bank' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500' }}">
                        Bank Account
                    </button>
                    <button type="button" wire:click="$set('payoutMethod', 'upi')"
                        class="flex-1 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $payoutMethod === 'upi' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500' }}">
                        UPI ID
                    </button>
                </div>

                @if ($payoutMethod === 'bank')
                    <div class="space-y-3">
                        <div>
                            <x-input-label for="bankAccountName" :value="__('Account Holder Name')" />
                            <x-text-input wire:model.live.debounce.250ms="bankAccountName" id="bankAccountName" type="text" class="mt-1 block w-full text-xs" placeholder="Sandeep Rathod" />
                            <x-input-error :messages="$errors->get('bankAccountName')" class="mt-1" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="bankAccountNumber" :value="__('Account Number')" />
                                <x-text-input wire:model.live.debounce.250ms="bankAccountNumber" id="bankAccountNumber" type="text" class="mt-1 block w-full font-mono text-xs" placeholder="9876543210123" />
                                <x-input-error :messages="$errors->get('bankAccountNumber')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="bankIfsc" :value="__('IFSC Code')" />
                                <x-text-input wire:model.live.debounce.250ms="bankIfsc" id="bankIfsc" type="text" class="mt-1 block w-full font-mono text-xs uppercase" placeholder="SBIN0001234" />
                                <x-input-error :messages="$errors->get('bankIfsc')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                @else
                    <div>
                        <x-input-label for="upiId" :value="__('UPI ID')" />
                        <x-text-input wire:model.live.debounce.250ms="upiId" id="upiId" type="text" class="mt-1 block w-full font-mono text-xs" placeholder="sandeep@upi" />
                        <x-input-error :messages="$errors->get('upiId')" class="mt-1" />
                    </div>
                @endif

                <button type="submit" class="w-full py-2.5 bg-gray-900 dark:bg-gray-100 text-white dark:text-gray-900 rounded-xl text-xs font-bold transition hover:opacity-90 cursor-pointer">
                    Save Payout Details
                </button>
            </form>
        </div>
    </div>

    <!-- BOOKING COMMISSION TRANSACTIONS TABLE -->
    @php
        $payments = Payment::with(['booking.turf', 'bookingDate'])
            ->whereHas('booking.turf.location', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->latest()
            ->paginate(10);
    @endphp

    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-black text-gray-900 dark:text-white">Booking Commission Ledger</h3>
            <span class="text-xs text-gray-500">Per-payment breakdown</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100 dark:border-gray-700/50">
                    <tr>
                        <th class="p-3">Ref & Date</th>
                        <th class="p-3">Method</th>
                        <th class="p-3">Amount</th>
                        <th class="p-3">Rate</th>
                        <th class="p-3">Commission</th>
                        <th class="p-3">Cash Held</th>
                        <th class="p-3">Wallet Contribution</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50 text-gray-700 dark:text-gray-300">
                    @forelse ($payments as $pmt)
                        <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-900/30">
                            <td class="p-3">
                                <span class="font-bold block text-gray-900 dark:text-white">#{{ $pmt->booking_id }}</span>
                                <span class="text-[10px] text-gray-400">{{ $pmt->created_at->format('d M, h:i A') }}</span>
                            </td>
                            <td class="p-3 font-semibold">
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase {{ $pmt->payment_method === 'App' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $pmt->payment_method }}
                                </span>
                            </td>
                            <td class="p-3 font-bold">₹{{ number_format($pmt->amount, 2) }}</td>
                            <td class="p-3 font-mono text-gray-500">{{ number_format($pmt->commission_percentage ?? 7.00, 2) }}%</td>
                            <td class="p-3 font-mono text-red-600 dark:text-red-400">-₹{{ number_format($pmt->commission_amount ?? 0, 2) }}</td>
                            <td class="p-3 font-mono text-gray-600 dark:text-gray-400">₹{{ number_format($pmt->cash_held_amount ?? 0, 2) }}</td>
                            <td class="p-3 font-bold font-mono {{ ($pmt->turf_payout_amount ?? 0) < 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ ($pmt->turf_payout_amount ?? 0) >= 0 ? '+' : '' }}₹{{ number_format($pmt->turf_payout_amount ?? 0, 2) }}
                            </td>
                            <td class="p-3">
                                @if ($pmt->wallet_cleared_at)
                                    <span class="text-[10px] font-black uppercase text-emerald-600 dark:text-emerald-400">Cleared</span>
                                @else
                                    <span class="text-[10px] font-black uppercase text-amber-600 dark:text-amber-400">Pending</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-6 text-center text-gray-400">No payment transaction records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-2">
            {{ $payments->links() }}
        </div>
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
