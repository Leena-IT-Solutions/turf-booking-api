<x-marketing-layout>
    <x-slot name="title">
        FAQs - Frequently Asked Questions
    </x-slot>
    <x-slot name="description">
        Find answers to frequently asked questions about TurfBooking. Learn about slot locks, online payments, pricing models, and mobile app support.
    </x-slot>

    <!-- Header Section -->
    <section class="pt-20 pb-12 text-center relative overflow-hidden">
        <!-- Ambient Glow -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-0 right-1/4 w-[600px] h-[600px] rounded-full bg-emerald-500/5 blur-3xl"></div>
            <div class="absolute bottom-10 left-10 w-[400px] h-[400px] rounded-full bg-teal-500/5 blur-3xl"></div>
        </div>

        <div class="max-w-4xl mx-auto px-6 space-y-4 relative z-10">
            <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest bg-emerald-50 border border-emerald-100/50 px-3 py-1 rounded-full inline-block">Support Desk</span>
            <h1 class="text-4xl sm:text-5xl font-black tracking-tight text-slate-900 leading-tight">
                Frequently Asked Questions
            </h1>
            <p class="text-base text-slate-550 max-w-xl mx-auto leading-relaxed font-medium">
                Find quick answers to common questions about booking, payments, venue management, and subscriptions.
            </p>
        </div>
    </section>

    <!-- FAQs Section -->
    <section class="pb-24 relative z-10">
        <div class="max-w-4xl mx-auto px-6 space-y-16">
            
            <!-- Category 1: Player FAQs -->
            <div id="player-faqs" class="space-y-6 scroll-mt-24">
                <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                    <span class="text-lg">🏃‍♂️</span>
                    <h2 class="text-xl font-black text-slate-900">Player FAQs</h2>
                </div>

                <div class="space-y-4">
                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I book a turf from the website?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            No. Turf search and booking are available through the TurfBooking mobile app.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Where can I download the app?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Download TurfBooking from Google Play or the App Store.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I see turf prices before booking?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I check available slots?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes, through the mobile app.
                        </p>
                    </details>
                </div>
            </div>

            <!-- Category 2: Turf Owner FAQs -->
            <div id="owner-faqs" class="space-y-6 scroll-mt-24">
                <div class="flex items-center gap-3 border-b border-slate-200 pb-3">
                    <span class="text-lg">🏟️</span>
                    <h2 class="text-xl font-black text-slate-900">Turf Owner FAQs</h2>
                </div>

                <div class="space-y-4">
                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I list my turf for free?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes, if your current business model supports free listing.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I manage multiple turfs?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I manage multiple locations?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I manage bookings from the dashboard?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I set my own pricing?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I block slots?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I add managers?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-6 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">How do I receive payments?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Payments made online by players are processed securely and credited directly to your registered bank account on a daily or weekly basis, after deduction of platform commissions. You can track gross revenue and payouts in real-time.
                        </p>
                    </details>
                </div>
            </div>

        </div>
    </section>

    <!-- Support CTA Section -->
    <section class="py-16 text-center bg-slate-50 border-t border-slate-200">
        <div class="max-w-4xl mx-auto px-6 space-y-6">
            <h2 class="text-2xl sm:text-3xl font-black text-slate-900">Still Have Questions?</h2>
            <p class="text-xs sm:text-sm text-slate-550 max-w-xl mx-auto leading-relaxed font-medium">
                Our support team is online 24/7 to help you set up your arena operations or resolve booking payment queries.
            </p>
            <div class="flex items-center justify-center gap-4 pt-2">
                <a href="{{ url('/contact') }}" class="px-6 py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider shadow-md">
                    Contact Support
                </a>
                <a href="{{ route('register') }}" class="px-6 py-3.5 bg-white text-slate-800 border border-slate-200 hover:bg-slate-50 rounded-xl text-xs uppercase tracking-wider font-semibold">
                    Register Your Turf
                </a>
            </div>
        </div>
    </section>
</x-marketing-layout>
