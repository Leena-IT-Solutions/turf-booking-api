<?php

use App\Models\TurfPayout;
use App\Services\PayoutService;
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
            $this->payoutScheduleDay = $user->payout_schedule_day ?? 1;

            $bal = (float) $user->commission_wallet_balance;
            $this->payoutAmount = $bal > 0 ? number_format($bal, 2, '.', '') : '0.00';
        }
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

        auth()->user()->update([
            'payout_method' => $this->payoutMethod,
            'bank_account_name' => $this->bankAccountName,
            'bank_account_number' => $this->bankAccountNumber,
            'bank_ifsc' => strtoupper($this->bankIfsc),
            'upi_id' => strtolower($this->upiId),
        ]);

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

            session()->flash('status', "Payout request for ₹" . number_format($payout->requested_amount, 2) . " submitted successfully! (Net payout: ₹" . number_format($payout->net_amount, 2) . ")");
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
        $pendingPayoutsAmount = $user ? (float) TurfPayout::where('user_id', $user->id)->whereIn('status', ['requested', 'processing'])->sum('net_amount') : 0.00;

        $recentPayouts = $user ? TurfPayout::where('user_id', $user->id)->latest()->paginate(10) : collect();

        return [
            'user' => $user,
            'balance' => $balance,
            'totalPayoutsReceived' => $totalPayoutsReceived,
            'pendingPayoutsAmount' => $pendingPayoutsAmount,
            'recentPayouts' => $recentPayouts,
        ];
    }
}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Banking & Payouts</h1>
                <p class="text-xs text-gray-500">Configure bank accounts & UPI, manage automated withdrawal schedules, and request payout transfers.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('turf.business') }}" wire:navigate
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-800 text-xs font-bold rounded-2xl border border-gray-200 transition-all">
                <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>View Business & Wallet Passbook</span>
            </a>
        </div>
    </div>

    <!-- Flash Notifications -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (session('kyc_status'))
        <div class="p-4 rounded-2xl bg-indigo-50 border border-indigo-200 text-indigo-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('kyc_status') }}</span>
        </div>
    @endif

    @if (session('error'))
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- 3 KEY BANKING STATS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <!-- 1. Available for Payout -->
        <div class="bg-white p-6 rounded-3xl border border-emerald-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">AVAILABLE FOR PAYOUT</span>
                <span class="p-2 bg-emerald-50 text-emerald-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-emerald-600">
                ₹{{ number_format(max(0, $balance), 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Matured net wallet earnings ready for immediate withdrawal</p>
        </div>

        <!-- 2. Active Payout Method -->
        <div class="bg-white p-6 rounded-3xl border border-indigo-100 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">RECEIVING METHOD</span>
                <span class="p-2 bg-indigo-50 text-indigo-600 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </span>
            </div>
            <div class="text-2xl font-black text-indigo-700">
                {{ $payoutMethod === 'bank' ? 'Bank Account' : 'UPI ID' }}
            </div>
            <p class="text-[11px] text-gray-500">
                @if ($payoutMethod === 'bank')
                    {{ $bankAccountNumber ? ('A/C: ****' . substr($bankAccountNumber, -4) . ' (' . ($bankIfsc ?: 'No IFSC') . ')') : 'No bank account saved' }}
                @else
                    {{ $upiId ? ('UPI: ' . $upiId) : 'No UPI ID saved' }}
                @endif
            </p>
        </div>

        <!-- 3. Lifetime Payouts Received -->
        <div class="bg-white p-6 rounded-3xl border border-gray-200 shadow-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-black uppercase tracking-wider text-gray-400">TOTAL PAYOUTS RECEIVED</span>
                <span class="p-2 bg-gray-100 text-gray-700 rounded-xl">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
            </div>
            <div class="text-3xl font-black text-gray-800">
                ₹{{ number_format($totalPayoutsReceived, 2) }}
            </div>
            <p class="text-[11px] text-gray-500">Completed payouts successfully transferred to your account</p>
        </div>
    </div>

    <!-- MAIN 2 CARDS (REQUEST PAYOUT & KYC DETAILS) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- 1. REQUEST PAYOUT PANEL -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-emerald-200 shadow-xs space-y-5">
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600">WITHDRAWAL</span>
                <h3 class="text-xl font-black text-gray-900">Request Payout</h3>
                <p class="text-xs text-gray-500">Transfer available cleared earnings directly to your bank account or UPI ID.</p>
            </div>

            <div class="space-y-4 pt-2">
                <div>
                    <x-input-label for="payoutAmount" :value="__('Withdrawal Amount (₹)')" />
                    <x-text-input wire:model.live.debounce.250ms="payoutAmount" id="payoutAmount" type="number" step="0.01" min="1" max="{{ max(0, $balance) }}" class="mt-1.5 block w-full text-xs font-mono" placeholder="0.00" />
                    <span class="text-[10px] text-gray-400 font-semibold mt-1 block">Maximum available: ₹{{ number_format(max(0, $balance), 2) }}</span>
                    <x-input-error :messages="$errors->get('payoutAmount')" class="mt-2" />
                </div>

                <button wire:click="requestPayout" type="button"
                    class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md cursor-pointer active:scale-98">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    <span>Submit Payout Request</span>
                </button>

                <!-- Automatic Payout Schedule Section -->
                <div class="pt-4 border-t border-gray-100 space-y-3">
                    <x-input-label :value="__('Automatic Payout Schedule')" />
                    <div class="flex flex-wrap items-center gap-3">
                        <select wire:model.live="payoutSchedule" class="text-xs rounded-xl border-gray-300 focus:ring-emerald-500">
                            <option value="manual">Manual Request</option>
                            <option value="daily">Daily Automatic</option>
                            <option value="weekly">Weekly Automatic</option>
                        </select>

                        @if ($payoutSchedule === 'weekly')
                            <select wire:model.live="payoutScheduleDay" class="text-xs rounded-xl border-gray-300 focus:ring-emerald-500">
                                <option value="1">Monday</option>
                                <option value="2">Tuesday</option>
                                <option value="3">Wednesday</option>
                                <option value="4">Thursday</option>
                                <option value="5">Friday</option>
                                <option value="6">Saturday</option>
                                <option value="0">Sunday</option>
                            </select>
                        @endif

                        <button wire:click="saveSchedulePreference" type="button" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition cursor-pointer active:scale-95">
                            Save Schedule
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. PAYOUT RECEIVING DETAILS (BANK / UPI) -->
        <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-200 shadow-xs space-y-5">
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">ACCOUNT DETAILS</span>
                <h3 class="text-xl font-black text-gray-900">Payout Receiving Details</h3>
                <p class="text-xs text-gray-500">Provide bank account or UPI details to receive automated payouts.</p>
            </div>

            <form wire:submit="saveKycDetails" class="space-y-4 pt-2">
                <!-- Method Selector -->
                <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl border border-gray-200">
                    <button type="button" wire:click="$set('payoutMethod', 'bank')"
                        class="flex-1 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $payoutMethod === 'bank' ? 'bg-white text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-800' }}">
                        Bank Account
                    </button>
                    <button type="button" wire:click="$set('payoutMethod', 'upi')"
                        class="flex-1 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $payoutMethod === 'upi' ? 'bg-white text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-800' }}">
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

                <button type="submit" class="w-full py-2.5 bg-gray-900 hover:bg-black text-white rounded-xl text-xs font-bold transition cursor-pointer active:scale-98">
                    Save Payout Details
                </button>
            </form>
        </div>
    </div>

    <!-- RECENT PAYOUTS & WITHDRAWAL HISTORY -->
    <div class="bg-white rounded-3xl border border-gray-200 p-6 space-y-4">
        <div class="flex items-center justify-between border-b border-gray-100 pb-4">
            <div>
                <h3 class="text-base font-black text-gray-900 tracking-tight">Payout Request & Settlement History</h3>
                <p class="text-xs text-gray-500">Track the status of your bank and UPI withdrawal requests.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 text-gray-400 font-extrabold uppercase tracking-wider border-b border-gray-100">
                    <tr>
                        <th class="p-3">Payout #</th>
                        <th class="p-3">Requested Amount</th>
                        <th class="p-3">TDS / Fees</th>
                        <th class="p-3">Net Payout</th>
                        <th class="p-3">Method / Destination</th>
                        <th class="p-3">Date</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 font-medium">
                    @forelse ($recentPayouts as $p)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="p-3 font-mono font-bold text-gray-900">
                                #{{ $p->id }}
                            </td>
                            <td class="p-3 font-bold text-gray-900">
                                ₹{{ number_format($p->requested_amount, 2) }}
                            </td>
                            <td class="p-3 text-rose-600">
                                -₹{{ number_format($p->tds_amount + $p->fee_amount, 2) }}
                            </td>
                            <td class="p-3 font-black text-emerald-600 text-sm">
                                ₹{{ number_format($p->net_amount, 2) }}
                            </td>
                            <td class="p-3 text-gray-600">
                                <span class="capitalize font-semibold">{{ $p->payout_method }}</span>
                                @if ($p->payout_method === 'bank')
                                    <span class="text-[10px] text-gray-400 block font-mono">****{{ substr($p->account_details['account_number'] ?? '', -4) }}</span>
                                @else
                                    <span class="text-[10px] text-gray-400 block font-mono">{{ $p->account_details['upi_id'] ?? '' }}</span>
                                @endif
                            </td>
                            <td class="p-3 text-gray-500 text-[11px]">
                                {{ $p->created_at->format('d M Y, h:i A') }}
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase
                                    {{ $p->status === 'completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($p->status === 'failed' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') }}">
                                    {{ ucfirst($p->status) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-gray-400">No payout withdrawal requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($recentPayouts instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="pt-2">
                {{ $recentPayouts->links() }}
            </div>
        @endif
    </div>
</div>
