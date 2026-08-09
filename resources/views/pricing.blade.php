<x-marketing-layout>
    <x-slot name="title">
        Pricing - Scale Your Sport Arena Business Cost-Effectively
    </x-slot>

    <!-- Pricing Area Wrapper -->
    <section x-data="{ annual: false }" class="py-20 lg:py-32 relative overflow-hidden">
        
        <!-- Headers -->
        <div class="max-w-4xl mx-auto px-6 text-center space-y-6 mb-16">
            <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Per-Turf Subscription</span>
            <h1 class="text-4xl sm:text-5xl font-black text-slate-900 tracking-tight">
                Simple, Transparent <br>
                <span class="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">No-Surprise Pricing</span>
            </h1>
            <p class="text-base text-slate-550 max-w-xl mx-auto leading-relaxed font-medium">
                Choose the subscription model that fits your business. List for free to gain visibility or upgrade to Pro for full management capabilities.
            </p>

            <!-- Toggle Switch -->
            <div class="flex items-center justify-center gap-4 pt-6">
                <span :class="!annual ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium'" class="text-xs sm:text-sm transition duration-150">Monthly Billing</span>
                
                <button 
                    @click="annual = !annual" 
                    type="button"
                    class="w-12 h-6 rounded-full bg-slate-200 p-0.5 relative transition duration-300 focus:outline-none"
                    aria-label="Toggle billing interval"
                >
                    <span 
                        :class="annual ? 'translate-x-6 bg-emerald-500' : 'translate-x-0 bg-slate-400'" 
                        class="block w-5 h-5 rounded-full transition duration-300 transform"
                    ></span>
                </button>

                <div class="flex items-center gap-2">
                    <span :class="annual ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium'" class="text-xs sm:text-sm transition duration-150">Annual Billing</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 bg-emerald-500/10 text-emerald-600 rounded-full border border-emerald-500/20">SAVE ~17%</span>
                </div>
            </div>
        </div>

        <!-- Pricing Cards Grid -->
        <div class="max-w-4xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch mb-24">
            
            <!-- Plan 1: Free Listing -->
            <div class="bg-white border border-slate-200 rounded-3xl p-8 sm:p-10 flex flex-col justify-between hover:border-slate-350 hover:shadow-md transition duration-300 shadow-sm relative">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-xl font-black text-slate-900">Free Listing</h3>
                        <p class="text-xs text-slate-500 mt-2">Get started by listing your sports venue online.</p>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-1 text-slate-900">
                        <span class="text-5xl font-black transition-all">₹0</span>
                    </div>

                    <hr class="border-slate-200/60">

                    <!-- Features -->
                    <ul class="space-y-3.5 text-xs text-slate-600 font-semibold">
                        <li class="flex items-center gap-2.5">
                            <span class="text-rose-500 font-bold">✔</span> Turf listing
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-rose-500 font-bold">✔</span> Basic visibility
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-rose-500 font-bold">✔</span> Basic profile
                        </li>
                        <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                            Full turf management
                        </li>
                        <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                            Booking & Slot management
                        </li>
                        <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                            Reports & dashboard
                        </li>
                    </ul>
                </div>

                <div class="pt-8">
                    <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3 bg-slate-100 text-slate-800 hover:bg-slate-200 font-bold rounded-xl text-xs uppercase tracking-wider transition">
                        List For Free
                    </a>
                </div>
            </div>

            <!-- Plan 2: Pro -->
            <div class="bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-emerald-500 rounded-3xl p-8 sm:p-10 flex flex-col justify-between transition duration-300 shadow-xl relative text-white">
                <!-- Popular Badge -->
                <span class="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow-md">PRO ADVANTAGE</span>

                <div class="space-y-6">
                    <div>
                        <h3 class="text-xl font-black text-white">Pro</h3>
                        <p class="text-xs text-slate-400 mt-2">Designed to supercharge your sports arena business.</p>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-1 text-white">
                        <span class="text-3xl font-black">₹</span>
                        <span x-text="annual ? '30,000' : '3,000'" class="text-5xl font-black transition-all">3,000</span>
                        <span x-text="annual ? '/ Turf / Year' : '/ Turf / Month'" class="text-xs text-slate-400">/ Turf / Month</span>
                    </div>

                    <hr class="border-slate-850">

                    <!-- Features -->
                    <ul class="space-y-3.5 text-xs text-slate-300 font-semibold">
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Full turf management
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Unlimited bookings
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Booking management
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Slot management
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Pricing management
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Reports
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Business dashboard
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✔</span> Lower platform commission
                        </li>
                    </ul>
                </div>

                <div class="pt-8">
                    <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-emerald-500/20">
                        Upgrade to Pro
                    </a>
                </div>
            </div>

        </div>


        <!-- FAQs -->
        <div class="max-w-4xl mx-auto px-6 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">BILLING & COMMISSION</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Pricing FAQs</h2>
            </div>

            <!-- FAQ Collapse lists -->
            <div class="space-y-4">
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I upgrade my Free Listing to Pro at any time?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        Yes. You can upgrade any of your listed turfs to Pro at any time through your dashboard's business profile page. Once upgraded, slot scheduling and payment features become active instantly.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How is the Per-Turf fee calculated?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        Subscriptions are billed per active turf/court. For example, if you manage 3 turfs under the Pro tier, your monthly subscription will be 3 x ₹3,000 = ₹9,000. Free listings incur no monthly charges.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How do platform commissions work?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed font-medium">
                        Free listings do not support direct online bookings through TurfBooking. For Pro subscriptions, a low percentage commission is charged on each online transaction processed, allowing you to keep the vast majority of your revenue.
                    </p>
                </details>
            </div>
        </div>
    </section>
</x-marketing-layout>
