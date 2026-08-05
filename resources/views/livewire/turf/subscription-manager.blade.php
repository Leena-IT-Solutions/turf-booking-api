<?php

use App\Models\SubscriptionPackage;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $billingCycle = 'monthly'; // 'monthly' or 'yearly'

    public function selectPlan(int $packageId, string $cycle)
    {
        $pkg = SubscriptionPackage::find($packageId);
        if (!$pkg) return;

        session()->flash('status', "Selected {$pkg->name} ({$cycle} billing). Payment integration coming soon!");
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

    <!-- Session Status Flash Alert -->
    @if (session('status'))
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-700/60 text-emerald-800 dark:text-emerald-300 text-xs font-bold flex items-center gap-2">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('status') }}
        </div>
    @endif

    @php
        $packages = SubscriptionPackage::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('monthly_amount', 'asc')
            ->get();
    @endphp

    <!-- Subscription Plans Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 w-full">
        @forelse ($packages as $pkg)
            @php
                $price = $billingCycle === 'yearly' ? $pkg->yearly_amount : $pkg->monthly_amount;
                $durationText = $billingCycle === 'yearly' ? 'Year (365 Days)' : 'Month (30 Days)';
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 shadow-sm flex flex-col justify-between relative transition hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md group">
                
                <div class="space-y-4">
                    <!-- Package Name & Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 dark:text-indigo-400">PLAN</span>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white leading-snug">
                                {{ $pkg->name }}
                            </h3>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700">
                            Active
                        </span>
                    </div>

                    <!-- Price Box -->
                    <div class="p-4 rounded-2xl bg-indigo-50/70 dark:bg-indigo-950/40 border border-indigo-100 dark:border-indigo-900/50 space-y-1">
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-black text-gray-900 dark:text-white">₹{{ number_format($price, 2) }}</span>
                            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">/ {{ $durationText }}</span>
                        </div>
                        <div class="text-[10px] font-bold text-amber-600 dark:text-amber-400 flex items-center gap-1">
                            <span>Platform Commission: {{ $pkg->commission_percentage }}%</span>
                        </div>
                    </div>

                    <!-- Description -->
                    @if ($pkg->description)
                        <p class="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
                            {{ $pkg->description }}
                        </p>
                    @endif

                    <!-- Features -->
                    @if (is_array($pkg->features) && count($pkg->features) > 0)
                        <div class="space-y-2 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                            <span class="text-[10px] font-extrabold uppercase text-gray-400 tracking-wider block">INCLUDED FEATURES</span>
                            <div class="space-y-1.5">
                                @foreach ($pkg->features as $feat)
                                    <div class="text-xs text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                        <span>{{ $feat }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Select / Subscribe Button -->
                <div class="pt-6 mt-6 border-t border-gray-100 dark:border-gray-700/60">
                    <button wire:click="selectPlan({{ $pkg->id }}, '{{ $billingCycle }}')" type="button"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm cursor-pointer active:scale-[0.99]">
                        <span>Subscribe Now</span>
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </button>
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

