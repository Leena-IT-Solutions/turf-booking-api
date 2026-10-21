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

        return [
            'saasSetting' => $setting,
            'defaultCommission' => $defaultCommission,
            'commissionGst' => $commissionGst,
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

        $user = auth()->user();
        $allowedTurfIds = $user->manageableTurfs()->pluck('turfs.id')->map(fn($id) => (int) $id)->all();
        $turfsToPay = array_values(array_unique(array_intersect(array_map('intval', $this->selectedTurfIds), $allowedTurfIds)));

        if (empty($turfsToPay)) {
            session()->flash('error', 'Please select at least one turf to subscribe/renew.');
            return;
        }

        if (!in_array($cycle, ['monthly', 'yearly'], true)) {
            session()->flash('error', 'Invalid billing cycle.');
            return;
        }

        $pkg = SubscriptionPackage::where('is_active', true)->find($packageId);
        if (!$pkg) {
            session()->flash('error', 'Selected subscription package not found.');
            return;
        }

        $turfCount = count($turfsToPay);
        $unitPrice = $cycle === 'yearly' ? $pkg->getEffectiveYearlyAmount() : $pkg->getEffectiveMonthlyAmount();
        $totalPrice = round($unitPrice * $turfCount, 2);
        $amountInPaise = (int) round($totalPrice * 100);


        $setting = SaasSetting::first();
        $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        if (!$rzpKey || !$rzpSecret) {
            session()->flash('error', 'Online payments are not configured yet. Please contact support.');
            return;
        }

        // A real Razorpay order is required: its id is what the payment signature is checked against.
        $orderId = null;
        try {
            $response = Http::withBasicAuth($rzpKey, $rzpSecret)
                ->timeout(15)
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
                $orderId = $response->json('id');
            } else {
                \Illuminate\Support\Facades\Log::warning('Razorpay subscription order failed', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Razorpay subscription order error', ['error' => $e->getMessage()]);
        }

        if (!$orderId) {
            session()->flash('error', 'Could not start the payment with Razorpay. Please try again in a moment.');
            return;
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


        $this->dispatch('open-razorpay-checkout', payload: [
            'key' => $rzpKey,
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
        if (!$paymentRecord || (int) $paymentRecord->user_id !== (int) auth()->id()) {
            session()->flash('error', 'Payment record not found.');
            return;
        }

        if ($paymentRecord->status !== 'pending') {
            session()->flash('error', 'This payment has already been processed.');
            return;
        }

        $setting = SaasSetting::first();
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        // Always verify Razorpay's HMAC signature. No secret, order, payment id or signature = no activation.
        if (!$rzpSecret || !$paymentRecord->razorpay_order_id || !$paymentId || !$signature) {
            session()->flash('error', 'Payment verification failed: missing payment details.');
            return;
        }

        $expectedSignature = hash_hmac('sha256', $paymentRecord->razorpay_order_id . '|' . $paymentId, $rzpSecret);
        if (!hash_equals($expectedSignature, (string) $signature)) {
            session()->flash('error', 'Payment verification failed: invalid signature.');
            return;
        }

        $pkg = SubscriptionPackage::find($paymentRecord->subscription_package_id);
        if (!$pkg) {
            session()->flash('error', 'Package not found.');
            return;
        }

        // Claim the record atomically so the same payment can't activate twice (double click / replay).
        $claimed = SubscriptionPayment::whereKey($paymentRecord->id)
            ->where('status', 'pending')
            ->update([
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
                'status' => 'completed',
            ]);
        if ($claimed !== 1) {
            session()->flash('error', 'This payment has already been processed.');
            return;
        }
        $paymentRecord->refresh();

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
                $owner = $turf->owner;
                if ($owner && $owner->onTrial()) {
                    $startsAt = $owner->trial_ends_at->copy();
                    $newExpiresAt = $owner->trial_ends_at->copy()->addDays($days);
                } else {
                    $startsAt = now();
                    $newExpiresAt = now()->addDays($days);
                }

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
        $hasActivePlan = $manageableLocations->pluck('turfs')->flatten()->filter(fn($t) => $t->activeSubscription !== null)->isNotEmpty();
    @endphp

    <!-- Free Trial Banner -->
    @if ($user && $user->onTrial())
        @php
            $daysLeft = $user->trialDaysLeft();
            $isAmber = $daysLeft <= 7;
        @endphp
        <div class="p-5 sm:p-6 rounded-3xl border transition-all flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 {{ $isAmber ? 'bg-gradient-to-r from-amber-50/90 via-orange-50/50 to-amber-50/80 border-amber-300 text-amber-950 shadow-sm' : 'bg-gradient-to-r from-emerald-50/90 via-teal-50/50 to-emerald-50/80 border-emerald-300 text-emerald-950 shadow-sm' }}">
            <div class="flex items-center gap-3.5">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 {{ $isAmber ? 'bg-amber-500 text-white shadow-xs' : 'bg-emerald-600 text-white shadow-xs' }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-xs font-black uppercase tracking-wider {{ $isAmber ? 'text-amber-800' : 'text-emerald-800' }}">Free Trial</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase {{ $isAmber ? 'bg-amber-200 text-amber-900' : 'bg-emerald-200 text-emerald-900' }}">
                            {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left
                        </span>
                    </div>
                    <p class="text-sm font-black tracking-tight">
                        Free trial: {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('day', $daysLeft) }} left <span class="font-normal text-xs opacity-90">(ends {{ $user->trial_ends_at?->format('d M Y') }})</span>
                    </p>
                    <p class="text-xs opacity-80 leading-relaxed font-medium">
                        You pay 0% commission until then. Subscribe now and your plan starts when the trial ends.
                    </p>
                </div>
            </div>
            <a href="#packagesSection" class="shrink-0 px-4 py-2.5 rounded-xl text-xs font-black uppercase tracking-wider transition shadow-xs {{ $isAmber ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-emerald-600 hover:bg-emerald-700 text-white' }}">
                Lock In Plan &rarr;
            </a>
        </div>
    @elseif ($user && $user->trial_started_at !== null && !$hasActivePlan)
        <div class="p-4 rounded-2xl bg-gray-50 border border-gray-200 text-gray-700 text-xs font-semibold flex items-center justify-between gap-3 flex-wrap">
            <div class="flex items-center gap-2.5">
                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                <span>Your free trial ended on {{ $user->trial_ends_at?->format('d M Y') }}. Subscribe to get 0% commission again.</span>
            </div>
            <a href="#packagesSection" class="text-indigo-600 hover:text-indigo-800 font-bold underline">
                View Plans &rarr;
            </a>
        </div>
    @endif

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
                                        $owner = $turf->owner;
                                    @endphp
                                    <div wire:click="toggleTurf({{ $turf->id }})"
                                        class="p-3.5 rounded-xl border transition cursor-pointer flex items-center justify-between gap-3 {{ $isSelected ? 'bg-indigo-50/80 border-indigo-500 shadow-sm' : 'bg-white border-gray-200 opacity-80' }}">
                                        <div class="min-w-0">
                                            <span class="font-bold text-xs text-gray-900 block truncate">{{ $turf->name }}</span>
                                            <span class="text-[10px] text-gray-500 block truncate mt-0.5">
                                                @if ($activeSub)
                                                    Plan: {{ $activeSub->package?->name ?? 'Subscribed' }}
                                                @elseif ($owner && $owner->onTrial())
                                                    Plan: <strong class="text-emerald-700 font-extrabold">Free Trial</strong> · ends {{ $owner->trial_ends_at?->format('d M') }}
                                                @elseif ($owner && $owner->trial_started_at !== null)
                                                    Plan: <span class="text-gray-500">Trial ended ({{ $owner->trial_ends_at?->format('d M') }})</span>
                                                @else
                                                    Plan: No Active Plan
                                                @endif
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

    <!-- STEP 2: SUBSCRIPTION PACKAGES -->
    <div id="packagesSection" class="space-y-4">
        <div class="px-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">STEP 2</span>
            <h2 class="text-xl font-black text-gray-900">Select Subscription Package</h2>
            <p class="text-xs text-gray-500">Choose a software package to activate digital booking management and 0% commission.</p>
        </div>

        @php
            $packages = SubscriptionPackage::where('is_active', true)->get();
            $pkgCount = $packages->count();
        @endphp

        @if ($pkgCount === 0)
            <div class="bg-white p-12 rounded-3xl border border-gray-200 text-center text-gray-500 space-y-3">
                <span class="text-4xl block">📦</span>
                <p class="font-bold text-gray-800">No active subscription packages available at the moment.</p>
            </div>
        @else
            <div class="space-y-6">
                @foreach ($packages as $pkg)
                    @php
                        $isOffer = $pkg->isOfferValid();
                        $standardPrice = $billingCycle === 'yearly' ? (float)$pkg->yearly_amount : (float)$pkg->monthly_amount;
                        $unitPrice = $billingCycle === 'yearly' ? $pkg->getEffectiveYearlyAmount() : $pkg->getEffectiveMonthlyAmount();
                        $totalPrice = $unitPrice * $selectedCount;
                        $durationText = $billingCycle === 'yearly' ? 'year' : 'month';
                    @endphp

                    <div class="bg-white rounded-3xl border border-gray-200/90 shadow-sm hover:shadow-md transition-all duration-300 overflow-hidden w-full">
                        <div class="grid grid-cols-1 lg:grid-cols-12">
                            <!-- Left: Plan Details & Included Features (7 cols) -->
                            <div class="lg:col-span-7 p-6 sm:p-8 lg:p-10 flex flex-col justify-between space-y-8">
                                <div class="space-y-5">
                                    <!-- Title & Offer Badge -->
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-2.5 flex-wrap">
                                            <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-100">
                                                PARTNER PLAN
                                            </span>
                                            @if ($isOffer)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-gradient-to-r from-amber-500 to-orange-500 text-white shadow-xs">
                                                    {{ $pkg->offer_badge ?: 'Launch Offer' }}
                                                </span>
                                            @endif
                                        </div>
                                        <h3 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight">{{ $pkg->name }}</h3>
                                        @if ($pkg->description)
                                            <p class="text-xs sm:text-sm text-gray-500 leading-relaxed max-w-xl">{{ $pkg->description }}</p>
                                        @endif
                                    </div>

                                    <!-- Features List -->
                                    @if ($pkg->features && is_array($pkg->features))
                                        <div class="pt-4 border-t border-gray-100 space-y-3">
                                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Everything included in this package</span>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                                                @foreach ($pkg->features as $feat)
                                                    <div class="flex items-start gap-3">
                                                        <div class="w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center shrink-0 mt-0.5">
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                                        </div>
                                                        <span class="text-xs font-semibold text-gray-700 leading-snug">{{ $feat }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <!-- Zero Commission Callout -->
                                <div class="p-4 rounded-2xl bg-emerald-50/70 border border-emerald-200/80 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-xs">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-xs font-bold text-emerald-900 block">0.00% Platform Commission Guarantee</span>
                                        <p class="text-[11px] text-emerald-700 mt-0.5">Keep 100% of your court booking revenue. No hidden platform fees.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Pricing, Summary & Checkout Card (5 cols) -->
                            <div class="lg:col-span-5 bg-gradient-to-b from-indigo-50/70 via-purple-50/30 to-gray-50/80 border-t lg:border-t-0 lg:border-l border-gray-200/80 p-6 sm:p-8 lg:p-10 flex flex-col justify-between space-y-6">
                                <div class="space-y-6">
                                    <!-- Price Header -->
                                    <div class="space-y-2">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-500 block">Pricing & Billing</span>
                                        <div class="flex items-baseline gap-2 flex-wrap">
                                            @if ($isOffer && $unitPrice < $standardPrice)
                                                <span class="text-4xl sm:text-5xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($unitPrice, 2) }}</span>
                                                <div class="flex flex-col">
                                                    <span class="text-sm text-gray-400 line-through font-bold">₹{{ number_format($standardPrice, 2) }}</span>
                                                    <span class="text-[10px] font-black uppercase text-amber-600 font-mono">Special Offer</span>
                                                </div>
                                            @else
                                                <span class="text-4xl sm:text-5xl font-black text-gray-900 font-mono tracking-tight">₹{{ number_format($unitPrice, 2) }}</span>
                                            @endif
                                            <span class="text-xs font-semibold text-gray-500">/ turf / {{ $durationText }}</span>
                                        </div>
                                    </div>

                                    <!-- Order Summary Breakdown Card -->
                                    <div class="p-4 rounded-2xl bg-white border border-gray-200/90 shadow-2xs space-y-3">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-400 block">Subscription Summary</span>
                                        
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-600">Selected Turfs:</span>
                                            <span class="font-bold text-gray-900">{{ $selectedCount }} turf(s)</span>
                                        </div>
                                        <div class="flex items-center justify-between text-xs">
                                            <span class="text-gray-600">Billing Interval:</span>
                                            <span class="font-bold text-gray-900 uppercase">{{ ucfirst($billingCycle) }}</span>
                                        </div>
                                        <div class="pt-2 border-t border-gray-100 flex items-baseline justify-between">
                                            <span class="text-xs font-bold text-gray-900">Total Payable:</span>
                                            <span class="text-2xl font-black text-indigo-600 font-mono">₹{{ number_format($totalPrice, 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Button & Reassurance -->
                                <div class="space-y-3 pt-2">
                                    <button wire:click="initiatePayment({{ $pkg->id }}, '{{ $billingCycle }}')"
                                        wire:loading.attr="disabled" wire:target="initiatePayment"
                                        @if ($selectedCount === 0) disabled @endif
                                        type="button"
                                        class="w-full py-4 px-6 rounded-2xl text-xs font-black uppercase tracking-wider transition-all flex items-center justify-center gap-2 cursor-pointer shadow-md disabled:opacity-70 {{ $selectedCount > 0 ? 'bg-indigo-600 hover:bg-indigo-700 text-white active:scale-[0.99] hover:shadow-indigo-200' : 'bg-gray-200 text-gray-400 cursor-not-allowed shadow-none' }}">
                                        <span wire:loading.remove wire:target="initiatePayment">Subscribe / Renew {{ $selectedCount > 0 ? "($selectedCount Turfs)" : '' }}</span>
                                        <span wire:loading wire:target="initiatePayment">Opening secure checkout…</span>
                                        <svg wire:loading.remove wire:target="initiatePayment" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                        <svg wire:loading wire:target="initiatePayment" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                                    </button>

                                    <div class="flex items-center justify-center gap-3 text-[10px] text-gray-400 font-medium">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                            SSL 256-bit Secure
                                        </span>
                                        <span>•</span>
                                        <span>Powered by Razorpay</span>
                                        <span>•</span>
                                        <span>Instant Activation</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- FEE SCHEDULE & TRANSPARENCY: DEFAULT COMMISSION & PAYMENT GATEWAY CHARGES -->
    <div class="pt-8 mt-6 border-t border-gray-200/80 space-y-4">
        <div class="px-2">
            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600">PRICING & FEE TRANSPARENCY</span>
            <h2 class="text-xl font-black text-gray-900">Platform Commission & Payment Gateway Charges</h2>
            <p class="text-xs text-gray-500">Transparent breakdown of standard booking commissions and customer payment processing fees.</p>
        </div>

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

                <div class="p-4 rounded-2xl bg-gradient-to-br from-indigo-50/50 via-white to-purple-50/30 border border-indigo-100/60 space-y-1.5">
                    <div class="flex items-baseline gap-2 flex-wrap">
                        <span class="text-3xl sm:text-4xl font-black text-gray-900 font-mono tracking-tight">
                            {{ number_format($defaultCommission, 2) }}%
                        </span>
                        <span class="text-xs text-gray-500 font-medium">per booking</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-extrabold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Inclusive of GST
                        </span>
                    </div>
                    @if ($commissionGst > 0)
                        <div class="flex items-center gap-2 text-xs text-gray-600 flex-wrap">
                            <span>Includes {{ number_format($commissionGst, 2) }}% GST</span>
                            @if ($saasSetting?->commission_gst_sac)
                                <span class="text-[10px] font-mono text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">SAC {{ $saasSetting->commission_gst_sac }}</span>
                            @endif
                            @php
                                $baseCommission = round($defaultCommission / (1 + ($commissionGst / 100)), 2);
                                $gstPortion = round($defaultCommission - $baseCommission, 2);
                            @endphp
                            <span class="text-[11px] text-gray-500 font-mono">(Base: {{ number_format($baseCommission, 2) }}% + GST: {{ number_format($gstPortion, 2) }}%)</span>
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
                                <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase bg-amber-100 text-amber-800 font-mono">{{ number_format($defaultCommission, 2) }}% (Incl. GST)</span>
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
@assets
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endassets

@script
<script>
    $wire.on('open-razorpay-checkout', (event) => {
        const payload = event.payload || event[0] || event;

        if (!payload || !payload.key) {
            alert('Razorpay key is not configured in SaaS Settings.');
            return;
        }
        if (typeof window.Razorpay === 'undefined') {
            alert('Could not load the Razorpay checkout. Please check your internet connection and try again.');
            return;
        }

        const options = {
            key: payload.key,
            amount: payload.amount,
            currency: payload.currency || 'INR',
            name: payload.name,
            description: payload.description,
            prefill: payload.prefill || {},
            theme: { color: '#4F46E5' },
            handler: function (response) {
                $wire.verifyPayment(payload.payment_record_id, response.razorpay_payment_id, response.razorpay_signature || '');
            },
        };
        if (payload.order_id) {
            options.order_id = payload.order_id;
        }

        const rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function (response) {
            alert('Payment Failed: ' + ((response.error && response.error.description) || 'Transaction cancelled.'));
        });
        rzp.open();
    });
</script>
@endscript
</div>

