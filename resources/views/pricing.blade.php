<x-marketing-layout>
    <x-slot name="title">
        Pricing & Plans - Scalable Turf Scheduling & Zero Commission Options
    </x-slot>

    <!-- Pricing Area Wrapper -->
    <section x-data="{ annual: false }" class="py-20 lg:py-32 relative overflow-hidden">
        
        <!-- Headers -->
        <div class="max-w-4xl mx-auto px-6 text-center space-y-6 mb-16">
            <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-3.5 py-1.5 rounded-full border border-emerald-200/80 uppercase tracking-widest inline-block shadow-2xs">
                Transparent Arena Pricing
            </span>
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-slate-900 tracking-tight">
                Simple, Transparent <br>
                <span class="bg-gradient-to-r from-emerald-600 via-teal-600 to-indigo-600 bg-clip-text text-transparent">No-Surprise Pricing</span>
            </h1>
            <p class="text-base sm:text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed font-medium">
                Choose between our zero-upfront <strong class="text-slate-900">Default Commission Plan</strong> or upgrade to a flat-rate <strong class="text-slate-900">Subscription Plan</strong> to keep 100% of your booking revenue.
            </p>

            <!-- Toggle Switch -->
            <div class="flex items-center justify-center gap-4 pt-6">
                <span :class="!annual ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium'" class="text-xs sm:text-sm transition duration-150">
                    Monthly Billing
                </span>
                
                <button 
                    @click="annual = !annual" 
                    type="button"
                    class="w-13 h-7 rounded-full p-0.5 relative transition duration-300 focus:outline-none shadow-inner"
                    :class="annual ? 'bg-emerald-500' : 'bg-slate-300'"
                    aria-label="Toggle billing interval"
                >
                    <span 
                        :class="annual ? 'translate-x-6 bg-white' : 'translate-x-0 bg-white'" 
                        class="block w-6 h-6 rounded-full transition duration-300 transform shadow-md"
                    ></span>
                </button>

                <div class="flex items-center gap-2">
                    <span :class="annual ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium'" class="text-xs sm:text-sm transition duration-150">
                        Annual Billing
                    </span>
                    <span class="text-[10px] font-black px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full border border-emerald-200 shadow-2xs">
                        SAVE UP TO 17%
                    </span>
                </div>
            </div>
        </div>

        <!-- Pricing Cards Grid -->
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 {{ $packages->count() >= 2 ? 'lg:grid-cols-3' : '' }} gap-8 items-stretch mb-24">
            
            <!-- Plan 1: Default Commission Plan (Pay-As-You-Go) -->
            <div class="bg-white border-2 border-slate-200/90 rounded-3xl p-8 sm:p-10 flex flex-col justify-between hover:border-slate-300 hover:shadow-xl transition-all duration-300 shadow-sm relative group">
                <!-- Badge -->
                <span class="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-slate-100 border border-slate-200 text-slate-700 font-black text-[9px] uppercase tracking-wider rounded-full shadow-2xs">
                    PAY AS YOU GO
                </span>

                <div class="space-y-6">
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-2xl font-black text-slate-900">Default Commission</h3>
                        </div>
                        <p class="text-xs text-slate-500 mt-2 leading-relaxed">
                            Zero monthly subscription. Perfect for starting venues—pay only when you generate booking revenue.
                        </p>
                    </div>

                    <!-- Price -->
                    <div class="space-y-1">
                        <div class="flex items-baseline gap-1 text-slate-900">
                            <span class="text-5xl font-black font-mono tracking-tight">₹0</span>
                            <span class="text-xs font-bold text-slate-500">/ fixed fee</span>
                        </div>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 text-[11px] font-bold">
                            <span>⚡</span>
                            <span>{{ number_format($defaultCommission, 1) }}% platform fee per booking</span>
                        </div>
                    </div>

                    <hr class="border-slate-200/70">

                    <!-- Features -->
                    <div class="space-y-3">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400 block">Plan Includes:</span>
                        <ul class="space-y-3 text-xs text-slate-600 font-medium">
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500 font-bold shrink-0">✔</span>
                                <span><strong>Keep {{ number_format(100 - $defaultCommission, 1) }}%</strong> of slot booking revenue</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500 font-bold shrink-0">✔</span>
                                <span>Verified arena listing on Player App</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500 font-bold shrink-0">✔</span>
                                <span>Slot booking & calendar scheduler</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500 font-bold shrink-0">✔</span>
                                <span>Online UPI, Cards, NetBanking & Cash tracking</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500 font-bold shrink-0">✔</span>
                                <span>Automated wallet ledger & bank payouts</span>
                            </li>
                            <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                                <span>0.00% Platform Commission guarantee</span>
                            </li>
                            <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                                <span>Automated WhatsApp booking confirmations</span>
                            </li>
                            <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                                <span>Multi-staff manager permissions & roles</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="pt-8">
                    <a href="{{ auth()->check() ? route('dashboard') : route('register') }}" class="w-full inline-flex items-center justify-center py-3.5 bg-slate-100 hover:bg-slate-200 text-slate-800 font-bold rounded-xl text-xs uppercase tracking-wider transition-colors shadow-2xs">
                        {{ auth()->check() ? __('Go to Dashboard') : __('List With Commission Plan') }}
                    </a>
                </div>
            </div>

            <!-- Dynamic Subscription Plans from Database -->
            @forelse ($packages as $pkg)
                @php
                    $isPopular = $loop->first;
                    $isOffer = $pkg->isOfferValid();
                    $unitMonthly = $isOffer && $pkg->offer_monthly_amount ? (float) $pkg->offer_monthly_amount : (float) $pkg->monthly_amount;
                    $standardMonthly = (float) $pkg->monthly_amount;
                    $unitYearly = $isOffer && $pkg->offer_yearly_amount ? (float) $pkg->offer_yearly_amount : (float) $pkg->yearly_amount;
                    $standardYearly = (float) $pkg->yearly_amount;
                @endphp

                <div class="rounded-3xl p-8 sm:p-10 flex flex-col justify-between transition-all duration-300 relative group {{ $isPopular ? 'bg-gradient-to-b from-slate-900 via-slate-900 to-slate-950 border-2 border-emerald-500 shadow-2xl text-white' : 'bg-white border-2 border-slate-200/90 hover:border-indigo-300 shadow-sm hover:shadow-xl text-slate-800' }}">
                    
                    <!-- Popular / Offer Badge -->
                    <div class="absolute top-0 right-8 -translate-y-1/2 flex items-center gap-2">
                        @if ($isOffer && $pkg->offer_badge)
                            <span class="px-3.5 py-1 bg-gradient-to-r from-amber-500 to-orange-500 text-white font-black text-[9px] uppercase tracking-wider rounded-full shadow-md">
                                {{ $pkg->offer_badge }}
                            </span>
                        @elseif ($isPopular)
                            <span class="px-3 py-1 bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow-md">
                                MOST POPULAR
                            </span>
                        @endif
                    </div>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-2xl font-black {{ $isPopular ? 'text-white' : 'text-slate-900' }}">{{ $pkg->name }}</h3>
                            <p class="text-xs mt-2 leading-relaxed {{ $isPopular ? 'text-slate-400' : 'text-slate-500' }}">
                                {{ $pkg->description ?: __('Complete management suite with zero booking commission.') }}
                            </p>
                        </div>

                        <!-- Price Section with Alpine Monthly / Annual Toggle -->
                        <div class="space-y-1">
                            <!-- Monthly Price View -->
                            <div x-show="!annual" class="flex items-baseline gap-2 flex-wrap">
                                @if ($isOffer && $unitMonthly < $standardMonthly)
                                    <span class="text-4xl sm:text-5xl font-black font-mono tracking-tight {{ $isPopular ? 'text-white' : 'text-slate-900' }}">
                                        ₹{{ number_format($unitMonthly, 0) }}
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="text-xs {{ $isPopular ? 'text-slate-400' : 'text-slate-400' }} line-through font-bold">₹{{ number_format($standardMonthly, 0) }}</span>
                                        <span class="text-[9px] font-black uppercase text-amber-500 font-mono">Special Deal</span>
                                    </div>
                                @else
                                    <span class="text-4xl sm:text-5xl font-black font-mono tracking-tight {{ $isPopular ? 'text-white' : 'text-slate-900' }}">
                                        ₹{{ number_format($standardMonthly, 0) }}
                                    </span>
                                @endif
                                <span class="text-xs font-semibold {{ $isPopular ? 'text-slate-400' : 'text-slate-500' }}">/ turf / month</span>
                            </div>

                            <!-- Annual Price View -->
                            <div x-show="annual" x-cloak class="flex items-baseline gap-2 flex-wrap">
                                @if ($isOffer && $unitYearly < $standardYearly)
                                    <span class="text-4xl sm:text-5xl font-black font-mono tracking-tight {{ $isPopular ? 'text-white' : 'text-slate-900' }}">
                                        ₹{{ number_format($unitYearly, 0) }}
                                    </span>
                                    <div class="flex flex-col">
                                        <span class="text-xs {{ $isPopular ? 'text-slate-400' : 'text-slate-400' }} line-through font-bold">₹{{ number_format($standardYearly, 0) }}</span>
                                        <span class="text-[9px] font-black uppercase text-amber-500 font-mono">Special Deal</span>
                                    </div>
                                @else
                                    <span class="text-4xl sm:text-5xl font-black font-mono tracking-tight {{ $isPopular ? 'text-white' : 'text-slate-900' }}">
                                        ₹{{ number_format($standardYearly, 0) }}
                                    </span>
                                @endif
                                <span class="text-xs font-semibold {{ $isPopular ? 'text-slate-400' : 'text-slate-500' }}">/ turf / year</span>
                            </div>

                            <!-- 0.00% Commission Callout -->
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg {{ $isPopular ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-500/40' : 'bg-emerald-50 text-emerald-900 border border-emerald-200' }} text-[11px] font-bold">
                                <span>🎯</span>
                                <span>0.00% Platform Commission Guarantee</span>
                            </div>
                        </div>

                        <hr class="{{ $isPopular ? 'border-slate-800' : 'border-slate-200/70' }}">

                        <!-- Features -->
                        <div class="space-y-3">
                            <span class="text-[10px] font-black uppercase tracking-wider {{ $isPopular ? 'text-slate-400' : 'text-slate-400' }} block">Features & Perks:</span>
                            <ul class="space-y-3 text-xs font-medium {{ $isPopular ? 'text-slate-300' : 'text-slate-600' }}">
                                <li class="flex items-center gap-2.5 font-bold {{ $isPopular ? 'text-emerald-400' : 'text-emerald-700' }}">
                                    <span class="text-emerald-400 font-bold shrink-0">✔</span>
                                    <span>Keep 100% of your booking earnings</span>
                                </li>
                                @if (is_array($pkg->features) && count($pkg->features) > 0)
                                    @foreach ($pkg->features as $feature)
                                        <li class="flex items-center gap-2.5">
                                            <span class="text-emerald-400 font-bold shrink-0">✔</span>
                                            <span>{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                @else
                                    <li class="flex items-center gap-2.5">
                                        <span class="text-emerald-400 font-bold shrink-0">✔</span>
                                        <span>Unlimited bookings & scheduling</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span class="text-emerald-400 font-bold shrink-0">✔</span>
                                        <span>Full turf & pitch management suite</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span class="text-emerald-400 font-bold shrink-0">✔</span>
                                        <span>Multi-staff manager permissions</span>
                                    </li>
                                    <li class="flex items-center gap-2.5">
                                        <span class="text-emerald-400 font-bold shrink-0">✔</span>
                                        <span>Comprehensive reports & analytics</span>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>

                    <div class="pt-8">
                        @auth
                            @if (auth()->user()->hasRole('turf-admin'))
                                <a href="{{ route('turf.subscription') }}" class="w-full inline-flex items-center justify-center py-3.5 {{ $isPopular ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/25' : 'bg-slate-900 hover:bg-slate-800 text-white shadow-md' }} font-black rounded-xl text-xs uppercase tracking-wider transition-all duration-150">
                                    {{ __('Select Plan in Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('dashboard') }}" class="w-full inline-flex items-center justify-center py-3.5 {{ $isPopular ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/25' : 'bg-slate-900 hover:bg-slate-800 text-white shadow-md' }} font-black rounded-xl text-xs uppercase tracking-wider transition-all duration-150">
                                    {{ __('Manage in Dashboard') }}
                                </a>
                            @endif
                        @else
                            <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3.5 {{ $isPopular ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/25' : 'bg-slate-900 hover:bg-slate-800 text-white shadow-md' }} font-black rounded-xl text-xs uppercase tracking-wider transition-all duration-150">
                                {{ __('Get Started with :plan', ['plan' => $pkg->name]) }}
                            </a>
                        @endauth
                    </div>
                </div>
            @empty
                <!-- Fallback Pro Card if no packages seeded yet -->
                <div class="bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-emerald-500 rounded-3xl p-8 sm:p-10 flex flex-col justify-between transition duration-300 shadow-xl relative text-white">
                    <span class="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow-md">
                        PRO ADVANTAGE
                    </span>

                    <div class="space-y-6">
                        <div>
                            <h3 class="text-2xl font-black text-white">Pro Turf Partner</h3>
                            <p class="text-xs text-slate-400 mt-2">Designed to supercharge your sports arena business.</p>
                        </div>

                        <div class="flex items-baseline gap-1 text-white">
                            <span class="text-3xl font-black">₹</span>
                            <span x-text="annual ? '30,000' : '3,000'" class="text-5xl font-black transition-all">3,000</span>
                            <span x-text="annual ? '/ turf / year' : '/ turf / month'" class="text-xs text-slate-400">/ turf / month</span>
                        </div>

                        <hr class="border-slate-800">

                        <ul class="space-y-3.5 text-xs text-slate-300 font-semibold">
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-400 font-bold">✔</span> 0.00% Platform Commission
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-400 font-bold">✔</span> Full turf management
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-400 font-bold">✔</span> Unlimited bookings
                            </li>
                        </ul>
                    </div>

                    <div class="pt-8">
                        <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider transition">
                            Upgrade to Pro
                        </a>
                    </div>
                </div>
            @endforelse

        </div>

        <!-- Commission Plan vs Subscription Plan Comparison Banner -->
        <div class="max-w-4xl mx-auto px-6 mb-24">
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-8 sm:p-10 border border-slate-800 text-white shadow-xl relative overflow-hidden">
                <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative z-10 space-y-6">
                    <div class="space-y-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-400">Model Comparison</span>
                        <h3 class="text-2xl sm:text-3xl font-black tracking-tight">How do Commission vs. Subscription compare?</h3>
                        <p class="text-xs sm:text-sm text-slate-300 max-w-2xl leading-relaxed">
                            Pick the model that fits your cash flow. You can start on the Default Commission plan and switch to a 0% commission Subscription package as your booking volume grows.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div class="bg-white/5 rounded-2xl p-5 border border-white/10 space-y-2">
                            <span class="text-xs font-bold text-slate-300 block">Default Commission Plan</span>
                            <div class="text-2xl font-black font-mono text-white">₹0 / month</div>
                            <p class="text-xs text-slate-400">
                                Best for new venues testing demand. You pay {{ number_format($defaultCommission, 1) }}% only when players book. If there are no bookings, you pay nothing.
                            </p>
                        </div>
                        <div class="bg-emerald-500/10 rounded-2xl p-5 border border-emerald-500/30 space-y-2">
                            <span class="text-xs font-bold text-emerald-400 block">Subscription Membership</span>
                            <div class="text-2xl font-black font-mono text-emerald-300">0.00% Commission</div>
                            <p class="text-xs text-slate-300">
                                Best for established or busy turfs. Fixed monthly or annual fee, and you keep 100% of every rupee players pay.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FAQs -->
        <div class="max-w-4xl mx-auto px-6 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">BILLING & COMMISSION FAQS</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Frequently Asked Questions</h2>
            </div>

            <!-- FAQ Collapse lists -->
            <div class="space-y-4">
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How does the Default Commission plan work?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        On our Default Commission plan, there are zero upfront or recurring monthly fees. A {{ number_format($defaultCommission, 1) }}% platform facilitation fee is deducted only when players complete a booking online or at your venue. If your turf has no bookings in a given period, you pay ₹0.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How do Subscription plans give me 0.00% commission?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        When you subscribe to an active Subscription package, the platform commission rate for your registered turf drops to 0.00%. You keep 100% of your booking revenues, making it exceptionally profitable for active venues.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How is the Per-Turf fee calculated?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        Subscriptions are billed per active pitch or court. For example, if you manage 2 turfs under a subscription plan, your plan covers both turfs with zero booking commissions. Turfs without an active subscription automatically default to the Commission plan.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I switch between the Commission plan and a Subscription plan anytime?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        Yes. You can upgrade from the Default Commission plan to any Subscription plan instantly through your dashboard's Subscription page. If your subscription expires, your account simply transitions back to the Default Commission plan without interrupting player bookings.
                    </p>
                </details>
            </div>
        </div>
    </section>
</x-marketing-layout>
