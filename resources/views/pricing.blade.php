<x-marketing-layout>
    <x-slot name="title">
        Pricing - Scale Your Sport Arena Business Cost-Effectively
    </x-slot>

    <!-- Pricing Area Wrapper with Alpine State -->
    <section x-data="{ annual: false }" class="py-20 lg:py-32 relative overflow-hidden">
        
        <!-- Headers -->
        <div class="max-w-4xl mx-auto px-6 text-center space-y-6 mb-16">
            <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">MEMBERSHIPS & TIERS</span>
            <h1 class="text-4xl sm:text-5xl font-black text-slate-900 tracking-tight">
                Simple, Transparent <br>
                <span class="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">No-Surprise Pricing</span>
            </h1>
            <p class="text-base text-slate-600 max-w-xl mx-auto leading-relaxed">
                Choose the perfect package for your turf business. All plans start with a 14-day risk-free trial. No credit card required.
            </p>

            <!-- Toggle Switch -->
            <div class="flex items-center justify-center gap-4 pt-6">
                <span :class="!annual ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium'" class="text-xs sm:text-sm transition duration-150">Monthly Billing</span>
                
                <button 
                    @click="annual = !annual" 
                    type="button"
                    class="w-12 h-6 rounded-full bg-slate-200 p-0.5 relative transition duration-300 focus:outline-none"
                >
                    <span 
                        :class="annual ? 'translate-x-6 bg-emerald-500' : 'translate-x-0 bg-slate-400'" 
                        class="block w-5 h-5 rounded-full transition duration-300 transform"
                    ></span>
                </button>

                <div class="flex items-center gap-2">
                    <span :class="annual ? 'text-slate-900 font-bold' : 'text-slate-400 font-medium'" class="text-xs sm:text-sm transition duration-150">Annual Billing</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 bg-emerald-500/10 text-emerald-600 rounded-full border border-emerald-500/20">SAVE 20%</span>
                </div>
            </div>
        </div>

        <!-- Pricing Cards Grid -->
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch mb-24">
            
            <!-- Plan 1: Starter -->
            <div class="bg-white border border-slate-200 rounded-3xl p-8 flex flex-col justify-between hover:border-slate-350 transition duration-300 shadow-sm relative">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Starter Plan</h3>
                        <p class="text-xs text-slate-500 mt-2">Perfect for single pitch owners starting out.</p>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-1 text-slate-900">
                        <span class="text-3xl font-black">₹</span>
                        <span x-text="annual ? '1,599' : '1,999'" class="text-5xl font-black transition-all">1,999</span>
                        <span class="text-xs text-slate-450">/ month</span>
                    </div>

                    <hr class="border-slate-200/60">

                    <!-- Features -->
                    <ul class="space-y-3.5 text-xs text-slate-600">
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> 1 Location / Turf Pitch
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Up to 3 Staff Members
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Dynamic Pricing Rules
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Online Booking & Cash Payments
                        </li>
                        <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                            Custom Domain Integration
                        </li>
                        <li class="flex items-center gap-2.5 text-slate-400 line-through decoration-slate-300">
                            Custom Branding & logo removal
                        </li>
                    </ul>
                </div>

                <div class="pt-8">
                    <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3 bg-slate-100 text-slate-800 hover:bg-slate-200 font-bold rounded-xl text-xs uppercase tracking-wider transition">
                        Start Free Trial
                    </a>
                </div>
            </div>

            <!-- Plan 2: Growth (Popular / Highlighted) -->
            <div class="bg-gradient-to-b from-slate-900 to-slate-950 border-2 border-emerald-500 rounded-3xl p-8 flex flex-col justify-between transition duration-300 shadow-xl relative text-white">
                <!-- Popular Badge -->
                <span class="absolute top-0 right-8 -translate-y-1/2 px-3 py-1 bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow-md">MOST POPULAR</span>

                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-bold text-white">Growth Plan</h3>
                        <p class="text-xs text-slate-400 mt-2">Designed for sports venues with multiple courts.</p>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-1 text-white">
                        <span class="text-3xl font-black">₹</span>
                        <span x-text="annual ? '3,999' : '4,999'" class="text-5xl font-black transition-all">4,999</span>
                        <span class="text-xs text-slate-500">/ month</span>
                    </div>

                    <hr class="border-slate-800">

                    <!-- Features -->
                    <ul class="space-y-3.5 text-xs text-slate-300">
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✓</span> Up to 5 Separate Pitches
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✓</span> Up to 10 Staff Members
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✓</span> Dynamic Pricing Engine
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✓</span> Custom Equipment Inventories
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✓</span> PDF/Excel Reports & Exports
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-400 font-bold">✓</span> Custom Domain Integration
                        </li>
                    </ul>
                </div>

                <div class="pt-8">
                    <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider transition shadow-lg shadow-emerald-500/20">
                        Start Free Trial
                    </a>
                </div>
            </div>

            <!-- Plan 3: Enterprise -->
            <div class="bg-white border border-slate-200 rounded-3xl p-8 flex flex-col justify-between hover:border-slate-350 transition duration-300 shadow-sm relative">
                <div class="space-y-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Enterprise</h3>
                        <p class="text-xs text-slate-500 mt-2">Tailored for large sports centers & franchises.</p>
                    </div>

                    <!-- Price -->
                    <div class="flex items-baseline gap-1 text-slate-900">
                        <span class="text-3xl font-black">₹</span>
                        <span x-text="annual ? '9,999' : '12,999'" class="text-5xl font-black transition-all">12,999</span>
                        <span class="text-xs text-slate-450">/ month</span>
                    </div>

                    <hr class="border-slate-200/60">

                    <!-- Features -->
                    <ul class="space-y-3.5 text-xs text-slate-600">
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Unlimited Courts & Locations
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Unlimited Staff Members
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Full Analytics & Export Logs
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Priority 24/7 Telephone Support
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> Dedicated Account Manager
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✓</span> White-label / Branding Removal
                        </li>
                    </ul>
                </div>

                <div class="pt-8">
                    <a href="{{ url('/contact') }}" class="w-full inline-flex items-center justify-center py-3 bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl text-xs uppercase tracking-wider transition">
                        Contact Sales
                    </a>
                </div>
            </div>

        </div>

        <!-- Detailed Feature Comparison Table -->
        <div class="max-w-7xl mx-auto px-6 mb-24 hidden md:block">
            <h2 class="text-xl font-bold text-slate-900 mb-8">Compare Plan Details</h2>
            <div class="overflow-hidden border border-slate-200 rounded-2xl bg-white">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                            <th class="p-4 border-b border-slate-200">Core Capabilities</th>
                            <th class="p-4 border-b border-slate-200">Starter</th>
                            <th class="p-4 border-b border-slate-200">Growth</th>
                            <th class="p-4 border-b border-slate-200">Enterprise</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-600">
                        <tr>
                            <td class="p-4 font-bold text-slate-800">Active Pitches / Fields</td>
                            <td class="p-4">1 Pitch</td>
                            <td class="p-4">Up to 5 Pitches</td>
                            <td class="p-4">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="p-4 font-bold text-slate-800">Staff Member Seats</td>
                            <td class="p-4">3 Staff</td>
                            <td class="p-4">10 Staff</td>
                            <td class="p-4">Unlimited</td>
                        </tr>
                        <tr>
                            <td class="p-4 font-bold text-slate-800">Dynamic Pricing Settings</td>
                            <td class="p-4">Basic rules</td>
                            <td class="p-4">Full Automation</td>
                            <td class="p-4">Full Automation</td>
                        </tr>
                        <tr>
                            <td class="p-4 font-bold text-slate-800">Data Export Format</td>
                            <td class="p-4">CSV only</td>
                            <td class="p-4">CSV & Excel</td>
                            <td class="p-4">CSV, Excel, PDF</td>
                        </tr>
                        <tr>
                            <td class="p-4 font-bold text-slate-800">Custom domain support</td>
                            <td class="p-4">✕</td>
                            <td class="p-4">✓</td>
                            <td class="p-4">✓</td>
                        </tr>
                        <tr>
                            <td class="p-4 font-bold text-slate-800">Customer Support</td>
                            <td class="p-4">Email / Chat</td>
                            <td class="p-4">Email / Priority Chat</td>
                            <td class="p-4">24/7 Phone + Dedicated Manager</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FAQs -->
        <div class="max-w-4xl mx-auto px-6 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">BILLING & TRANSFERS</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Pricing FAQs</h2>
            </div>

            <!-- FAQ Collapse lists -->
            <div class="space-y-4">
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I change subscription plans at any time?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-550 leading-relaxed">
                        Yes. You can upgrade or downgrade your active subscription plan directly from your billing profile page. If upgrading mid-billing cycle, we will compute a pro-rata charge. Downgrade credits are automatically applied on your following invoice.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">What payment options do you support?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-550 leading-relaxed">
                        We accept all major credit and debit cards (Visa, Mastercard, American Express, Discover) via our integrated Stripe checkout portal. We also support local bank transfers for annual Enterprise billing cycles.
                    </p>
                </details>

                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">Do you charge commissions on bookings processed?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed">
                        No. We believe in keeping flat subscription fees. We charge 0% commission on slot transactions you process. You only pay standard transaction charges enforced by your chosen gateway provider (e.g. Stripe card fees).
                    </p>
                </details>
            </div>
        </div>

    </section>
</x-marketing-layout>
