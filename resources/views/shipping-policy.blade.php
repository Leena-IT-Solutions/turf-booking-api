<x-marketing-layout>
    <x-slot name="title">
        Shipping Policy - LEENA IT SOLUTIONS | {{ config('app.name', 'TurfBooking') }}
    </x-slot>

    <section class="py-12 sm:py-20 relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            
            <!-- Policy Navigation Tabs -->
            <x-policy-tabs active="shipping" />

            <!-- Main Document Card -->
            <article class="bg-white border border-slate-200/80 rounded-3xl p-6 sm:p-12 shadow-sm relative overflow-hidden">
                
                <!-- Document Header -->
                <header class="border-b border-slate-100 pb-8 mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-[11px] font-extrabold uppercase tracking-wider mb-4">
                        📦 Delivery & Fulfillment
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">Shipping Policy</h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-2">
                        Platform: <a href="https://turf.infoleena.com" class="text-emerald-600 hover:underline font-medium">https://turf.infoleena.com</a> &bull; Operated by <strong>LEENA IT SOLUTIONS</strong>
                    </p>
                </header>

                <div class="space-y-8 text-xs sm:text-sm leading-relaxed text-slate-600">
                    
                    <!-- Dispatch & Carriers -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-emerald-50/50 border border-emerald-100 text-slate-700 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                Dispatch Notice
                            </span>
                            <h2 class="text-sm sm:text-base font-bold text-emerald-950">Domestic Shipping & Courier Partners</h2>
                        </div>
                        <p>
                            The orders for the user are shipped through registered domestic courier companies and/or speed post only. Orders are shipped within <strong>7 days</strong> from the date of the order and/or payment or as per the delivery date agreed at the time of order confirmation and delivering of the shipment, subject to courier company / post office norms.
                        </p>
                    </div>

                    <!-- Liability Limitation -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Courier & Transit Delays</h2>
                        <p>
                            Platform Owner shall not be liable for any delay in delivery by the courier company / postal authority.
                        </p>
                    </div>

                    <!-- Delivery Address & Digital Service Confirmation -->
                    <div class="space-y-3">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Delivery Address & Service Confirmations</h2>
                        <p>
                            Delivery of all physical orders will be made to the address provided by the buyer at the time of purchase.
                        </p>
                        <p>
                            Delivery of our digital services (venue bookings, slot passes, access confirmations) will be confirmed on your email ID as specified at the time of registration.
                        </p>
                    </div>

                    <!-- Shipping Costs -->
                    <div class="p-4 sm:p-5 rounded-2xl bg-amber-50/70 border border-amber-200 text-amber-950 space-y-1.5">
                        <h2 class="text-sm sm:text-base font-bold text-amber-900">Shipping Costs Non-Refundable</h2>
                        <p class="text-xs">
                            If there are any shipping cost(s) levied by the seller or the Platform Owner (as the case be), the same is <strong>not refundable</strong>.
                        </p>
                    </div>

                    <!-- Contact & Inquiries -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Shipping Inquiries & Consignment Tracking</h2>
                        <p class="text-xs text-slate-600">If you have questions regarding your delivery status, contact us:</p>
                        <div class="text-xs space-y-1 pt-1 text-slate-700">
                            <p><strong>Email:</strong> <a href="mailto:leenaadam28@gmail.com" class="text-emerald-600 hover:underline">leenaadam28@gmail.com</a> / <a href="mailto:sandeep198558@gmail.com" class="text-emerald-600 hover:underline">sandeep198558@gmail.com</a></p>
                            <p><strong>Phone:</strong> +91 9769409405 / +91 9664588677</p>
                            <p><strong>Support Window:</strong> Monday &ndash; Friday (09:00 &ndash; 18:00 IST)</p>
                            <p><strong>Entity:</strong> LEENA IT SOLUTIONS, Plot No 65, Shree Satyam CHS B101, Sai Section, Ambernath East 421501 MS India</p>
                        </div>
                    </div>

                </div>
            </article>

        </div>
    </section>
</x-marketing-layout>
