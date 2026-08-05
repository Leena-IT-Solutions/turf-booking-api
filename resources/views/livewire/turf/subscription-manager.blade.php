<?php

use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\TurfSubscription;
use App\Models\SaasSetting;
use App\Models\Turf;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $billingCycle = 'monthly'; // 'monthly' or 'yearly'
    public string $razorpayKey = '';
    public array $selectedTurfIds = [];

    public function mount()
    {
        $setting = SaasSetting::first();
        $this->razorpayKey = $setting?->razorpay_key ?: (config('services.razorpay.key') ?: '');

        // Default select all manageable turfs
        $user = auth()->user();
        if ($user) {
            $this->selectedTurfIds = $user->manageableTurfs()->pluck('turfs.id')->map(fn($id) => (int)$id)->toArray();
        }
    }

    public function toggleTurf(int $turfId)
    {
        if (in_array($turfId, $this->selectedTurfIds)) {
            $this->selectedTurfIds = array_values(array_filter($this->selectedTurfIds, fn($id) => $id !== $turfId));
        } else {
            $this->selectedTurfIds[] = $turfId;
        }
    }

    public function toggleAllTurfs()
    {
        $user = auth()->user();
        if (!$user) return;
        $allIds = $user->manageableTurfs()->pluck('turfs.id')->map(fn($id) => (int)$id)->toArray();

        if (count($this->selectedTurfIds) === count($allIds)) {
            $this->selectedTurfIds = [];
        } else {
            $this->selectedTurfIds = $allIds;
        }
    }

    public function initiatePayment(int $packageId, string $cycle, ?array $targetTurfIds = null)
    {
        if (!empty($targetTurfIds)) {
            $this->selectedTurfIds = $targetTurfIds;
        }

        $turfsToPay = $this->selectedTurfIds;

        if (empty($turfsToPay)) {
            session()->flash('error', 'Please select at least one turf to subscribe/renew.');
            return;
        }


        $pkg = SubscriptionPackage::find($packageId);
        if (!$pkg) {
            session()->flash('error', 'Selected subscription package not found.');
            return;
        }

        $user = auth()->user();
        $turfCount = count($turfsToPay);
        $unitPrice = $cycle === 'yearly' ? (float)$pkg->yearly_amount : (float)$pkg->monthly_amount;
        $totalPrice = round($unitPrice * $turfCount, 2);
        $amountInPaise = (int) round($totalPrice * 100);


        $setting = SaasSetting::first();
        $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        $orderId = null;

        if ($rzpKey && $rzpSecret) {
            try {
                $response = Http::withBasicAuth($rzpKey, $rzpSecret)
                    ->post('https://api.razorpay.com/v1/orders', [
                        'amount' => $amountInPaise,
                        'currency' => 'INR',
                        'receipt' => 'sub_' . time() . '_' . $user->id,
                        'notes' => [
                            'user_id' => $user->id,
                            'package_id' => $pkg->id,
                            'cycle' => $cycle,
                            'turf_count' => $turfCount,
                        ],
                    ]);

                if ($response->successful()) {
                    $orderData = $response->json();
                    $orderId = $orderData['id'] ?? null;
                }
            } catch (\Exception $e) {
                // Fallback to null order ID on network error
            }
        }

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => $cycle,
            'amount' => $totalPrice,
            'turf_ids' => array_values($turfsToPay),
            'turf_count' => $turfCount,
            'razorpay_order_id' => $orderId,
            'status' => 'pending',
        ]);


        $this->dispatch('open-razorpay-checkout', [
            'key' => $this->razorpayKey,
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'name' => config('app.name', 'TurfBooking'),
            'description' => "Subscription: {$pkg->name} ({$turfCount} turfs)",
            'order_id' => $orderId,
            'prefill' => [
                'name' => $user->name,
                'email' => $user->email,
                'contact' => $user->mobile ?? '',
            ],
            'payment_record_id' => $payment->id,
            'package_name' => $pkg->name,
        ]);
    }

    #[On('verify-subscription-payment')]
    public function verifyPayment($paymentRecordId = null, $paymentId = null, $signature = null)
    {
        if (!$paymentRecordId) {
            session()->flash('error', 'Payment record ID missing.');
            return;
        }

        $paymentRecord = SubscriptionPayment::find($paymentRecordId);
        if (!$paymentRecord) {
            session()->flash('error', "Payment record #{$paymentRecordId} not found.");
            return;
        }

        $setting = SaasSetting::first();
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        // HMAC Signature Verification if Razorpay Secret is set and order_id exists
        if ($rzpSecret && $paymentRecord->razorpay_order_id) {
            if (!$paymentId || !$signature) {
                session()->flash('error', 'Payment verification failed: missing payment ID or signature.');
                return;
            }

            $expectedSignature = hash_hmac('sha256', $paymentRecord->razorpay_order_id . '|' . $paymentId, $rzpSecret);
            if (!hash_equals($expectedSignature, (string)$signature)) {
                session()->flash('error', 'Payment verification failed: invalid HMAC signature.');
                return;
            }
        }

        $pkg = SubscriptionPackage::find($paymentRecord->subscription_package_id);
        if (!$pkg) {
            session()->flash('error', 'Package not found.');
            return;
        }

        $paymentRecord->update([
            'razorpay_payment_id' => $paymentId ?: ('pay_simulated_' . time()),
            'razorpay_signature' => $signature ?: 'simulated_sig',
            'status' => 'completed',
        ]);

        $days = $paymentRecord->billing_cycle === 'yearly' ? 365 : 30;
        $turfIds = $paymentRecord->turf_ids ?? [];
        $unitPrice = round($paymentRecord->amount / max(1, count($turfIds)), 2);

        foreach ($turfIds as $turfId) {
            $turf = Turf::find($turfId);
            if (!$turf) continue;

            $activeSub = $turf->activeSubscription;

            if ($activeSub && $activeSub->expires_at && $activeSub->expires_at->isFuture()) {
                // Extend active unexpired subscription
                $startsAt = $activeSub->starts_at;
                $newExpiresAt = $activeSub->expires_at->copy()->addDays($days);
                $activeSub->update(['status' => 'expired']);
            } else {
                // Lapsed or new subscription
                $startsAt = now();
                $newExpiresAt = now()->addDays($days);

                // Expire any lingering records for this turf
                TurfSubscription::where('turf_id', $turf->id)
                    ->where('status', 'active')
                    ->update(['status' => 'expired']);
            }

            TurfSubscription::create([
                'turf_id' => $turf->id,
                'subscription_package_id' => $pkg->id,
                'subscription_payment_id' => $paymentRecord->id,
                'billing_cycle' => $paymentRecord->billing_cycle,
                'price' => $unitPrice,
                'commission_percentage' => $pkg->commission_percentage,
                'starts_at' => $startsAt,
                'expires_at' => $newExpiresAt,
                'status' => 'active',
            ]);
        }

        session()->flash('status', "Payment successful! Subscription activated/renewed for " . count($turfIds) . " turf(s) on {$pkg->name}.");
    }

}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Per-Turf Subscription Plans</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Select turfs to subscribe or renew, and unlock lower platform commission rates per turf.</p>
            </div>
        </div>

        <!-- Billing Cycle Switcher -->
        <div class="flex items-center gap-2 bg-gray-100 dark:bg-gray-900 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-700 self-start sm:self-auto">
            <button wire:click="$set('billingCycle', 'monthly')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $billingCycle === 'monthly' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
                Monthly
            </button>
            <button wire:click="$set('billingCycle', 'yearly')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5 {{ $billingCycle === 'yearly' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400' }}">
                <span>Yearly</span>
                <span class="px-1.5 py-0.5 text-[9px] font-black uppercase rounded-md bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300">Save</span>
            </button>
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

    <!-- STEP 1: TURF CHECKLIST SELECTION -->
    @php
        $user = auth()->user();
        $manageableLocations = $user ? $user->manageableLocations()->with(['turfs.activeSubscription.package'])->get() : collect();
        $allTurfsCount = $manageableLocations->pluck('turfs')->flatten()->count();
        $selectedCount = count($selectedTurfIds);
    @endphp

    <div class="bg-white dark:bg-gray-800 p-6 sm:p-8 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs space-y-5">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">STEP 1</span>
                <h2 class="text-xl font-black text-gray-900 dark:text-white">Select Turfs to Subscribe / Renew</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400">Pick which turfs you want to include in this subscription payment.</p>
            </div>
            <button wire:click="toggleAllTurfs" type="button" class="px-3 py-1.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded-xl transition cursor-pointer">
                {{ $selectedCount === $allTurfsCount ? 'Deselect All' : 'Select All' }}
            </button>
        </div>

        @if ($manageableLocations->isEmpty() || $allTurfsCount === 0)
            <div class="p-6 text-center text-gray-500 dark:text-gray-400 text-xs bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-dashed border-gray-300 dark:border-gray-700">
                No turfs available under your management. Create a location and turf first to subscribe.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($manageableLocations as $loc)
                    @if ($loc->turfs->isNotEmpty())
                        <div class="bg-gray-50/70 dark:bg-gray-900/40 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/60 space-y-3">
                            <span class="text-[11px] font-black uppercase tracking-wider text-gray-500 dark:text-gray-400 block">
                                📍 {{ $loc->name }}
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach ($loc->turfs as $turf)
                                    @php
                                        $isSelected = in_array($turf->id, $selectedTurfIds);
                                        $activeSub = $turf->activeSubscription;
                                    @endphp
                                    <div wire:click="toggleTurf({{ $turf->id }})"
                                        class="p-3.5 rounded-xl border transition cursor-pointer flex items-center justify-between gap-3 {{ $isSelected ? 'bg-indigo-50/80 dark:bg-indigo-950/40 border-indigo-500 dark:border-indigo-400 shadow-sm' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 opacity-80' }}">
                                        <div class="min-w-0">
                                            <span class="font-bold text-xs text-gray-900 dark:text-white block truncate">{{ $turf->name }}</span>
                                            <span class="text-[10px] text-gray-500 dark:text-gray-400 block truncate mt-0.5">
                                                Rate: {{ number_format($turf->commission_percentage, 2) }}% | {{ $activeSub ? $activeSub->package?->name : 'No Plan' }}
                                            </span>
                                            @if ($activeSub)
                                                <span class="text-[9px] text-emerald-600 dark:text-emerald-400 block font-semibold">Exp: {{ $activeSub->expires_at?->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                        <div class="h-5 w-5 rounded-md border flex items-center justify-center shrink-0 {{ $isSelected ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300 dark:border-gray-600' }}">
                                            @if ($isSelected)
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>

    <!-- STEP 2: SUBSCRIPTION PACKAGES CARDS -->
    <div class="space-y-2">
        <div class="px-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">STEP 2</span>
            <h2 class="text-xl font-black text-gray-900 dark:text-white">Select Subscription Package</h2>
        </div>

        @php
            $packages = SubscriptionPackage::where('is_active', true)->get();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($packages as $pkg)
                @php
                    $unitPrice = $billingCycle === 'yearly' ? (float)$pkg->yearly_amount : (float)$pkg->monthly_amount;
                    $totalPrice = $unitPrice * $selectedCount;
                    $durationText = $billingCycle === 'yearly' ? 'year' : 'month';
                @endphp

                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 shadow-xs hover:shadow-md transition p-6 sm:p-8 flex flex-col justify-between space-y-6">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">{{ $pkg->name }}</span>
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-indigo-50 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ $pkg->commission_percentage }}% Commission
                            </span>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-baseline gap-1">
                                <span class="text-3xl font-black text-gray-900 dark:text-white">₹{{ number_format($unitPrice, 2) }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">/ turf / {{ $durationText }}</span>
                            </div>
                            @if ($selectedCount > 0)
                                <p class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                                    Total: ₹{{ number_format($totalPrice, 2) }} for {{ $selectedCount }} turf(s)
                                </p>
                            @else
                                <p class="text-xs text-amber-600 dark:text-amber-400 font-semibold">
                                    Select at least 1 turf above to see total
                                </p>
                            @endif
                        </div>

                        @if ($pkg->description)
                            <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">{{ $pkg->description }}</p>
                        @endif

                        @if ($pkg->features && is_array($pkg->features))
                            <ul class="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                @foreach ($pkg->features as $feat)
                                    <li class="flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>{{ $feat }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <button wire:click="initiatePayment({{ $pkg->id }}, '{{ $billingCycle }}')"
                        @if ($selectedCount === 0) disabled @endif
                        type="button"
                        class="w-full py-3 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {{ $selectedCount > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm active:scale-[0.99]' : 'bg-gray-200 dark:bg-gray-700 text-gray-400 cursor-not-allowed' }}">
                        <span>Subscribe / Renew {{ $selectedCount }} Turf(s)</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            @empty
                <div class="col-span-full bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-200 dark:border-gray-700 text-center text-gray-500 dark:text-gray-400 space-y-3">
                    <span class="text-4xl block">📦</span>
                    <p class="font-bold text-gray-800 dark:text-gray-200">No active subscription packages available at the moment.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Razorpay Checkout Script Integration -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-razorpay-checkout', (data) => {
            const payload = data[0] || data;
            
            if (!payload.key) {
                alert('Razorpay key is not configured in SaaS Settings.');
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
                    Livewire.dispatch('verify-subscription-payment', [payload.payment_record_id, response.razorpay_payment_id, response.razorpay_signature || ""]);
                },
                "prefill": payload.prefill || {},
                "theme": {
                    "color": "#4F46E5"
                }
            };

            const rzp = new Razorpay(options);
            rzp.on('payment.failed', function (response) {
                alert("Payment Failed: " + (response.error.description || "Transaction cancelled."));
            });
            rzp.open();
        });
    });
</script>
