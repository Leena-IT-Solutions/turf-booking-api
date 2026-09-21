<x-marketing-layout>
    <x-slot name="title">
        Return Policy - LEENA IT SOLUTIONS | {{ config('app.name', 'TurfBooking') }}
    </x-slot>

    <section class="py-12 sm:py-20 relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 sm:px-6">
            
            <!-- Policy Navigation Tabs -->
            <x-policy-tabs active="return" />

            <!-- Main Document Card -->
            <article class="bg-white border border-slate-200/80 rounded-3xl p-6 sm:p-12 shadow-sm relative overflow-hidden">
                
                <!-- Document Header -->
                <header class="border-b border-slate-100 pb-8 mb-8">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-100 text-emerald-700 text-[11px] font-extrabold uppercase tracking-wider mb-4">
                        🔄 Returns & Exchanges
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-black text-slate-900 tracking-tight">Return Policy</h1>
                    <p class="text-xs sm:text-sm text-slate-500 mt-2">
                        Platform: <a href="https://turf.infoleena.com" class="text-emerald-600 hover:underline font-medium">https://turf.infoleena.com</a> &bull; Operated by <strong>LEENA IT SOLUTIONS</strong>
                    </p>
                </header>

                <div class="space-y-8 text-xs sm:text-sm leading-relaxed text-slate-600">
                    
                    <!-- 7-Day Policy Window -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-emerald-50/50 border border-emerald-100 text-slate-700 space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                7-Day Window
                            </span>
                            <h2 class="text-sm sm:text-base font-bold text-emerald-950">Eligibility & Return Timeframe</h2>
                        </div>
                        <p>
                            We offer refund / exchange within first <strong>7 days</strong> from the date of your purchase. If <strong>7 days</strong> have passed since your purchase, you will not be offered a return, exchange or refund of any kind.
                        </p>
                    </div>

                    <!-- Return Conditions -->
                    <div class="space-y-3">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Return & Exchange Eligibility Criteria</h2>
                        <p>In order to become eligible for a return or an exchange:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5">
                                <span class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    (i) Unused Condition
                                </span>
                                <p class="text-[11px] text-slate-500">The purchased item should be unused and in the same condition as you received it.</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5">
                                <span class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    (ii) Original Packaging
                                </span>
                                <p class="text-[11px] text-slate-500">The item must be in its original packaging with tags and receipts intact.</p>
                            </div>

                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 space-y-1.5">
                                <span class="font-bold text-slate-900 text-xs flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                    (iii) Sale Items Exclusions
                                </span>
                                <p class="text-[11px] text-slate-500">If the item that you purchased was on a promotional sale, it may not be eligible for return / exchange.</p>
                            </div>
                        </div>
                        <p class="pt-2 text-slate-600">
                            Further, only such items are replaced by us (based on an exchange request), if such items are found defective or damaged.
                        </p>
                    </div>

                    <!-- Exempted Items & Inspection Process -->
                    <div class="space-y-3 pt-2">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Exempted Categories & Quality Check Process</h2>
                        <p>
                            You agree that there may be a certain category of products / items that are exempted from returns or refunds. Such categories of the products would be identified to you at the item of purchase.
                        </p>
                        <p>
                            For exchange / return accepted request(s) (as applicable), once your returned product / item is received and inspected by us, we will send you an email to notify you about receipt of the returned / exchanged product. Further, if the same has been approved after the quality check at our end, your request (i.e. return / exchange) will be processed in accordance with our policies.
                        </p>
                    </div>

                    <!-- Contact Card -->
                    <div class="p-5 sm:p-6 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                        <h2 class="text-sm sm:text-base font-bold text-slate-900">Need Help with a Return?</h2>
                        <p class="text-xs text-slate-600">Please reach out to our team with your order confirmation and photos of the item:</p>
                        <div class="text-xs space-y-1 pt-1 text-slate-700">
                            <p><strong>Email:</strong> <a href="mailto:leenaadam28@gmail.com" class="text-emerald-600 hover:underline">leenaadam28@gmail.com</a> / <a href="mailto:sandeep198558@gmail.com" class="text-emerald-600 hover:underline">sandeep198558@gmail.com</a></p>
                            <p><strong>Phone:</strong> +91 9769409405 / +91 9664588677</p>
                            <p><strong>Entity:</strong> LEENA IT SOLUTIONS, Plot No 65, Shree Satyam CHS B101, Sai Section, Ambernath East 421501 MS India</p>
                        </div>
                    </div>

                </div>
            </article>

        </div>
    </section>
</x-marketing-layout>
