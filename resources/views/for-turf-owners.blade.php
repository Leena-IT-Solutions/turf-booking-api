<x-marketing-layout>
    <x-slot name="title">
        For Turf Owners - Automate and Scale Your Sports Arena
    </x-slot>
    <x-slot name="description">
        Empower your sports venue business with TurfBooking's dedicated turf owner features: slot managers, staff profiles, pricing configuration, and analytics.
    </x-slot>

    <!-- Pricing / Billing state wrapper for pricing section -->
    <div x-data="{ annual: false }">
        <!-- Hero Section -->
        <section class="relative pt-20 pb-24 lg:pt-32 lg:pb-36 overflow-hidden">
            <!-- Ambient Glow -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
                <div class="absolute top-0 right-1/4 w-[600px] h-[600px] rounded-full bg-emerald-500/5 blur-3xl"></div>
                <div class="absolute bottom-10 left-10 w-[400px] h-[400px] rounded-full bg-teal-500/5 blur-3xl"></div>
            </div>

            <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center relative z-10">
                <div class="lg:col-span-7 space-y-8 text-left">
                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold tracking-wide uppercase text-emerald-600">
                        <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Arena Growth Partner
                    </div>
                    <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 leading-tight">
                        Scale Your Sports Complex <br>
                        <span class="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Without the Stress</span>
                    </h1>
                    <p class="text-base text-slate-650 leading-relaxed max-w-2xl font-medium">
                        Stop chasing players on WhatsApp and managing bookings on paper. TurfBooking gives you the automated software, slots scheduler, and payment integrations needed to boost occupancy and payouts.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 pt-2">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white font-extrabold text-sm tracking-wide transition-all duration-300 shadow-lg shadow-emerald-500/15 hover:shadow-xl hover:shadow-emerald-500/25 hover:-translate-y-0.5">
                            Register Your Turf
                        </a>
                        <a href="#how-it-works" class="inline-flex items-center justify-center px-6 py-4 rounded-xl bg-white hover:bg-slate-100 text-slate-700 font-bold text-sm tracking-wide border border-slate-200 transition duration-150 shadow-sm">
                            Explore Features
                        </a>
                    </div>
                </div>

                <!-- Visual Widget Mockup -->
                <div class="lg:col-span-5 relative">
                    <div class="absolute inset-0 bg-emerald-500/10 rounded-3xl blur-2xl transform rotate-2"></div>
                    <div class="relative bg-white border border-slate-200 rounded-2xl shadow-2xl p-6 space-y-6 text-left">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full bg-red-400"></span>
                                <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                                <span class="w-3 h-3 rounded-full bg-green-400"></span>
                            </div>
                            <span class="text-[10px] font-black text-slate-400 uppercase bg-slate-50 px-2.5 py-1 rounded">TURF MANAGER PRO</span>
                        </div>

                        <!-- Small Stat Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="p-4 bg-slate-50 border border-slate-200/50 rounded-2xl">
                                <span class="text-[9px] font-bold text-slate-400 uppercase block">Occupancy Rate</span>
                                <span class="text-2xl font-black text-slate-900 mt-1 block">94.2%</span>
                                <span class="text-[9px] font-bold text-emerald-500">↑ 18.4% this month</span>
                            </div>
                            <div class="p-4 bg-slate-50 border border-slate-200/50 rounded-2xl">
                                <span class="text-[9px] font-bold text-slate-400 uppercase block">Weekly Payouts</span>
                                <span class="text-2xl font-black text-slate-900 mt-1 block">₹48,250</span>
                                <span class="text-[9px] font-bold text-emerald-500">Auto-cleared</span>
                            </div>
                        </div>

                        <!-- Mini slots status -->
                        <div class="space-y-3">
                            <span class="text-[10px] font-black text-slate-400 uppercase block">Next Upcoming Matches</span>
                            <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="block text-xs font-bold text-slate-900">Elite 5v5 Turf - Pitch 1</span>
                                    <span class="text-[10px] text-slate-500 font-medium">06:00 PM - 07:00 PM</span>
                                </div>
                                <span class="text-[9px] font-black bg-emerald-500 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Paid</span>
                            </div>
                            <div class="p-3 bg-indigo-500/5 border border-indigo-500/10 rounded-xl flex items-center justify-between">
                                <div>
                                    <span class="block text-xs font-bold text-slate-900">Cricket Nets Practice</span>
                                    <span class="text-[10px] text-slate-500 font-medium">07:00 PM - 08:30 PM</span>
                                </div>
                                <span class="text-[9px] font-black bg-indigo-500 text-white px-2 py-0.5 rounded-full uppercase tracking-wider">Locked</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 1. Why List Your Turf? -->
        <section class="py-24 bg-white border-y border-slate-200/80">
            <div class="max-w-7xl mx-auto px-6 space-y-16">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-4xl sm:text-5xl font-black tracking-tight text-slate-900">
                        Why List Your Turf?
                    </h2>
                </div>

                <!-- Features Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Point 1 -->
                    <div class="p-8 border border-slate-200 rounded-3xl space-y-6 hover:shadow-lg transition duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <!-- Clock SVG -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="space-y-2 text-left">
                            <h3 class="text-lg font-bold text-slate-900">Eradicate Empty Slots</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                Configure peak pricing rules for popular weekend slots and promotional discounts for off-peak weekdays to keep your complex filled at all hours.
                            </p>
                        </div>
                    </div>

                    <!-- Point 2 -->
                    <div class="p-8 border border-slate-200 rounded-3xl space-y-6 hover:shadow-lg transition duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <!-- Users SVG -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div class="space-y-2 text-left">
                            <h3 class="text-lg font-bold text-slate-900">Reach Local Players</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                Get discovered by thousands of active sports players in your area looking for available pitches and court slots daily.
                            </p>
                        </div>
                    </div>

                    <!-- Point 3 -->
                    <div class="p-8 border border-slate-200 rounded-3xl space-y-6 hover:shadow-lg transition duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                            <!-- Lock SVG -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div class="space-y-2 text-left">
                            <h3 class="text-lg font-bold text-slate-900">Smart Slot Locking</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                Stop double bookings instantly. Our visual scheduler holds active slots during a customer's payment process to ensure operational integrity.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. How TurfBooking Works -->
        <section id="how-it-works" class="py-24 bg-slate-50 border-b border-slate-200/80">
            <div class="max-w-7xl mx-auto px-6 space-y-16">
                <div class="text-center max-w-3xl mx-auto mb-16">
                    <h2 class="text-4xl sm:text-5xl font-black tracking-tight text-slate-900">
                        How TurfBooking Works
                    </h2>
                </div>

                <!-- Steps Container -->
                <div class="max-w-6xl mx-auto space-y-8">
                    <!-- Top Row: Steps 1, 2, 3 -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-stretch">
                        
                        <!-- Step 1 -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-sm hover:shadow-xl hover:border-emerald-500/20 transition-all duration-300 flex flex-col justify-between min-h-[220px] text-left">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-emerald-600 bg-emerald-50 border border-emerald-100/50 px-2.5 py-1 rounded-lg uppercase tracking-wider">Step 1</span>
                                    <span class="text-3xl font-mono text-slate-200 font-black">01</span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900">Register</h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                    Create your secure owner account and enter your sports venue's basic location details.
                                </p>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-sm hover:shadow-xl hover:border-emerald-500/20 transition-all duration-300 flex flex-col justify-between min-h-[220px] text-left">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-emerald-600 bg-emerald-50 border border-emerald-100/50 px-2.5 py-1 rounded-lg uppercase tracking-wider">Step 2</span>
                                    <span class="text-3xl font-mono text-slate-200 font-black">02</span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900">Add Details</h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                    Upload high-resolution photos, select available sports, surface types, amenities, and list rentable equipment.
                                </p>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-sm hover:shadow-xl hover:border-emerald-500/20 transition-all duration-300 flex flex-col justify-between min-h-[220px] text-left">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-emerald-600 bg-emerald-50 border border-emerald-100/50 px-2.5 py-1 rounded-lg uppercase tracking-wider">Step 3</span>
                                    <span class="text-3xl font-mono text-slate-200 font-black">03</span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900">Configure Slots</h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                    Define slot durations, customize time intervals, and configure peak rates for evenings/weekends alongside off-peak discounts.
                                </p>
                            </div>
                        </div>

                    </div>

                    <!-- Bottom Row: Steps 4, 5 -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto items-stretch">
                        
                        <!-- Step 4 -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-sm hover:shadow-xl hover:border-emerald-500/20 transition-all duration-300 flex flex-col justify-between min-h-[220px] text-left">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-emerald-600 bg-emerald-50 border border-emerald-100/50 px-2.5 py-1 rounded-lg uppercase tracking-wider">Step 4</span>
                                    <span class="text-3xl font-mono text-slate-200 font-black">04</span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900">Go Live</h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                    Your sports complex immediately goes live and appears on the TurfBooking app for local players.
                                </p>
                            </div>
                        </div>

                        <!-- Step 5 -->
                        <div class="bg-white border border-slate-200/80 rounded-3xl p-8 shadow-sm hover:shadow-xl hover:border-emerald-500/20 transition-all duration-300 flex flex-col justify-between min-h-[220px] text-left">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-black text-emerald-600 bg-emerald-50 border border-emerald-100/50 px-2.5 py-1 rounded-lg uppercase tracking-wider">Step 5</span>
                                    <span class="text-3xl font-mono text-slate-200 font-black">05</span>
                                </div>
                                <h3 class="text-xl font-black text-slate-900">Manage & Grow</h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                    Monitor schedules, approve online/offline bookings, invite staff, and track direct bank payouts from your business dashboard.
                                </p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Dashboard Features -->
        <section class="py-24 bg-white border-b border-slate-200/80">
            <div class="max-w-7xl mx-auto px-6 space-y-16">
                <div class="text-center max-w-3xl mx-auto space-y-4">
                    <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Dashboard features</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900">
                        A Single Dashboard to Rule It All
                    </h2>
                    <p class="text-sm text-slate-550 leading-relaxed max-w-xl mx-auto font-medium">
                        Our customized management software handles the hard work so you can focus on building the perfect athletic venue.
                    </p>
                </div>

                <!-- Dashboard features grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div class="p-6 border border-slate-100 rounded-2xl hover:border-slate-200 hover:shadow-sm transition-all duration-300">
                        <div class="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="font-extrabold text-sm text-slate-900 mb-2">Calendar Scheduler</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-semibold">Track walk-in & online bookings, and block slots for maintenance in real-time.</p>
                    </div>

                    <div class="p-6 border border-slate-100 rounded-2xl hover:border-slate-200 hover:shadow-sm transition-all duration-300">
                        <div class="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <h3 class="font-extrabold text-sm text-slate-900 mb-2">Flexible Pricing Engine</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-semibold">Set rates by sport, demand, time of day, and holiday schedules automatically.</p>
                    </div>

                    <div class="p-6 border border-slate-100 rounded-2xl hover:border-slate-200 hover:shadow-sm transition-all duration-300">
                        <div class="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <h3 class="font-extrabold text-sm text-slate-900 mb-2">Reports & Payout Analytics</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-semibold">Track gross revenue, payouts, and customer conversion sheets from one panel.</p>
                    </div>

                    <div class="p-6 border border-slate-100 rounded-2xl hover:border-slate-200 hover:shadow-sm transition-all duration-300">
                        <div class="h-10 w-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center mb-4">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <h3 class="font-extrabold text-sm text-slate-900 mb-2">Staff & Role Manager</h3>
                        <p class="text-xs text-slate-500 leading-relaxed font-semibold">Invite court managers and gate staff with customized, controlled access permissions.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. Subscription Plans -->
        <section class="py-24 bg-slate-50 border-b border-slate-200/80">
            <div class="max-w-7xl mx-auto px-6 space-y-16">
                <div class="text-center max-w-3xl mx-auto space-y-4">
                    <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Subscription plans</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900">
                        Simple Per-Turf Subscription
                    </h2>
                    <p class="text-sm text-slate-550 leading-relaxed max-w-xl mx-auto font-medium">
                        Choose the plan that suits your business. List for free or upgrade to Pro to unlock advanced management capabilities.
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
                <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-8 items-stretch">
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
                                    <span class="text-emerald-500 font-bold">✔</span> Turf listing
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✔</span> Basic visibility
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✔</span> Basic profile
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✔</span> Full turf management
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✔</span> Booking & Slot management
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✔</span> Reports & dashboard
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <span class="text-emerald-500 font-bold">✔</span> Standard platform commission
                                </li>
                            </ul>
                        </div>

                        <div class="pt-8">
                            <a href="{{ route('register') }}" class="w-full inline-flex items-center justify-center py-3 bg-slate-100 text-slate-800 hover:bg-slate-200 font-bold rounded-xl text-xs uppercase tracking-wider transition">
                                Register Your Turf
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
                                Register Your Turf
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Benefits -->
        <section class="py-24 bg-white border-b border-slate-200/80">
            <div class="max-w-7xl mx-auto px-6 space-y-16">
                <div class="text-center max-w-3xl mx-auto space-y-4">
                    <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Benefits</span>
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight text-slate-900">
                        Maximize Your Revenue and Efficiency
                    </h2>
                    <p class="text-sm text-slate-550 leading-relaxed max-w-xl mx-auto font-medium">
                        Our platform is engineered to give sports complex owners the exact tools required to drive growth.
                    </p>
                </div>

                <!-- Benefits List (checkmarks block style) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-5xl mx-auto">
                    <div class="flex items-start gap-4 p-6 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="h-6 w-6 text-emerald-500 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                            <span class="text-xs font-black">✓</span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-900 mb-1">24/7 Autopilot Bookings</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">Enable bookings even when your physical reception is closed. Players book and pay directly from their phones.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-6 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="h-6 w-6 text-emerald-500 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                            <span class="text-xs font-black">✓</span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-900 mb-1">Custom Pricing Logic</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">Charge premium rates for peak nighttime slots and weekends, and offer off-peak promotions to drive weekday traffic.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-6 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="h-6 w-6 text-emerald-500 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                            <span class="text-xs font-black">✓</span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-900 mb-1">Lower Platform Commissions</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">Pro members enjoy reduced platform commission structures to ensure you keep the maximum share of slot revenue.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4 p-6 bg-slate-50 border border-slate-200 rounded-2xl">
                        <div class="h-6 w-6 text-emerald-500 bg-emerald-100 rounded-full flex items-center justify-center shrink-0 mt-0.5">
                            <span class="text-xs font-black">✓</span>
                        </div>
                        <div>
                            <h3 class="font-extrabold text-sm text-slate-900 mb-1">Local Brand Amplification</h3>
                            <p class="text-xs text-slate-500 leading-relaxed font-semibold">Get listed on local maps and highlighted to nearby sports players seeking fields for matches.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. FAQs -->
        <section class="py-24 bg-slate-50 border-b border-slate-200/80">
            <div class="max-w-4xl mx-auto px-6 space-y-12">
                <div class="text-center space-y-4">
                    <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">FAQs</span>
                    <h2 class="text-3xl font-black tracking-tight text-slate-900 text-center">Frequently Asked Questions</h2>
                </div>

                <!-- FAQ Collapse list -->
                <div class="space-y-4">
                    <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">How do I receive my payouts?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Payouts are computed automatically and transferred directly to your configured bank account on a daily or weekly basis, without manual claims.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I block time slots for offline/walk-in matches?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            Yes! Your dashboard contains a scheduler where you can manually book or lock slots for telephone/walk-in players or scheduled field maintenance.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">Is there any setup fee to register a turf?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            No. Registration and listing on the Free Listing plan is completely free. We charge no setup fee or hidden hosting costs.
                        </p>
                    </details>

                    <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                        <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                            <h3 class="text-xs sm:text-sm font-bold text-slate-900">What happens when a player requests a cancellation?</h3>
                            <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                        </summary>
                        <p class="mt-4 text-xs text-slate-500 leading-relaxed font-semibold">
                            You define your own venue cancellation policy (e.g. refund if cancelled 24 hours prior, slot credit options, etc.) in your dashboard settings.
                        </p>
                    </details>
                </div>
            </div>
        </section>

        <!-- 7. Owner Registration CTA -->
        <section class="py-24 text-center bg-white">
            <div class="max-w-4xl mx-auto px-6 space-y-6">
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900">Start Automating Your Venue Operations Today</h2>
                <p class="text-xs sm:text-sm text-slate-500 max-w-xl mx-auto leading-relaxed font-semibold font-semibold">
                    Join sports centers using TurfBooking to automate schedules, secure deposit bookings, and track cleared revenue payout sheets in real-time.
                </p>
                <div class="flex items-center justify-center gap-4 pt-2">
                    <a href="{{ route('register') }}" class="px-6 py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider shadow-md">
                        Register Your Turf
                    </a>
                    <a href="{{ url('/pricing') }}" class="px-6 py-3.5 bg-slate-100 text-slate-800 border border-slate-200 hover:bg-slate-200 rounded-xl text-xs uppercase tracking-wider">
                        Compare Pricing Plans
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-marketing-layout>
