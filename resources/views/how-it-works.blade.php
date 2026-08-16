<x-marketing-layout>
    <x-slot name="title">
        How It Works - TurfBooking
    </x-slot>
    <x-slot name="description">
        Learn how TurfBooking makes turf slot scheduling and booking easy for both players and turf business owners. Discover the step-by-step process.
    </x-slot>

    <!-- Hero Header -->
    <section class="pt-24 pb-16 text-center relative overflow-hidden bg-slate-50 border-b border-slate-200/50">
        <!-- Ambient decorative glows -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-indigo-500/5 blur-3xl"></div>
            <div class="absolute bottom-0 -right-40 w-96 h-96 rounded-full bg-emerald-500/5 blur-3xl"></div>
        </div>

        <div class="max-w-4xl mx-auto px-6 space-y-4 relative z-10">
            <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest bg-indigo-50 border border-indigo-100/80 px-3.5 py-1.5 rounded-full inline-block">Platform Guide</span>
            <h1 class="text-4xl sm:text-5xl font-black tracking-tight text-slate-900 leading-tight">
                How TurfBooking Works
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 max-w-xl mx-auto leading-relaxed font-semibold">
                Whether you're looking to run a sports venue business or book a slot for your next game, here is exactly how the platform works.
            </p>
        </div>
    </section>

    <!-- Content Sections -->
    <section class="py-24 bg-white">
        <div class="max-w-6xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-2 gap-16 lg:gap-12 items-start">
            
            <!-- Turf Owners Side (Indigo) -->
            <div class="bg-slate-50/50 border border-slate-200/75 rounded-3xl p-8 sm:p-10 space-y-8 hover:shadow-xl hover:shadow-indigo-500/5 transition duration-300">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🏟️</span>
                        <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-widest bg-indigo-50 border border-indigo-100 px-2.5 py-1 rounded-lg">Turf Owners</span>
                    </div>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">How It Works for Turf Owners</h2>
                    <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                        Running a sports complex is complex enough. We make managing operations, bookings, and pricing extremely simple.
                    </p>
                </div>

                <!-- Timeline Steps Container -->
                <div class="relative pl-10 space-y-8">
                    <!-- Vertical Timeline Line -->
                    <div class="absolute left-[15px] top-3 bottom-3 w-[2px] bg-gradient-to-b from-indigo-500/30 to-indigo-500/5"></div>

                    <!-- Step 1 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-indigo-50 border-2 border-indigo-500 flex items-center justify-center text-[10px] font-black text-indigo-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            01
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-indigo-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Register Your Turf</h3>
                            <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                                Create your turf owner account and add your location.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-indigo-50 border-2 border-indigo-500 flex items-center justify-center text-[10px] font-black text-indigo-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            02
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-3 hover:border-indigo-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Add Your Turf</h3>
                            <div class="space-y-2">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Add Details:</p>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs text-slate-600 font-semibold">
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Turf name
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Sports
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Surface
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Photos
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Facilities
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Equipment
                                    </li>
                                    <li class="flex items-center gap-1.5 col-span-2">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Description
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-indigo-50 border-2 border-indigo-500 flex items-center justify-center text-[10px] font-black text-indigo-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            03
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-3 hover:border-indigo-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Configure Slots & Pricing</h3>
                            <div class="space-y-2">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Configure Settings:</p>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs text-slate-600 font-semibold">
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Time slots
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Slot duration
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Pricing
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Availability
                                    </li>
                                    <li class="flex items-center gap-1.5 col-span-2">
                                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Peak/off-peak rates
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-indigo-50 border-2 border-indigo-500 flex items-center justify-center text-[10px] font-black text-indigo-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            04
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-indigo-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Go Live</h3>
                            <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                                Your turf becomes available to players through the TurfBooking app.
                            </p>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-indigo-50 border-2 border-indigo-500 flex items-center justify-center text-[10px] font-black text-indigo-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            05
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-indigo-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Manage Bookings</h3>
                            <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                                Manage bookings, payments, customers and daily operations from your dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Players Side (Emerald) -->
            <div class="bg-slate-50/50 border border-slate-200/75 rounded-3xl p-8 sm:p-10 space-y-8 hover:shadow-xl hover:shadow-emerald-500/5 transition duration-300">
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">⚽</span>
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-widest bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-lg">Players</span>
                    </div>
                    <h2 class="text-2xl font-black text-slate-900 tracking-tight">How It Works for Players</h2>
                    <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                        Since booking is not available on the website, we make your journey very clear.
                    </p>
                </div>

                <!-- Timeline Steps Container -->
                <div class="relative pl-10 space-y-8">
                    <!-- Vertical Timeline Line -->
                    <div class="absolute left-[15px] top-3 bottom-3 w-[2px] bg-gradient-to-b from-emerald-500/30 to-emerald-500/5"></div>

                    <!-- Step 1 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-emerald-50 border-2 border-emerald-500 flex items-center justify-center text-[10px] font-black text-emerald-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            01
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-emerald-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Download TurfBooking App</h3>
                            <p class="text-xs text-slate-550 font-bold">
                                Android / iOS
                            </p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-emerald-50 border-2 border-emerald-500 flex items-center justify-center text-[10px] font-black text-emerald-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            02
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-emerald-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Search Nearby Turfs</h3>
                            <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                                Select your location and sport.
                            </p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-emerald-50 border-2 border-emerald-500 flex items-center justify-center text-[10px] font-black text-emerald-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            03
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-3 hover:border-emerald-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Explore Turfs</h3>
                            <div class="space-y-2">
                                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">View Info:</p>
                                <ul class="grid grid-cols-2 gap-x-4 gap-y-2.5 text-xs text-slate-600 font-semibold">
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Photos
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Pricing
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Facilities
                                    </li>
                                    <li class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Available slots
                                    </li>
                                    <li class="flex items-center gap-1.5 col-span-2">
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        Location
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-emerald-50 border-2 border-emerald-500 flex items-center justify-center text-[10px] font-black text-emerald-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            04
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-emerald-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Select Your Slot</h3>
                            <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                                Choose your preferred date and time.
                            </p>
                        </div>
                    </div>

                    <!-- Step 5 -->
                    <div class="relative group">
                        <!-- Step Badge -->
                        <div class="absolute left-[-35px] top-1 w-6 h-6 rounded-full bg-emerald-50 border-2 border-emerald-500 flex items-center justify-center text-[10px] font-black text-emerald-600 z-10 group-hover:scale-110 transition duration-300 shadow-sm">
                            05
                        </div>
                        <!-- Card Content -->
                        <div class="bg-white border border-slate-150 rounded-2xl p-5 shadow-sm space-y-2 hover:border-emerald-200 hover:shadow-md transition duration-300">
                            <h3 class="text-sm font-bold text-slate-800">Book & Play</h3>
                            <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                                Complete your booking through the app.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Dedicated CTA Banner -->
    <section class="py-24 bg-gradient-to-tr from-slate-900 via-slate-955 to-indigo-950 border-t border-slate-800 text-white text-center relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-white/5 via-transparent to-transparent opacity-50"></div>
        <div class="max-w-4xl mx-auto px-6 space-y-8 relative z-10">
            <h2 class="text-3xl sm:text-4xl font-black leading-tight bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">
                Your Next Game Is Just a Few Taps Away.
            </h2>
            <div class="pt-2">
                <a href="https://play.google.com/store/apps/details?id=com.infoleena.turf.booking&hl=en_IN" class="inline-flex items-center justify-center px-8 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-slate-955 font-extrabold text-xs uppercase tracking-wider rounded-2xl transition duration-150 shadow-md shadow-emerald-500/20 gap-2">
                    Download TurfBooking App &rarr;
                </a>
            </div>
        </div>
    </section>
</x-marketing-layout>
