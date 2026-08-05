<?php

use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\TurfSubscription;
use App\Models\SaasSetting;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $billingCycle = 'monthly'; // 'monthly' or 'yearly'
    public string $razorpayKey = '';

    public function mount()
    {
        $setting = SaasSetting::first();
        $this->razorpayKey = $setting?->razorpay_key ?: (config('services.razorpay.key') ?: '');
    }

    public function initiatePayment(int $packageId, string $cycle)
    {
        $pkg = SubscriptionPackage::find($packageId);
        if (!$pkg) {
            session()->flash('error', 'Selected subscription package not found.');
            return;
        }

        $user = auth()->user();
        $price = $cycle === 'yearly' ? (float)$pkg->yearly_amount : (float)$pkg->monthly_amount;
        $amountInPaise = (int) round($price * 100);

        $setting = SaasSetting::first();
        $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        $orderId = null;

        // Create Razorpay Order if keys configured
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
                        ],
                    ]);

                if ($response->successful()) {
                    $orderData = $response->json();
                    $orderId = $orderData['id'] ?? null;
                }
            } catch (\Exception $e) {
                // Fallback to manual order reference if network error
            }
        }

        $payment = SubscriptionPayment::create([
            'user_id' => $user->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => $cycle,
            'amount' => $price,
            'razorpay_order_id' => $orderId,
            'status' => 'pending',
        ]);

        $this->dispatch('open-razorpay-checkout', [
            'key' => $this->razorpayKey,
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'name' => config('app.name', 'TurfBooking'),
            'description' => "Subscription: {$pkg->name} ({$cycle})",
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
    public function verifyPayment($data = null)
    {
        if (is_array($data)) {
            $paymentId = $data['razorpay_payment_id'] ?? null;
            $paymentRecordId = $data['payment_record_id'] ?? null;
            $signature = $data['razorpay_signature'] ?? null;
        } else {
            $paymentId = func_get_arg(0) ?? null;
            $paymentRecordId = func_num_args() > 1 ? func_get_arg(1) : null;
            $signature = null;
        }


        $paymentRecord = SubscriptionPayment::find($paymentRecordId);
        if (!$paymentRecord) {
            session()->flash('error', 'Payment record not found.');
            return;
        }

        $user = auth()->user();
        $pkg = SubscriptionPackage::find($paymentRecord->subscription_package_id);
        if (!$pkg) {
            session()->flash('error', 'Package not found.');
            return;
        }

        // Update payment record
        $paymentRecord->update([
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'status' => 'completed',
        ]);

        // Deactivate previous active subscriptions for this Turf Admin
        TurfSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Activate new subscription
        $days = $paymentRecord->billing_cycle === 'yearly' ? 365 : 30;

        TurfSubscription::create([
            'user_id' => $user->id,
            'subscription_package_id' => $pkg->id,
            'billing_cycle' => $paymentRecord->billing_cycle,
            'price' => $paymentRecord->amount,
            'commission_percentage' => $pkg->commission_percentage,
            'starts_at' => now(),
            'expires_at' => now()->addDays($days),
            'status' => 'active',
        ]);

        session()->flash('status', "Payment successful! Subscribed to {$pkg->name}. Your new commission rate is {$pkg->commission_percentage}%.");
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
                <h1 class="text-2xl font-black text-gray-900 dark:text-white tracking-tight">Subscription Plans</h1>
                <p class="text-xs text-gray-500 dark:text-gray-400">Choose the best subscription package for your turf management.</p>
            </div>
        </div>

        <!-- Monthly / Yearly Toggle Switch -->
        <div class="flex items-center gap-3 bg-gray-100 dark:bg-gray-900 p-1.5 rounded-2xl border border-gray-200 dark:border-gray-700 self-start sm:self-auto">
            <button wire:click="$set('billingCycle', 'monthly')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer {{ $billingCycle === 'monthly' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Monthly Billing
            </button>
            <button wire:click="$set('billingCycle', 'yearly')" type="button"
                class="px-4 py-2 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5 {{ $billingCycle === 'yearly' ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                Yearly Billing
                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300">Save Big</span>
            </button>
        </div>
    </div>

    @php
        $activeSub = auth()->user()->activeSubscription;
    @endphp

    <!-- CURRENT ACTIVE SUBSCRIPTION CARD -->
    <div class="bg-white dark:bg-gray-800 rounded-3xl border {{ $activeSub ? 'border-indigo-200 dark:border-indigo-700/60 shadow-md' : 'border-amber-200 dark:border-amber-700/60' }} p-6 sm:p-8 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-gray-100 dark:border-gray-700/60">
            <div class="space-y-1">
                <div class="flex items-center gap-2.5">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400 dark:text-gray-500">CURRENT PLAN</span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black uppercase {{ $activeSub ? 'bg-indigo-100 dark:bg-indigo-950/80 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-700' : 'bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-700' }}">
                        {{ $activeSub ? '● ' . ($activeSub->package->name ?? 'Paid Plan') : '○ Default Free Plan' }}
                    </span>
                </div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white">
                    {{ $activeSub ? ($activeSub->package->name ?? 'Subscription Active') : 'Default 7.00% Commission Plan' }}
                </h2>
            </div>

            <div class="flex items-center gap-4 bg-gray-50/90 dark:bg-gray-900/70 px-5 py-3 rounded-2xl border border-gray-100 dark:border-gray-700/60 shrink-0">
                <div>
                    <span class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider block">YOUR COMMISSION RATE</span>
                    <span class="text-2xl font-black {{ $activeSub ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                        {{ $activeSub ? number_format($activeSub->commission_percentage, 2) : '7.00' }}%
                    </span>
                </div>
            </div>
        </div>

        @if ($activeSub)
            <!-- Active Paid Subscription Details -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div class="bg-gray-50 dark:bg-gray-900/50 p-3.5 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-0.5">Billing Cycle</span>
                    <span class="font-extrabold text-gray-800 dark:text-gray-200 uppercase">{{ $activeSub->billing_cycle }}</span>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 p-3.5 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-0.5">Subscription Price</span>
                    <span class="font-extrabold text-gray-800 dark:text-gray-200">₹{{ number_format($activeSub->price, 2) }}</span>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/50 p-3.5 rounded-2xl border border-gray-100 dark:border-gray-700/50">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block mb-0.5">Valid Until</span>
                    <span class="font-extrabold text-indigo-600 dark:text-indigo-400">{{ $activeSub->expires_at->format('d M Y, h:i A') }}</span>
                </div>
            </div>
        @else
            <!-- Free Plan Recommendation Banner -->
            <div class="bg-amber-500/10 border border-amber-500/30 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded-xl shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h4 class="text-xs font-black text-amber-800 dark:text-amber-300 uppercase tracking-wide">Lower Your Commission Rate!</h4>
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                            You are currently on the default <strong>7% commission plan</strong>. Subscribe to one of our premium packages below to lower your platform commission rate!
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <!-- Session Status Flash Alert -->
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

    @php
        $packages = SubscriptionPackage::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('monthly_amount', 'asc')
            ->get();
    @endphp

    <!-- Subscription Plans List (Full Width Cards) -->
    <div class="flex flex-col space-y-4 w-full">
        @forelse ($packages as $pkg)
            @php
                $price = $billingCycle === 'yearly' ? $pkg->yearly_amount : $pkg->monthly_amount;
                $durationText = $billingCycle === 'yearly' ? 'Year (365 Days)' : 'Month (30 Days)';
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm flex flex-col space-y-5 transition hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md w-full">
                
                <!-- Top Header Row: Plan Name, Status & Subscribe Action -->
                <div class="flex items-start justify-between gap-4 w-full">
                    <div class="flex items-center gap-3">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">SUBSCRIPTION PLAN</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white leading-snug">
                                {{ $pkg->name }}
                            </h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shrink-0">
                            Active
                        </span>
                    </div>

                    <!-- Subscribe Button (Right Top Aligned) -->
                    <button wire:click="initiatePayment({{ $pkg->id }}, '{{ $billingCycle }}')" type="button"
                        class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm cursor-pointer active:scale-[0.99] shrink-0">
                        <span>Subscribe Now</span>
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
                </div>

                <!-- Description & Features (Vertical Stack) -->
                <div class="space-y-3 min-w-0">
                    @if ($pkg->description)
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            {{ $pkg->description }}
                        </p>
                    @endif

                    @if (is_array($pkg->features) && count($pkg->features) > 0)
                        <div class="space-y-1.5 pt-1">
                            <span class="text-[10px] font-extrabold uppercase text-gray-400 tracking-wider block">INCLUDED FEATURES</span>
                            <div class="space-y-1">
                                @foreach ($pkg->features as $feat)
                                    <div class="text-xs text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        <span class="break-words">{{ $feat }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Dedicated Full Width Row for Pricing & Commission -->
                <div class="w-full bg-gray-50/90 dark:bg-gray-900/60 p-4 rounded-2xl border border-gray-100 dark:border-gray-700/60 flex flex-wrap sm:flex-nowrap items-center justify-between gap-4">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[10px] font-extrabold text-gray-400 uppercase tracking-wider block">PRICE:</span>
                        <span class="text-2xl font-black text-gray-900 dark:text-white">₹{{ number_format($price, 2) }}</span>
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">/ {{ $durationText }}</span>
                    </div>

                    <div class="px-4 py-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-center shrink-0">
                        <span class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-wider block">PLATFORM COMMISSION</span>
                        <span class="text-base font-black text-amber-600 dark:text-amber-400">{{ $pkg->commission_percentage }}%</span>
                    </div>
                </div>

            </div>

        @empty
            <div class="col-span-full bg-white dark:bg-gray-800 p-12 rounded-3xl border border-gray-200 dark:border-gray-700 text-center text-gray-500 dark:text-gray-400 space-y-3">
                <span class="text-4xl block">📦</span>
                <p class="font-bold text-gray-800 dark:text-gray-200">No active subscription packages available at the moment.</p>
            </div>
        @endforelse
    </div>
</div>

<!-- Razorpay Checkout Script Integration -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('open-razorpay-checkout', (data) => {
            const payload = data[0] || data;
            
            // Fallback: If Razorpay keys are not configured yet, simulate successful subscription
            if (!payload.key) {
                if (confirm(`Razorpay key is not configured in SaaS Settings.\n\nWould you like to simulate successful payment for "${payload.package_name}"?`)) {
                    Livewire.dispatch('verify-subscription-payment', {
                        razorpay_payment_id: 'pay_simulated_' + Date.now(),
                        payment_record_id: payload.payment_record_id,
                        razorpay_signature: 'simulated_sig'
                    });
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
                    Livewire.dispatch('verify-subscription-payment', {
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id || payload.order_id,
                        razorpay_signature: response.razorpay_signature || "",
                        payment_record_id: payload.payment_record_id
                    });
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


