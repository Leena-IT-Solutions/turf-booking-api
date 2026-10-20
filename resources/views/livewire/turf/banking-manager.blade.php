<?php

use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Services\PayoutService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $payoutMethod = 'bank';
    public string $bankAccountName = '';
    public string $bankAccountNumber = '';
    public string $bankIfsc = '';
    public string $upiId = '';

    public string $payoutSchedule = 'weekly';
    public int $payoutScheduleDay = 1; // 1 = Monday
    public string $payoutAmount = '';

    // UI state
    public bool $isEditingKyc = false;
    public string $historyStatus = 'all'; // 'all', 'pending', 'completed', 'rejected'

    public function mount(): void
    {
        $user = auth()->user();
        if ($user) {
            $this->payoutMethod = $user->payout_method ?? 'bank';
            $this->bankAccountName = $user->bank_account_name ?? '';
            $this->bankAccountNumber = $user->bank_account_number ?? '';
            $this->bankIfsc = $user->bank_ifsc ?? '';
            $this->upiId = $user->upi_id ?? '';
            $this->payoutSchedule = $user->payout_schedule ?? 'weekly';
            $this->payoutScheduleDay = (int) ($user->payout_schedule_day ?? 1);

            $bal = (float) $user->commission_wallet_balance;
            $this->payoutAmount = $bal > 0 ? number_format($bal, 2, '.', '') : '0.00';

            $hasSavedDetails = ($this->payoutMethod === 'bank' && !empty($this->bankAccountNumber))
                || ($this->payoutMethod === 'upi' && !empty($this->upiId));
            $this->isEditingKyc = !$hasSavedDetails;
        }
    }

    public function toggleKycEdit(): void
    {
        $this->isEditingKyc = !$this->isEditingKyc;
    }

    public function setQuickAmount(string $type): void
    {
        $user = auth()->user();
        $bal = (float) ($user->commission_wallet_balance ?? 0);
        if ($type === 'max') {
            $this->payoutAmount = number_format(max(0, $bal), 2, '.', '');
        } elseif ($type === '50') {
            $this->payoutAmount = number_format(max(0, $bal * 0.5), 2, '.', '');
        } elseif ($type === '25') {
            $this->payoutAmount = number_format(max(0, $bal * 0.25), 2, '.', '');
        } elseif (is_numeric($type)) {
            $this->payoutAmount = number_format(min($bal, (float) $type), 2, '.', '');
        }
    }

    public function setHistoryStatus(string $status): void
    {
        $this->historyStatus = $status;
        $this->resetPage();
    }

    public function saveKycDetails(): void
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

        // Enforce format rules from PayoutDestination
        $details = \App\Support\PayoutDestination::normalize(
            $this->payoutMethod, $this->bankAccountName, $this->bankAccountNumber, $this->bankIfsc, $this->upiId
        );
        if ($problem = \App\Support\PayoutDestination::problem($details)) {
            $field = [
                'payout_method' => 'payoutMethod',
                'bank_account_name' => 'bankAccountName',
                'bank_account_number' => 'bankAccountNumber',
                'bank_ifsc' => 'bankIfsc',
                'upi_id' => 'upiId',
            ][$problem['field']];
            $this->addError($field, $problem['message']);
            return;
        }

        auth()->user()->update($details);
        $this->isEditingKyc = false;

        session()->flash('kyc_status', 'Payout receiving details saved successfully!');
    }

    public function saveSchedulePreference(): void
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

    public function requestPayout(): void
    {
        $user = auth()->user();
        $amount = (float) $this->payoutAmount;

        try {
            $payoutService = new PayoutService();
            $payout = $payoutService->requestPayout($user, $amount, 'manual');

            $msg = "Payout request for ₹" . number_format($payout->requested_amount, 2) . " submitted successfully! (Net payout: ₹" . number_format($payout->net_amount, 2) . ")";
            if ($payout->mode === 'manual') {
                $msg .= " The platform will transfer it to your account and share the UTR reference.";
            }

            session()->flash('status', $msg);
            $this->mount();
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $balance = $user ? (float) $user->commission_wallet_balance : 0.00;
        $totalPayoutsReceived = $user ? (float) TurfPayout::where('user_id', $user->id)->where('status', 'completed')->sum('net_amount') : 0.00;
        $pendingPayoutsAmount = $user ? (float) TurfPayout::where('user_id', $user->id)->whereIn('status', ['pending', 'processing'])->sum('net_amount') : 0.00;
        $pendingPayoutsCount = $user ? TurfPayout::where('user_id', $user->id)->whereIn('status', ['pending', 'processing'])->count() : 0;

        // Estimated fee calculation
        $saas = SaasSetting::first();
        $payoutHours = (int) ($saas?->payout_hours ?? 24);
        $payoutCharges = (float) ($saas?->payout_charges ?? 40.00);

        $lastCompleted = $user ? TurfPayout::where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest('processed_at')
            ->first() : null;

        $estimatedFee = 0.00;
        if ($lastCompleted && $lastCompleted->processed_at) {
            $hoursSince = abs(now()->diffInHours($lastCompleted->processed_at));
            if ($hoursSince < $payoutHours) {
                $estimatedFee = $payoutCharges;
            }
        }

        $requested = max(0, (float) $this->payoutAmount);
        $estimatedNet = max(0, $requested - $estimatedFee);

        // History query with status filter
        $historyQuery = $user ? TurfPayout::where('user_id', $user->id)->latest() : TurfPayout::whereRaw('1=0');
        if ($this->historyStatus === 'pending') {
            $historyQuery->whereIn('status', ['pending', 'processing']);
        } elseif ($this->historyStatus === 'completed') {
            $historyQuery->where('status', 'completed');
        } elseif ($this->historyStatus === 'rejected') {
            $historyQuery->whereIn('status', ['rejected', 'failed']);
        }

        // Filter Counts
        $allCount = $user ? TurfPayout::where('user_id', $user->id)->count() : 0;
        $pendingFilterCount = $pendingPayoutsCount;
        $completedFilterCount = $user ? TurfPayout::where('user_id', $user->id)->where('status', 'completed')->count() : 0;
        $rejectedFilterCount = $user ? TurfPayout::where('user_id', $user->id)->whereIn('status', ['rejected', 'failed'])->count() : 0;

        $daysOfWeek = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return [
            'user' => $user,
            'balance' => $balance,
            'totalPayoutsReceived' => $totalPayoutsReceived,
            'pendingPayoutsAmount' => $pendingPayoutsAmount,
            'pendingPayoutsCount' => $pendingPayoutsCount,
            'estimatedFee' => $estimatedFee,
            'estimatedNet' => $estimatedNet,
            'daysOfWeek' => $daysOfWeek,
            'recentPayouts' => $historyQuery->paginate(10),
            'counts' => [
                'all' => $allCount,
                'pending' => $pendingFilterCount,
                'completed' => $completedFilterCount,
                'rejected' => $rejectedFilterCount,
            ],
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-100 shadow-xs">
        <div class="flex items-center gap-3.5">
            <div class="p-3.5 bg-gradient-to-tr from-emerald-500 to-teal-600 text-white rounded-2xl shadow-xs">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                </svg>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-bold text-gray-900 tracking-tight">{{ __('Banking & Payout Settlements') }}</h1>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ __('Live Net Wallet') }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Configure verified bank & UPI destinations, set auto-withdrawal schedules, and request earnings payouts.') }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('turf.business') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 hover:text-gray-900 text-xs font-bold rounded-xl border border-gray-200/80 transition duration-150">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ __('View Business & Passbook') }}</span>
            </a>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2.5 shadow-2xs">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('kyc_status'))
        <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-bold flex items-center gap-2.5 shadow-2xs">
            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('kyc_status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2.5 shadow-2xs">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- 4 KEY FINANCIAL STATS STRIP -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- 1. Available for Payout -->
        <div class="bg-white p-5 rounded-3xl border border-emerald-100 shadow-2xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ __('AVAILABLE FOR PAYOUT') }}</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                    💰
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-emerald-600 font-mono">
                ₹{{ number_format(max(0, $balance), 2) }}
            </div>
            <p class="text-[11px] text-gray-500 font-medium">{{ __('Matured wallet earnings ready for immediate withdrawal') }}</p>
        </div>

        <!-- 2. In-Transit / Pending Payouts -->
        <div class="bg-white p-5 rounded-3xl border border-amber-100 shadow-2xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ __('IN-TRANSIT PAYOUTS') }}</span>
                <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                    ⏳
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-amber-600 font-mono">
                ₹{{ number_format($pendingPayoutsAmount, 2) }}
            </div>
            <p class="text-[11px] text-gray-500 font-medium">
                {{ $pendingPayoutsCount > 0 ? $pendingPayoutsCount . ' ' . __('request(s) awaiting bank transfer') : __('No pending withdrawal requests in transit') }}
            </p>
        </div>

        <!-- 3. Active Receiving Method -->
        <div class="bg-white p-5 rounded-3xl border border-indigo-100 shadow-2xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ __('RECEIVING DESTINATION') }}</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                    {{ $payoutMethod === 'bank' ? '🏦' : '⚡' }}
                </span>
            </div>
            <div class="text-xl sm:text-2xl font-black text-indigo-700 truncate">
                {{ $payoutMethod === 'bank' ? __('Bank Account') : __('UPI ID') }}
            </div>
            <p class="text-[11px] text-gray-500 truncate font-mono">
                @if ($payoutMethod === 'bank')
                    {{ $bankAccountNumber ? ('A/C: ****' . substr($bankAccountNumber, -4) . ' (' . ($bankIfsc ?: 'No IFSC') . ')') : __('No bank account saved') }}
                @else
                    {{ $upiId ? $upiId : __('No UPI ID saved') }}
                @endif
            </p>
        </div>

        <!-- 4. Lifetime Payouts Received -->
        <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-2xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">{{ __('TOTAL SETTLED') }}</span>
                <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm">
                    ✅
                </span>
            </div>
            <div class="text-2xl sm:text-3xl font-black text-gray-800 font-mono">
                ₹{{ number_format($totalPayoutsReceived, 2) }}
            </div>
            <p class="text-[11px] text-gray-500 font-medium">{{ __('Completed payouts successfully transferred to your account') }}</p>
        </div>
    </div>

    <!-- MAIN TWO COLUMN PANELS (REQUEST PAYOUT & RECEIVING BENEFICIARY) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        
        <!-- 1. REQUEST PAYOUT PANEL (7 COLS ON LARGE) -->
        <div class="lg:col-span-7 bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs space-y-5">
            <div class="flex items-start justify-between gap-4 pb-3 border-b border-gray-100">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 bg-emerald-50 px-2.5 py-0.5 rounded-full border border-emerald-200/50">
                        {{ __('Withdrawal') }}
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 mt-1.5">{{ __('Request Payout') }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Transfer available net earnings directly to your verified bank account or UPI ID.') }}</p>
                </div>
                <div class="text-right shrink-0">
                    <span class="text-[10px] text-gray-400 font-semibold block uppercase">{{ __('Max Withdrawable') }}</span>
                    <span class="text-base font-black text-gray-900 font-mono">₹{{ number_format(max(0, $balance), 2) }}</span>
                </div>
            </div>

            <!-- Amount Input & Quick Chips -->
            <div class="space-y-4 pt-1">
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="payoutAmount" class="block text-xs font-bold text-gray-700">{{ __('Withdrawal Amount (₹)') }}</label>
                        <button type="button" wire:click="setQuickAmount('max')" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition cursor-pointer">
                            {{ __('Withdraw All (100%)') }}
                        </button>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <span class="text-gray-400 font-bold text-base">₹</span>
                        </div>
                        <input wire:model.live.debounce.250ms="payoutAmount" 
                               id="payoutAmount" 
                               type="number" 
                               step="0.01" 
                               min="1" 
                               max="{{ max(0, $balance) }}" 
                               class="w-full pl-8 pr-4 py-2.5 border border-gray-200 rounded-xl text-base font-black font-mono bg-white text-gray-900 focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 shadow-2xs" 
                               placeholder="0.00" />
                    </div>
                    <x-input-error :messages="$errors->get('payoutAmount')" class="mt-1.5" />

                    <!-- Quick Percentage Chips -->
                    <div class="flex items-center gap-2 mt-2.5 flex-wrap">
                        <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mr-1">{{ __('Quick Fill:') }}</span>
                        <button type="button" wire:click="setQuickAmount('500')" class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200/80 rounded-lg text-xs font-semibold font-mono transition cursor-pointer">
                            ₹500
                        </button>
                        <button type="button" wire:click="setQuickAmount('1000')" class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200/80 rounded-lg text-xs font-semibold font-mono transition cursor-pointer">
                            ₹1,000
                        </button>
                        <button type="button" wire:click="setQuickAmount('25')" class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200/80 rounded-lg text-xs font-semibold transition cursor-pointer">
                            25%
                        </button>
                        <button type="button" wire:click="setQuickAmount('50')" class="px-2.5 py-1 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200/80 rounded-lg text-xs font-semibold transition cursor-pointer">
                            50%
                        </button>
                        <button type="button" wire:click="setQuickAmount('max')" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200/60 rounded-lg text-xs font-bold transition cursor-pointer">
                            100% (Max)
                        </button>
                    </div>
                </div>

                <!-- Live Settlement Calculation Breakdown Box -->
                <div class="bg-gray-50/80 border border-gray-100 rounded-2xl p-4 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-gray-600">
                        <span>{{ __('Requested Amount:') }}</span>
                        <span class="font-mono font-bold text-gray-900">₹{{ number_format(max(0, (float) $payoutAmount), 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-gray-600">
                        <span class="flex items-center gap-1">
                            {{ __('Estimated Fees / Deductions:') }}
                            <span title="Payout cooldown fees if multiple requests occur within 24h" class="text-gray-400 cursor-help">ℹ️</span>
                        </span>
                        <span class="font-mono font-bold {{ $estimatedFee > 0 ? 'text-rose-600' : 'text-gray-500' }}">
                            {{ $estimatedFee > 0 ? '-₹' . number_format($estimatedFee, 2) : '₹0.00' }}
                        </span>
                    </div>
                    <div class="pt-2 border-t border-gray-200/60 flex items-center justify-between font-bold text-sm">
                        <span class="text-gray-900">{{ __('Net Amount to be Credited:') }}</span>
                        <span class="text-emerald-600 font-mono text-base font-black">
                            ₹{{ number_format($estimatedNet, 2) }}
                        </span>
                    </div>

                    <!-- Destination preview -->
                    <div class="pt-1.5 flex items-center gap-1.5 text-[11px] text-gray-500">
                        <span class="text-gray-400">Target:</span>
                        <span class="font-semibold text-gray-800">
                            @if ($payoutMethod === 'bank' && $bankAccountNumber)
                                {{ __('Bank A/C') }} ****{{ substr($bankAccountNumber, -4) }} ({{ $bankIfsc }})
                            @elseif ($payoutMethod === 'upi' && $upiId)
                                {{ __('UPI') }} {{ $upiId }}
                            @else
                                <span class="text-amber-600 font-bold">{{ __('No payout destination configured yet') }}</span>
                            @endif
                        </span>
                    </div>
                </div>

                <!-- Submit Button -->
                <button wire:click="requestPayout" 
                        type="button"
                        @if ($balance <= 0 || ((float) $payoutAmount) <= 0 || ((float) $payoutAmount) > $balance) disabled @endif
                        class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold transition duration-150 flex items-center justify-center gap-2 shadow-xs cursor-pointer active:scale-98">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span>{{ __('Submit Payout Request') }}</span>
                </button>

                <p class="text-[11px] text-gray-400 text-center flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ __('Standard transfers are typically processed within 24 bank working hours via IMPS/NEFT.') }}</span>
                </p>
            </div>
        </div>

        <!-- 2. PAYOUT RECEIVING DETAILS / VIRTUAL CARD (5 COLS ON LARGE) -->
        <div class="lg:col-span-5 bg-white p-6 sm:p-7 rounded-3xl border border-gray-100 shadow-xs flex flex-col justify-between space-y-5">
            <div>
                <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2.5 py-0.5 rounded-full border border-indigo-200/50">
                            {{ __('Beneficiary') }}
                        </span>
                        <h3 class="text-lg font-bold text-gray-900 mt-1.5">{{ __('Payout Receiving Details') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Account destination for receiving automated & manual earnings payouts.') }}</p>
                    </div>

                    @if (!$isEditingKyc && (($payoutMethod === 'bank' && $bankAccountNumber) || ($payoutMethod === 'upi' && $upiId)))
                        <button type="button" wire:click="toggleKycEdit" class="p-2 bg-gray-50 hover:bg-gray-100 text-indigo-600 rounded-xl text-xs font-bold transition border border-gray-200/60 cursor-pointer" title="{{ __('Edit Account Details') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        </button>
                    @endif
                </div>

                <!-- VIEW MODE: Realistic Virtual Card Preview -->
                @if (!$isEditingKyc && (($payoutMethod === 'bank' && $bankAccountNumber) || ($payoutMethod === 'upi' && $upiId)))
                    <div class="mt-4">
                        <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-3xl p-6 shadow-md relative overflow-hidden border border-slate-800">
                            <!-- Background Watermark Pattern -->
                            <div class="absolute -right-6 -bottom-6 w-32 h-32 rounded-full bg-indigo-500/10 blur-xl pointer-events-none"></div>

                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg">{{ $payoutMethod === 'bank' ? '🏛️' : '⚡' }}</span>
                                    <span class="text-xs font-bold tracking-wider uppercase text-indigo-200">
                                        {{ $payoutMethod === 'bank' ? __('Bank Transfer Account') : __('UPI Direct Transfer') }}
                                    </span>
                                </div>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-400/20 text-emerald-300 border border-emerald-400/30">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                    {{ __('Verified') }}
                                </span>
                            </div>

                            <!-- Card Middle Details -->
                            <div class="mt-6 space-y-1">
                                @if ($payoutMethod === 'bank')
                                    <div class="text-[10px] text-indigo-300/80 uppercase tracking-widest font-semibold">{{ __('Account Number') }}</div>
                                    <div class="text-xl font-bold font-mono tracking-widest text-white">
                                        •••• •••• •••• {{ substr($bankAccountNumber, -4) }}
                                    </div>
                                @else
                                    <div class="text-[10px] text-indigo-300/80 uppercase tracking-widest font-semibold">{{ __('UPI Virtual Payment Address') }}</div>
                                    <div class="text-lg font-bold font-mono text-white tracking-wide">
                                        {{ $upiId }}
                                    </div>
                                @endif
                            </div>

                            <!-- Card Bottom Row -->
                            <div class="mt-6 pt-4 border-t border-white/10 flex items-end justify-between">
                                <div>
                                    <div class="text-[9px] uppercase tracking-wider text-indigo-300/70">{{ __('Account Holder') }}</div>
                                    <div class="text-xs font-bold uppercase tracking-wide text-white mt-0.5">
                                        {{ $bankAccountName ?: auth()->user()->name }}
                                    </div>
                                </div>
                                
                                @if ($payoutMethod === 'bank')
                                    <div class="text-right">
                                        <div class="text-[9px] uppercase tracking-wider text-indigo-300/70">{{ __('IFSC Code') }}</div>
                                        <div class="text-xs font-bold font-mono text-white mt-0.5">
                                            {{ $bankIfsc ?: 'N/A' }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Change button -->
                        <div class="mt-4 flex justify-end">
                            <button type="button" wire:click="toggleKycEdit" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1.5 cursor-pointer">
                                <span>{{ __('Change or Update Details') }}</span>
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        </div>
                    </div>

                <!-- EDIT / INITIAL MODE FORM -->
                @else
                    <form wire:submit="saveKycDetails" class="space-y-4 mt-4">
                        <!-- Method Selector Tabs -->
                        <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-2xl border border-gray-200/80">
                            <button type="button" 
                                    wire:click="$set('payoutMethod', 'bank')"
                                    class="flex-1 py-1.5 text-xs font-bold rounded-xl transition cursor-pointer {{ $payoutMethod === 'bank' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                                🏛️ {{ __('Bank Account') }}
                            </button>
                            <button type="button" 
                                    wire:click="$set('payoutMethod', 'upi')"
                                    class="flex-1 py-1.5 text-xs font-bold rounded-xl transition cursor-pointer {{ $payoutMethod === 'upi' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                                ⚡ {{ __('UPI ID') }}
                            </button>
                        </div>

                        @if ($payoutMethod === 'bank')
                            <div class="space-y-3">
                                <div>
                                    <label for="bankAccountName" class="block text-xs font-bold text-gray-700 mb-1">{{ __('Account Holder Name') }}</label>
                                    <input wire:model.live.debounce.250ms="bankAccountName" 
                                           id="bankAccountName" 
                                           type="text" 
                                           class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs" 
                                           placeholder="Sandeep Rathod" />
                                    <x-input-error :messages="$errors->get('bankAccountName')" class="mt-1" />
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="bankAccountNumber" class="block text-xs font-bold text-gray-700 mb-1">{{ __('Account Number') }}</label>
                                        <input wire:model.live.debounce.250ms="bankAccountNumber" 
                                               id="bankAccountNumber" 
                                               type="text" 
                                               class="w-full px-3 py-2 border border-gray-200 rounded-xl font-mono text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs" 
                                               placeholder="0442500100511601" />
                                        <x-input-error :messages="$errors->get('bankAccountNumber')" class="mt-1" />
                                    </div>
                                    <div>
                                        <label for="bankIfsc" class="block text-xs font-bold text-gray-700 mb-1">{{ __('IFSC Code') }}</label>
                                        <input wire:model.live.debounce.250ms="bankIfsc" 
                                               id="bankIfsc" 
                                               type="text" 
                                               class="w-full px-3 py-2 border border-gray-200 rounded-xl font-mono text-xs uppercase bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs" 
                                               placeholder="KARB0000044" />
                                        <x-input-error :messages="$errors->get('bankIfsc')" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        @else
                            <div>
                                <label for="upiId" class="block text-xs font-bold text-gray-700 mb-1">{{ __('UPI ID (Virtual Payment Address)') }}</label>
                                <input wire:model.live.debounce.250ms="upiId" 
                                       id="upiId" 
                                       type="text" 
                                       class="w-full px-3 py-2 border border-gray-200 rounded-xl font-mono text-xs bg-white text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-2xs" 
                                       placeholder="sandeep@upi" />
                                <x-input-error :messages="$errors->get('upiId')" class="mt-1" />
                            </div>
                        @endif

                        <div class="flex items-center gap-2 pt-2">
                            @if (($payoutMethod === 'bank' && $bankAccountNumber) || ($payoutMethod === 'upi' && $upiId))
                                <button type="button" wire:click="toggleKycEdit" class="flex-1 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition cursor-pointer">
                                    {{ __('Cancel') }}
                                </button>
                            @endif
                            <button type="submit" class="flex-1 py-2.5 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold transition cursor-pointer active:scale-98 shadow-xs">
                                {{ __('Save Payout Details') }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <!-- Security Assurance Note -->
            <div class="bg-gray-50/80 rounded-2xl p-3 border border-gray-100 flex items-center gap-2.5 text-[11px] text-gray-500">
                <span class="text-base">🔒</span>
                <span>{{ __('Banking credentials are encrypted and strictly utilized only for authorized earnings payouts.') }}</span>
            </div>
        </div>

    </div>

    <!-- DEDICATED PAYOUT AUTOMATION & SCHEDULE CARD -->
    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-gray-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm font-bold">
                    🔄
                </div>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">{{ __('Automatic Payout Schedule') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('Automate wallet settlements so your cleared earnings transfer on a recurring schedule.') }}</p>
                </div>
            </div>

            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 border border-purple-200/50">
                {{ ucfirst($payoutSchedule) }} {{ __('Mode') }}
            </span>
        </div>

        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-1">
            <div class="flex flex-wrap items-center gap-3">
                <div class="w-full sm:w-48">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ __('Schedule Mode') }}</label>
                    <select wire:model.live="payoutSchedule" class="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 bg-white focus:ring-purple-500 focus:border-purple-500 shadow-2xs cursor-pointer">
                        <option value="manual">{{ __('Manual Request (On-demand)') }}</option>
                        <option value="daily">{{ __('Daily Automatic') }}</option>
                        <option value="weekly">{{ __('Weekly Automatic') }}</option>
                    </select>
                </div>

                @if ($payoutSchedule === 'weekly')
                    <div class="w-full sm:w-44">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">{{ __('Day of Week') }}</label>
                        <select wire:model.live="payoutScheduleDay" class="w-full px-3 py-2 text-xs rounded-xl border border-gray-200 bg-white focus:ring-purple-500 focus:border-purple-500 shadow-2xs cursor-pointer">
                            @foreach ($daysOfWeek as $num => $dayName)
                                <option value="{{ $num }}">{{ $dayName }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="pt-4 sm:pt-4">
                    <button wire:click="saveSchedulePreference" 
                            type="button" 
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition cursor-pointer shadow-xs active:scale-95">
                        {{ __('Save Schedule') }}
                    </button>
                </div>
            </div>

            <div class="text-xs text-gray-500 bg-gray-50 p-3 rounded-2xl border border-gray-100 max-w-sm">
                @if ($payoutSchedule === 'manual')
                    <p class="font-medium">💡 {{ __('Earnings remain in your wallet until you submit a manual withdrawal request above.') }}</p>
                @elseif ($payoutSchedule === 'daily')
                    <p class="font-medium text-purple-700">⚡ {{ __('Automated sweep runs every 24 hours transferring all cleared net earnings.') }}</p>
                @elseif ($payoutSchedule === 'weekly')
                    <p class="font-medium text-purple-700">📅 {{ __('Automated sweep triggers every :day transferring all cleared earnings.', ['day' => $daysOfWeek[$payoutScheduleDay] ?? 'Monday']) }}</p>
                @endif
            </div>
        </div>
    </div>

    <!-- RECENT PAYOUTS & WITHDRAWAL HISTORY TABLE -->
    <div class="bg-white rounded-3xl border border-gray-100 p-6 space-y-4 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-bold text-gray-900 tracking-tight">{{ __('Payout Request & Settlement History') }}</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 font-mono">
                        {{ $counts['all'] }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Complete record of your bank and UPI withdrawal transactions and settlement references.') }}</p>
            </div>

            <!-- Status Filter Tabs -->
            <div class="inline-flex p-1 bg-gray-100 rounded-xl self-start sm:self-auto">
                <button type="button" 
                        wire:click="setHistoryStatus('all')" 
                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $historyStatus === 'all' ? 'bg-white text-indigo-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    {{ __('All') }} ({{ $counts['all'] }})
                </button>
                <button type="button" 
                        wire:click="setHistoryStatus('pending')" 
                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $historyStatus === 'pending' ? 'bg-white text-amber-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    {{ __('Awaiting') }} ({{ $counts['pending'] }})
                </button>
                <button type="button" 
                        wire:click="setHistoryStatus('completed')" 
                        class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $historyStatus === 'completed' ? 'bg-white text-emerald-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                    {{ __('Settled') }} ({{ $counts['completed'] }})
                </button>
                @if ($counts['rejected'] > 0)
                    <button type="button" 
                            wire:click="setHistoryStatus('rejected')" 
                            class="px-3 py-1.5 text-xs font-bold rounded-lg transition {{ $historyStatus === 'rejected' ? 'bg-white text-rose-600 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}">
                        {{ __('Rejected') }} ({{ $counts['rejected'] }})
                    </button>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50/80 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                    <tr>
                        <th class="p-3">{{ __('Payout #') }}</th>
                        <th class="p-3">{{ __('Requested') }}</th>
                        <th class="p-3">{{ __('TDS / Fees') }}</th>
                        <th class="p-3">{{ __('Net Payout') }}</th>
                        <th class="p-3">{{ __('Method / Destination') }}</th>
                        <th class="p-3">{{ __('Date & Time') }}</th>
                        <th class="p-3 text-right">{{ __('Status & Reference') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                    @forelse ($recentPayouts as $p)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="p-3 font-mono font-bold text-gray-900">
                                #{{ $p->id }}
                            </td>
                            <td class="p-3 font-bold text-gray-900 font-mono">
                                ₹{{ number_format($p->requested_amount, 2) }}
                            </td>
                            <td class="p-3 text-rose-600 font-mono font-semibold">
                                {{ $p->charge_applied > 0 ? '-₹' . number_format($p->charge_applied, 2) : '₹0.00' }}
                            </td>
                            <td class="p-3 font-black text-emerald-600 text-sm font-mono">
                                ₹{{ number_format($p->net_amount, 2) }}
                            </td>
                            <td class="p-3 text-gray-600">
                                <div class="flex items-center gap-1.5">
                                    <span>{{ $p->payout_method === 'bank' ? '🏛️' : '⚡' }}</span>
                                    <span class="capitalize font-semibold text-gray-800">{{ $p->payout_method }}</span>
                                </div>
                                @if ($p->payout_method === 'bank')
                                    <span class="text-[10px] text-gray-400 block font-mono">
                                        ****{{ substr($p->account_details['account_number'] ?? '', -4) }}
                                    </span>
                                @else
                                    <span class="text-[10px] text-gray-400 block font-mono">
                                        {{ $p->account_details['upi_id'] ?? '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-500 text-[11px]">
                                <span class="font-semibold text-gray-800 block">{{ $p->created_at->format('d M Y') }}</span>
                                <span class="text-gray-400 text-[10px]">{{ $p->created_at->format('h:i A') }} ({{ $p->created_at->diffForHumans() }})</span>
                            </td>
                            <td class="p-3 text-right">
                                @if ($p->status === 'completed')
                                    <div class="space-y-1 inline-flex flex-col items-end">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200/60">
                                            <span>✓</span>
                                            <span>{{ __('Paid') }}</span>
                                        </span>
                                        @if ($p->utr)
                                            <div x-data="{ copied: false }" class="flex items-center gap-1 text-[10px] text-gray-500 font-mono">
                                                <span>UTR: {{ $p->utr }}</span>
                                                <button type="button" 
                                                        x-on:click="navigator.clipboard.writeText('{{ $p->utr }}'); copied = true; setTimeout(() => copied = false, 2000)" 
                                                        title="Copy UTR Reference"
                                                        class="p-0.5 hover:text-indigo-600 transition">
                                                    <span x-show="!copied">📋</span>
                                                    <span x-show="copied" class="text-emerald-600 font-bold">✓</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                @elseif ($p->status === 'pending' || $p->status === 'processing')
                                    <div class="space-y-1 inline-flex flex-col items-end">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200/70">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                            <span>{{ __('Requested: awaiting transfer') }}</span>
                                        </span>
                                        <span class="text-[9px] text-gray-400">{{ __('Processing in 24 bank hrs') }}</span>
                                    </div>
                                @elseif ($p->status === 'rejected')
                                    <div class="space-y-0.5 inline-flex flex-col items-end">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            {{ __('Rejected') }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 block max-w-xs truncate" title="{{ $p->failure_reason }}">
                                            {{ $p->failure_reason ?: __('Amount returned to wallet') }}
                                        </span>
                                    </div>
                                @elseif ($p->status === 'failed')
                                    <div class="space-y-0.5 inline-flex flex-col items-end">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                            {{ __('Transfer Failed') }}
                                        </span>
                                        <span class="text-[10px] text-gray-400 block">{{ __('Amount refunded to wallet') }}</span>
                                    </div>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-gray-50 text-gray-700 border border-gray-200">
                                        {{ ucfirst($p->status) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-12 text-center text-gray-400 text-xs">
                                <span class="text-2xl block mb-2">💸</span>
                                {{ __('No payout withdrawal requests found matching this filter.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($recentPayouts instanceof \Illuminate\Pagination\LengthAwarePaginator && $recentPayouts->hasPages())
            <div class="pt-3 border-t border-gray-100">
                {{ $recentPayouts->links() }}
            </div>
        @endif
    </div>
</div>
