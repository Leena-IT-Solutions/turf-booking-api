<?php

use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\TurfSubscription;
use App\Models\SaasSetting;
use App\Models\PaymentGatewayCharge;
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

    public function with(): array
    {
        $setting = SaasSetting::first();
        $gatewayCharges = PaymentGatewayCharge::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $defaultCommission = $setting ? (float) $setting->commission_percentage : 7.00;
        $commissionGst = $setting ? (float) $setting->commission_gst_percentage : 18.00;
        $effectiveCommission = round($defaultCommission * (1 + ($commissionGst / 100)), 2);

        return [
            'saasSetting' => $setting,
            'defaultCommission' => $defaultCommission,
            'commissionGst' => $commissionGst,
            'effectiveCommission' => $effectiveCommission,
            'gatewayCharges' => $gatewayCharges,
        ];
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
        $unitPrice = $cycle === 'yearly' ? $pkg->getEffectiveYearlyAmount() : $pkg->getEffectiveMonthlyAmount();
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
                'commission_percentage' => 0.00,
                'starts_at' => $startsAt,
                'expires_at' => $newExpiresAt,
                'status' => 'active',
            ]);
        }

        $pkg->incrementOfferClaim();

        session()->flash('status', "Payment successful! Subscription activated/renewed for " . count($turfIds) . " turf(s) on {$pkg->name}.");
    }

}; ?>

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-200 shadow-xs">
        <div class="flex items-center gap-3">
            <div class="p-3 bg-indigo-50 text-indigo-600 rounded-2xl">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-black text-gray-900 tracking-tight">Per-Turf Subscription Plans</h1>
                <p class="text-xs text-gray-500">Select turfs to subscribe or renew, and activate full software management features.</p>
            </div>
        </div>

        <!-- Billing Cycle Switcher -->
        <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl border border-gray-200 self-start sm:self-auto">
            <button wire:click="$set('billingCycle', 'monthly')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $billingCycle === 'monthly' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 ' }}">
                Monthly
            </button>
            <button wire:click="$set('billingCycle', 'yearly')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5 {{ $billingCycle === 'yearly' ? 'bg-white text-indigo-600 shadow-sm' : 'text-gray-500 ' }}">
                <span>Yearly</span>
                <span class="px-1.5 py-0.5 text-[9px] font-black uppercase rounded-md bg-emerald-100 text-emerald-700">Save</span>
            </button>
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

    <!-- STEP 1: TURF CHECKLIST SELECTION -->
    @php
        $user = auth()->user();
        $manageableLocations = $user ? $user->manageableLocations()->with(['turfs.activeSubscription.package'])->get() : collect();
        $allTurfsCount = $manageableLocations->pluck('turfs')->flatten()->count();
        $selectedCount = count($selectedTurfIds);
    @endphp

    <div class="bg-white p-6 sm:p-8 rounded-3xl border border-gray-200 shadow-xs space-y-5">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">STEP 1</span>
                <h2 class="text-xl font-black text-gray-900">Select Turfs to Subscribe / Renew</h2>
                <p class="text-xs text-gray-500">Pick which turfs you want to include in this subscription payment.</p>
            </div>
            <button wire:click="toggleAllTurfs" type="button" class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold rounded-xl transition cursor-pointer">
                {{ $selectedCount === $allTurfsCount ? 'Deselect All' : 'Select All' }}
            </button>
        </div>

        @if ($manageableLocations->isEmpty() || $allTurfsCount === 0)
            <div class="p-6 text-center text-gray-500 text-xs bg-gray-50 rounded-2xl border border-dashed border-gray-300">
                No turfs available under your management. Create a location and turf first to subscribe.
            </div>
        @else
            <div class="space-y-4">
                @foreach ($manageableLocations as $loc)
                    @if ($loc->turfs->isNotEmpty())
                        <div class="bg-gray-50/70 p-4 rounded-2xl border border-gray-200/80 space-y-3">
                            <span class="text-[11px] font-black uppercase tracking-wider text-gray-500 block">
                                📍 {{ $loc->name }}
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                @foreach ($loc->turfs as $turf)
                                    @php
                                        $isSelected = in_array($turf->id, $selectedTurfIds);
                                        $activeSub = $turf->activeSubscription;
                                    @endphp
                                    <div wire:click="toggleTurf({{ $turf->id }})"
                                        class="p-3.5 rounded-xl border transition cursor-pointer flex items-center justify-between gap-3 {{ $isSelected ? 'bg-indigo-50/80 border-indigo-500 shadow-sm' : 'bg-white border-gray-200 opacity-80' }}">
                                        <div class="min-w-0">
                                            <span class="font-bold text-xs text-gray-900 block truncate">{{ $turf->name }}</span>
                                            <span class="text-[10px] text-gray-500 block truncate mt-0.5">
                                                Plan: {{ $activeSub ? ($activeSub->package?->name ?? 'Subscribed') : 'No Active Plan' }}
                                            </span>
                                            @if ($activeSub)
                                                <span class="text-[9px] text-emerald-600 block font-semibold">Exp: {{ $activeSub->expires_at?->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                        <div class="h-5 w-5 rounded-md border flex items-center justify-center shrink-0 {{ $isSelected ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-gray-300 ' }}">
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
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">STEP 2</span>
            <h2 class="text-xl font-black text-gray-900">Select Subscription Package</h2>
        </div>

        @php
            $packages = SubscriptionPackage::where('is_active', true)->get();
        @endphp

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @forelse ($packages as $pkg)
                @php
                    $isOffer = $pkg->isOfferValid();
                    $standardPrice = $billingCycle === 'yearly' ? (float)$pkg->yearly_amount : (float)$pkg->monthly_amount;
                    $unitPrice = $billingCycle === 'yearly' ? $pkg->getEffectiveYearlyAmount() : $pkg->getEffectiveMonthlyAmount();
                    $totalPrice = $unitPrice * $selectedCount;
                    $durationText = $billingCycle === 'yearly' ? 'year' : 'month';
                @endphp

                <div class="bg-white rounded-3xl border {{ $isOffer ? 'border-amber-300 shadow-md ring-1 ring-amber-200' : 'border-gray-200 shadow-xs' }} hover:shadow-lg transition p-6 sm:p-8 flex flex-col justify-between space-y-6 relative overflow-hidden">
                    @if ($isOffer)
                        <div class="absolute -top-1 right-6 bg-gradient-to-r from-amber-500 to-orange-500 text-white text-[10px] font-extrabold uppercase tracking-wider px-3 py-1 rounded-b-xl shadow-xs flex items-center gap-1">
                            <span>{{ $pkg->offer_badge ?: 'Launch Offer' }}</span>
                        </div>
                    @endif

                    <div class="space-y-4 pt-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase tracking-wider text-indigo-600">{{ $pkg->name }}</span>
                        </div>

                        <div class="space-y-1">
                            @if ($isOffer && $unitPrice < $standardPrice)
                                <div class="flex items-baseline gap-2">
                                    <span class="text-3xl font-black text-amber-600">₹{{ number_format($unitPrice, 2) }}</span>
                                    <span class="text-base text-gray-400 line-through font-bold">₹{{ number_format($standardPrice, 2) }}</span>
                                    <span class="text-xs text-gray-500">/ turf / {{ $durationText }}</span>
                                </div>
                                @if ($pkg->offer_max_claims)
                                    <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-800 border border-amber-200 text-[10px] font-bold">
                                        <span>🔥 Only {{ $pkg->getRemainingOfferClaims() }} of {{ $pkg->offer_max_claims }} founder spots left!</span>
                                    </div>
                                @endif
                            @else
                                <div class="flex items-baseline gap-1">
                                    <span class="text-3xl font-black text-gray-900">₹{{ number_format($unitPrice, 2) }}</span>
                                    <span class="text-xs text-gray-500">/ turf / {{ $durationText }}</span>
                                </div>
                            @endif

                            @if ($selectedCount > 0)
                                <p class="text-xs font-bold text-indigo-600 pt-1">
                                    Total: ₹{{ number_format($totalPrice, 2) }} for {{ $selectedCount }} turf(s)
                                </p>
                            @else
                                <p class="text-xs text-amber-600 font-semibold pt-1">
                                    Select at least 1 turf above to see total
                                </p>
                            @endif
                        </div>

                        @if ($pkg->description)
                            <p class="text-xs text-gray-500 leading-relaxed">{{ $pkg->description }}</p>
                        @endif

                        @if ($pkg->features && is_array($pkg->features))
                            <ul class="space-y-2 pt-2 border-t border-gray-100">
                                @foreach ($pkg->features as $feat)
                                    <li class="flex items-center gap-2 text-xs text-gray-700">
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
                        class="w-full py-3 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer {{ $selectedCount > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white shadow-sm active:scale-[0.99]' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}">
                        <span>Subscribe / Renew {{ $selectedCount }} Turf(s)</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>
            @empty
                <div class="col-span-full bg-white p-12 rounded-3xl border border-gray-200 text-center text-gray-500 space-y-3">
                    <span class="text-4xl block">📦</span>
                    <p class="font-bold text-gray-800">No active subscription packages available at the moment.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- RATES & TRANSPARENCY: DEFAULT COMMISSION & PAYMENT GATEWAY CHARGES -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Default Platform Commission Card -->
        <div class="lg:col-span-5 bg-white p-6 sm:p-7 rounded-3xl border border-gray-200/80 shadow-xs flex flex-col justify-between space-y-6">
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2zM10 8.5a.5.5 0 11-1 0 .5.5 0 011 0zm5 5a.5.5 0 11-1 0 .5.5 0 011 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 block">Platform Commission</span>
                            <h3 class="text-sm font-bold text-gray-900">Default Baseline Rate</h3>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-100">
                        Standard
                    </span>
                </div>

                <div class="p-4 rounded-2xl bg-gradient-to-br from-indigo-50/50 via-white to-purple-50/30 border border-indigo-100/60">
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl sm:text-4xl font-black text-gray-900 font-mono tracking-tight">
                            {{ number_format($defaultCommission, 2) }}%
                        </span>
                        <span class="text-xs text-gray-500 font-medium">per booking</span>
                    </div>
                    @if ($commissionGst > 0)
                        <div class="mt-1 flex items-center gap-2 text-xs text-gray-600 flex-wrap">
                            <span>+ {{ number_format($commissionGst, 2) }}% GST</span>
                            @if ($saasSetting?->commission_gst_sac)
                                <span class="text-[10px] font-mono text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded">SAC {{ $saasSetting->commission_gst_sac }}</span>
                            @endif
                            <span class="font-bold text-indigo-700">≈ {{ number_format($effectiveCommission, 2) }}% effective</span>
                        </div>
                    @endif
                </div>

                <!-- Comparison Cards -->
                <div class="space-y-2.5">
                    <!-- With Subscription -->
                    <div class="p-3.5 rounded-2xl bg-emerald-50/70 border border-emerald-200/70 flex items-start gap-3">
                        <div class="w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-xs">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold text-emerald-950">With Active Subscription</span>
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase bg-emerald-200/80 text-emerald-800 font-mono">0.00% Commission</span>
                            </div>
                            <p class="text-[11px] text-emerald-800 mt-0.5 leading-relaxed">
                                Subscribed turfs pay <strong class="font-black">0% platform commission</strong> on all bookings. You keep 100% of your court booking earnings!
                            </p>
                        </div>
                    </div>

                    <!-- Without Subscription -->
                    <div class="p-3.5 rounded-2xl bg-gray-50/80 border border-gray-200/80 flex items-start gap-3">
                        <div class="w-5 h-5 rounded-full bg-amber-500 text-white flex items-center justify-center shrink-0 mt-0.5 font-bold text-[10px] shadow-xs">
                            !
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-bold text-gray-900">Without Subscription</span>
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase bg-amber-100 text-amber-800 font-mono">{{ number_format($defaultCommission, 2) }}% Commission</span>
                            </div>
                            <p class="text-[11px] text-gray-500 mt-0.5 leading-relaxed">
                                Non-subscribed turfs incur the default baseline commission on every online booking.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Details if configured -->
            @if ($saasSetting && ($saasSetting->max_commission_due > 0 || $saasSetting->commission_due_grace_days > 0))
                <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                    <span>Credit Limit: <strong class="text-gray-800 font-mono">₹{{ number_format($saasSetting->max_commission_due, 2) }}</strong></span>
                    <span>Grace Period: <strong class="text-gray-800 font-mono">{{ $saasSetting->commission_due_grace_days }} days</strong></span>
                </div>
            @endif
        </div>

        <!-- Payment Gateway Charges Card -->
        <div class="lg:col-span-7 bg-white p-6 sm:p-7 rounded-3xl border border-gray-200/80 shadow-xs flex flex-col justify-between space-y-4">
            <div class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-2xl bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-violet-600 block">Payment Gateway Charges</span>
                            <h3 class="text-sm font-bold text-gray-900">Direct Gateway MDR (Razorpay)</h3>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-violet-50 text-violet-700 border border-violet-100 self-start sm:self-auto">
                        Live Method Rates
                    </span>
                </div>

                <p class="text-xs text-gray-500 leading-relaxed">
                    Standard Merchant Discount Rates (MDR) applied by the payment aggregator per transaction on online customer payments:
                </p>

                <!-- Responsive Charges Table -->
                <div class="overflow-x-auto rounded-2xl border border-gray-200/80 bg-gray-50/40">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="border-b border-gray-200/80 bg-gray-100/70 text-[10px] font-black uppercase text-gray-500 tracking-wider">
                                <th class="py-2.5 px-3.5">Payment Method</th>
                                <th class="py-2.5 px-3 text-right">Base Fee</th>
                                <th class="py-2.5 px-3 text-right">GST (18%)</th>
                                <th class="py-2.5 px-3.5 text-right">Total Deduction</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($gatewayCharges as $charge)
                                <tr class="hover:bg-gray-50/80 transition">
                                    <td class="py-2.5 px-3.5">
                                        <div class="space-y-0.5">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-bold text-gray-900">{{ $charge->name }}</span>
                                                @if ($charge->code)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold uppercase bg-gray-100 text-gray-600">
                                                        {{ $charge->code }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($charge->description)
                                                <p class="text-[10px] text-gray-400 truncate max-w-xs">{{ $charge->description }}</p>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono font-bold text-gray-700 whitespace-nowrap">
                                        {{ number_format($charge->charge_percentage, 2) }}%
                                        @if ($charge->flat_fee > 0)
                                            <span class="text-[10px] text-gray-400 font-normal block">+₹{{ number_format($charge->flat_fee, 2) }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-right font-mono text-gray-500 whitespace-nowrap">
                                        {{ number_format($charge->tax_percentage, 2) }}%
                                    </td>
                                    <td class="py-2.5 px-3.5 text-right whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[11px] font-black font-mono {{ $charge->charge_percentage > 2.00 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                            {{ number_format($charge->total_percentage, 2) }}%
                                        </span>
                                        <span class="text-[9px] text-gray-400 font-medium block mt-0.5">
                                            ₹{{ number_format(1000 * ($charge->total_percentage / 100), 2) }}/₹1k
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-center text-xs text-gray-400">
                                        No active payment gateway charges configured.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="pt-2 flex items-center gap-2 text-[10px] text-gray-400">
                <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Gateway charges are deducted directly by Razorpay during payout settlement on online customer payments.</span>
            </div>
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
