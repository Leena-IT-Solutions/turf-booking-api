<x-marketing-layout>
    <x-slot name="title">
        {{ config('app.name', 'TurfBooking') }} - Sports Venue Management & Slot Booking Platform
    </x-slot>

    <!-- Hero Section -->
    <section class="relative pt-12 pb-24 lg:pt-20 lg:pb-32 overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            
            <!-- Hero Details -->
            <div class="lg:col-span-6 space-y-8 text-left relative z-10">
                <!-- Badge Promo -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 dark:bg-emerald-400/10 border border-emerald-500/20 text-xs font-bold tracking-wide uppercase text-emerald-600 dark:text-emerald-400">
                    <span class="flex h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    SaaS Platform for Turf Owners
                </div>

                <!-- Main Headline -->
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                    Run Your Sports Venue <br>
                    <span class="bg-gradient-to-r from-emerald-500 via-emerald-600 to-teal-500 dark:from-emerald-400 dark:via-emerald-500 dark:to-teal-300 bg-clip-text text-transparent">On Autopilot</span>
                </h1>

                <!-- Sub-description -->
                <p class="text-base text-slate-600 dark:text-slate-400 leading-relaxed max-w-xl">
                    Say goodbye to chaotic spreadsheets and endless WhatsApp booking chats. Manage slot allocations, dynamically adjust pricing, coordinate staff, and collect payments effortlessly.
                </p>

                <!-- Action CTAs -->
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-4 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white dark:text-slate-950 font-extrabold text-sm tracking-wide transition-all duration-300 shadow-lg shadow-emerald-500/15 hover:shadow-xl hover:shadow-emerald-500/25 hover:-translate-y-0.5">
                        Register Your Turf Venue
                    </a>
                    <a href="{{ url('/features') }}" class="inline-flex items-center justify-center px-6 py-4 rounded-xl bg-white hover:bg-slate-100 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-sm tracking-wide border border-slate-200 dark:border-slate-800 transition duration-150 shadow-sm">
                        Explore Features
                    </a>
                </div>

                <!-- Download Badges for Companion App -->
                <div class="pt-6 border-t border-slate-200/60 dark:border-slate-800/60 space-y-3">
                    <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest block">Are you a player? Download companion app</span>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="#" class="group transition duration-200">
                            <div class="bg-slate-900 text-white hover:bg-slate-800 border border-slate-800 dark:border-slate-700 flex items-center gap-2 px-3.5 py-2 rounded-xl shadow-sm">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-.96.04-2.13.64-2.82 1.45-.6.69-1.12 1.83-.98 2.94.88.08 1.97-.52 2.81-1.33z"></path>
                                </svg>
                                <div class="text-left leading-none">
                                    <span class="block text-[8px] text-slate-400 font-medium">Download on the</span>
                                    <span class="text-[10px] font-bold">App Store</span>
                                </div>
                            </div>
                        </a>
                        <a href="#" class="group transition duration-200">
                            <div class="bg-slate-900 text-white hover:bg-slate-800 border border-slate-800 dark:border-slate-700 flex items-center gap-2 px-3.5 py-2 rounded-xl shadow-sm">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 3.23v17.54c0 .54.37.93.88.97l9.47-9.47L5.88 2.26c-.51.04-.88.43-.88.97zm11.2 8.77l3.66-3.66c.38-.38.38-1 0-1.38L5.88 2.26l10.32 9.74zm4.14 1.38L15.47 9l-9.59 9.59c.51.04.88.43.88.97l13.58-6.19c.38-.17.38-.81 0-.99z"></path>
                                </svg>
                                <div class="text-left leading-none">
                                    <span class="block text-[8px] text-slate-400 font-medium">GET IT ON</span>
                                    <span class="text-[10px] font-bold">Google Play</span>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dashboard Mockup Widget (CSS High-Fidelity Design) -->
            <div class="lg:col-span-6 relative">
                <!-- Glowing effect in background -->
                <div class="absolute inset-0 bg-emerald-500/10 dark:bg-emerald-400/10 rounded-3xl blur-2xl transform rotate-2"></div>
                
                <!-- Mockup Box -->
                <div class="relative bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800/80 rounded-2xl shadow-2xl p-6 overflow-hidden">
                    <!-- Top bar -->
                    <div class="flex items-center justify-between border-b border-slate-200/60 dark:border-slate-800/65 pb-4 mb-6">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-red-400"></span>
                            <span class="w-3 h-3 rounded-full bg-yellow-400"></span>
                            <span class="w-3 h-3 rounded-full bg-green-400"></span>
                            <span class="text-[11px] font-semibold text-slate-450 dark:text-slate-500 ml-2">turf-dashboard.io/scheduler</span>
                        </div>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-500/20">LIVE VENUE MONITOR</span>
                    </div>

                    <!-- mini metrics grids -->
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 rounded-xl">
                            <span class="text-[9px] font-semibold text-slate-400 uppercase">Today's Sales</span>
                            <span class="block text-sm font-black text-slate-800 dark:text-white mt-0.5">$1,840.00</span>
                            <span class="text-[8px] text-emerald-500 font-bold">↑ +14.2%</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 rounded-xl">
                            <span class="text-[9px] font-semibold text-slate-400 uppercase">Slot Occupancy</span>
                            <span class="block text-sm font-black text-slate-800 dark:text-white mt-0.5">88.5%</span>
                            <span class="text-[8px] text-emerald-500 font-bold">↑ +5.3%</span>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 rounded-xl">
                            <span class="text-[9px] font-semibold text-slate-400 uppercase">Bookings</span>
                            <span class="block text-sm font-black text-slate-800 dark:text-white mt-0.5">26 Slots</span>
                            <span class="text-[8px] text-slate-400 font-medium">14 online, 12 cash</span>
                        </div>
                    </div>

                    <!-- Mini Calendar Scheduler Grid -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-slate-800 dark:text-white">Active Slots Scheduler - Pitch A</span>
                            <span class="text-[10px] font-bold text-slate-400">August 04, 2026</span>
                        </div>
                        
                        <div class="space-y-2 text-xs">
                            <!-- Slot 1: Booked -->
                            <div class="flex items-center gap-3 p-2 bg-emerald-500/10 dark:bg-emerald-500/10 border border-emerald-500/20 rounded-xl">
                                <span class="font-bold text-emerald-600 dark:text-emerald-400 min-w-[70px] text-[10px]">04:00 - 05:00 PM</span>
                                <div class="flex-grow flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">Barcelona Football Club Academy</span>
                                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-emerald-500 text-white font-bold uppercase tracking-wider">Booked (Paid)</span>
                                </div>
                            </div>
                            <!-- Slot 2: Locked -->
                            <div class="flex items-center gap-3 p-2 bg-indigo-500/5 dark:bg-indigo-500/10 border border-indigo-500/15 rounded-xl">
                                <span class="font-bold text-indigo-500 dark:text-indigo-400 min-w-[70px] text-[10px]">05:00 - 06:00 PM</span>
                                <div class="flex-grow flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">Locked Slot: Elite League Practice</span>
                                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-indigo-500 text-white font-bold uppercase tracking-wider">Reserved Slot</span>
                                </div>
                            </div>
                            <!-- Slot 3: Available -->
                            <div class="flex items-center gap-3 p-2 bg-slate-50 dark:bg-slate-950 border border-slate-200/50 dark:border-slate-800 rounded-xl">
                                <span class="font-bold text-slate-400 min-w-[70px] text-[10px]">06:00 - 07:00 PM</span>
                                <div class="flex-grow flex items-center justify-between">
                                    <span class="text-slate-450 dark:text-slate-500">Unbooked Slot - Peak Pricing ($60/hr)</span>
                                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-350 font-bold uppercase tracking-wider">Available</span>
                                </div>
                            </div>
                            <!-- Slot 4: Booked -->
                            <div class="flex items-center gap-3 p-2 bg-amber-500/10 dark:bg-amber-500/10 border border-amber-500/20 rounded-xl">
                                <span class="font-bold text-amber-600 dark:text-amber-400 min-w-[70px] text-[10px]">07:00 - 08:00 PM</span>
                                <div class="flex-grow flex items-center justify-between">
                                    <span class="font-bold text-slate-800 dark:text-slate-200">Local Corporates Match</span>
                                    <span class="text-[9px] px-2 py-0.5 rounded-full bg-amber-500 text-white font-bold uppercase tracking-wider">Unpaid (Cash Field)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Platform Stats / Trust Section -->
    <section class="py-16 bg-white dark:bg-slate-900 border-y border-slate-200/80 dark:border-slate-800/80 transition duration-300">
        <div class="max-w-7xl mx-auto px-6">
            <p class="text-center text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-10">TRUSTED BY TOP VENUES NATIONWIDE</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div>
                    <span class="block text-4xl font-black text-slate-900 dark:text-white bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">500,000+</span>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-2 block">Slots Booked Online</span>
                </div>
                <div>
                    <span class="block text-4xl font-black text-slate-900 dark:text-white bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">300+</span>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-2 block">Active Venues & Arenas</span>
                </div>
                <div>
                    <span class="block text-4xl font-black text-slate-900 dark:text-white bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">$12M+</span>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-2 block">Revenue Processed</span>
                </div>
                <div>
                    <span class="block text-4xl font-black text-slate-900 dark:text-white bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">99.99%</span>
                    <span class="text-xs font-bold text-slate-500 dark:text-slate-400 mt-2 block">System Uptime Guarantee</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Turf Owner Features Grid -->
    <section class="py-24 lg:py-32">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <!-- Header description -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <span class="text-xs font-bold text-emerald-500 dark:text-emerald-400 uppercase tracking-widest">Built For Sports Businesses</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white">
                    Powering Sports Venues of All Sizes
                </h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Everything you need to manage slots, increase peak capacity, handle custom memberships, and streamline cash transactions.
                </p>
            </div>

            <!-- Features Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- Feature 1: Real-Time Slots Calendar -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Visual Scheduling Grid</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Manage multiple fields or courts in a unified live interactive view. Block slots for cleaning, academy classes, or elite match reservations with a simple tap.
                        </p>
                    </div>
                </div>

                <!-- Feature 2: Smart Pricing Matrix -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Dynamic Pricing Engine</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Configure automatic price increments for peak weekend slots, promotional discounts for off-peak morning hours, and premium night slots with active floodlight billing.
                        </p>
                    </div>
                </div>

                <!-- Feature 3: Staff Scoping & Roles -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Staff Management Control</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Add venue managers, receptionists, and pitch guards. Limit access permissions to specific fields, hide financial reports, and maintain action audit trails.
                        </p>
                    </div>
                </div>

                <!-- Feature 4: Custom Coupons & Promo Codes -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Discount & Coupon Matrix</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Generate unique coupon codes to reward recurring players. Set maximum discount ceilings, expire codes automatically, and track code utilization analytics.
                        </p>
                    </div>
                </div>

                <!-- Feature 5: Facility Rentals & Upsell -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Equipment & Add-on Rentals</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Upsell equipment at checkout. Rent out soccer balls, goalie gloves, training bibs, or list add-ons like clean drinking water, locker rooms, or referee services.
                        </p>
                    </div>
                </div>

                <!-- Feature 6: Deep Revenue Analytics -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 space-y-6">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">CSV & PDF Accounting Exports</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Extract detailed occupancy grids and sales ledgers. Reconcile on-field cash collections and direct credit card processing with robust accountant-ready exports.
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Player Feature Teaser (Companion App Mockup/Ad) -->
    <section class="py-24 bg-gradient-to-b from-slate-100 to-white dark:from-slate-900 dark:to-slate-950 transition duration-300">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            
            <!-- Graphic/App Mockup UI -->
            <div class="lg:col-span-5 order-2 lg:order-1 relative">
                <div class="absolute inset-0 bg-emerald-500/10 dark:bg-emerald-455/5 rounded-3xl blur-2xl"></div>
                <div class="relative max-w-[280px] mx-auto bg-black border-[8px] border-slate-900 dark:border-slate-800 rounded-[40px] shadow-2xl aspect-[9/19.5] overflow-hidden">
                    <!-- Notch -->
                    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-32 h-6 bg-slate-900 dark:bg-slate-800 rounded-b-2xl z-20 flex items-center justify-center">
                        <span class="w-2.5 h-2.5 rounded-full bg-slate-800 dark:bg-slate-900"></span>
                    </div>
                    
                    <!-- App Interface screen -->
                    <div class="h-full bg-slate-950 text-white p-4 flex flex-col justify-between pt-8 text-[11px] relative">
                        <!-- Top header -->
                        <div class="flex items-center justify-between mt-2">
                            <div>
                                <span class="text-slate-450 block text-[9px]">YOUR LOCAL COURT</span>
                                <span class="font-bold text-xs text-white">Greenfield Arena ⚽</span>
                            </div>
                            <span class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center">🔍</span>
                        </div>

                        <!-- Slots Grid inside App -->
                        <div class="my-6 space-y-3">
                            <span class="font-bold text-[10px] text-slate-400 block">Select Slot Time:</span>
                            <div class="grid grid-cols-2 gap-2">
                                <button class="p-2 rounded bg-slate-900 text-slate-400 border border-slate-800 text-center font-bold">06:00 PM</button>
                                <button class="p-2 rounded bg-emerald-500 text-slate-950 text-center font-black border border-emerald-450">07:00 PM</button>
                                <button class="p-2 rounded bg-slate-900 text-slate-400 border border-slate-800 text-center font-bold">08:00 PM</button>
                                <button class="p-2 rounded bg-slate-900 text-slate-400 border border-slate-800 text-center font-bold">09:00 PM</button>
                            </div>
                            
                            <!-- Booking summary card -->
                            <div class="p-3 bg-slate-900 border border-slate-800 rounded-xl space-y-2 mt-4">
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Hourly Slot Fee:</span>
                                    <span>$60.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-400">Amenities (Lights):</span>
                                    <span>$10.00</span>
                                </div>
                                <hr class="border-slate-800">
                                <div class="flex justify-between font-bold text-emerald-400">
                                    <span>Total:</span>
                                    <span>$70.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Book button -->
                        <button class="w-full py-3 bg-emerald-500 text-slate-950 rounded-xl font-bold uppercase text-[10px] tracking-wider text-center shadow-lg shadow-emerald-500/10">
                            Book Instantly
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Details -->
            <div class="lg:col-span-7 order-1 lg:order-2 space-y-6 text-left">
                <span class="text-xs font-bold text-emerald-500 dark:text-emerald-400 uppercase tracking-widest block">For Players & Teams</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-slate-900 dark:text-white leading-tight">
                    Instant Mobile Bookings <br>
                    For Soccer, Tennis & Basketball
                </h2>
                <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                    Make it incredibly simple for local players and clubs to discover your venue. Our companion mobile apps allow players to review live pitch availability, select equipment add-ons, pay securely using cards, and get real-time game confirmations.
                </p>
                
                <ul class="space-y-3 text-xs text-slate-600 dark:text-slate-400">
                    <li class="flex items-center gap-2">
                        <span class="text-emerald-500">✓</span> <strong>GPS Discovery:</strong> Let players find your courts sorted by proximity.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-emerald-500">✓</span> <strong>Split Payments:</strong> Let players coordinate and divide the hourly slot fee.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="text-emerald-500">✓</span> <strong>Match Notifications:</strong> Push alerts reminding players of upcoming slots.
                    </li>
                </ul>

                <div class="pt-4 flex items-center gap-3">
                    <a href="#" class="inline-flex items-center gap-2 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:underline">
                        Learn how player app works <span>→</span>
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- Testimonials / Success stories -->
    <section class="py-24 bg-white dark:bg-slate-950 transition duration-300">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-4">
                <span class="text-xs font-bold text-emerald-500 dark:text-emerald-400 uppercase tracking-widest block">TESTIMONIALS</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Loved By Arena Operators</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Testimonial 1 -->
                <div class="p-8 bg-slate-50 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-800 rounded-3xl space-y-6">
                    <p class="text-sm text-slate-600 dark:text-slate-350 italic leading-relaxed">
                        "Before TurfBooking, we had constant double bookings on Friday evenings due to receptionist coordination errors. Moving our schedule online helped us save 8+ hours a week in administration and boosted our slot occupancy by nearly 30%!"
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="h-11 w-11 rounded-full bg-emerald-500 flex items-center justify-center font-bold text-white text-xs">
                            MC
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900 dark:text-white">Marcus Carter</span>
                            <span class="block text-[10px] text-slate-400">Managing Owner, Elite Sports Complex</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="p-8 bg-slate-50 dark:bg-slate-900/50 border border-slate-200/60 dark:border-slate-800 rounded-3xl space-y-6">
                    <p class="text-sm text-slate-600 dark:text-slate-350 italic leading-relaxed">
                        "The dynamic pricing module is a complete game changer. We raised our Saturday night pricing for soccer pitches by 20%, and reduced early Wednesday rates to attract high school players. Revenues grew by 25% in just 60 days."
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="h-11 w-11 rounded-full bg-teal-500 flex items-center justify-center font-bold text-white text-xs">
                            SR
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900 dark:text-white">Sandro Rossi</span>
                            <span class="block text-[10px] text-slate-400">Founder, Arena Futbol Club</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 bg-slate-50 dark:bg-slate-900/40 border-t border-slate-200/60 dark:border-slate-800/80 transition duration-300">
        <div class="max-w-4xl mx-auto px-6 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-bold text-emerald-500 dark:text-emerald-400 uppercase tracking-widest block">HELP & SUPPORT</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">Frequently Asked Questions</h2>
            </div>

            <!-- FAQ List -->
            <div class="space-y-4">
                
                <!-- FAQ 1 -->
                <details class="group bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Can I configure separate managers for my specific turf locations?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Yes. TurfBooking supports multi-location role-based permissions. You can register multiple venues (e.g., Downtown Arena and Westend Pitch) and delegate specific managers to view and edit calendars solely for their allocated locations.
                    </p>
                </details>

                <!-- FAQ 2 -->
                <details class="group bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">How does offline cash booking reconcile with online credit cards?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        When players walk in, your staff can book slots manually from the dashboard calendar and mark them as "On-Field Cash". The system pools this with online stripe transactions to give you a consolidated sales report.
                    </p>
                </details>

                <!-- FAQ 3 -->
                <details class="group bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">Is there an integration option to rent gear (like balls and jerseys)?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Absolutely. You can customize your inventory items, pricing, and quantity inside the Facilities & Equipment manager. Players can add equipment items directly to their cart when reserving their hourly slots.
                    </p>
                </details>

                <!-- FAQ 4 -->
                <details class="group bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-800 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white">How can we export sales files for our accounting audit?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Inside the Reports Manager, turf admins can export filtered spreadsheets (CSV/Excel) and occupancy charts detailing exact slot fees, coupon reductions, tax parameters, and net revenue distributions.
                    </p>
                </details>

            </div>
        </div>
    </section>

    <!-- Call To Action -->
    <section class="py-24 bg-gradient-to-r from-emerald-600 to-teal-650 text-white relative overflow-hidden text-center">
        <!-- background accents -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-white/10 via-transparent to-transparent opacity-50"></div>
        
        <div class="max-w-4xl mx-auto px-6 relative z-10 space-y-8">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                Ready to Maximize Your Venue Occupancy?
            </h2>
            <p class="text-emerald-100 text-sm max-w-xl mx-auto leading-relaxed">
                Join hundreds of sport arena managers using TurfBooking to fill calendars, coordinate staff schedules, and drive recurring sales.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="px-8 py-4 bg-white hover:bg-slate-50 text-emerald-950 font-black rounded-xl text-sm transition-all duration-300 shadow-xl shadow-emerald-950/20 hover:shadow-emerald-950/30 hover:-translate-y-0.5">
                    Start Your 14-Day Free Trial
                </a>
                <a href="{{ url('/contact') }}" class="px-8 py-4 bg-emerald-700/50 hover:bg-emerald-750 border border-emerald-450/40 font-bold rounded-xl text-sm transition-all duration-300">
                    Contact Sales Representative
                </a>
            </div>
            <span class="block text-[11px] text-emerald-200">No credit card required. Cancel anytime.</span>
        </div>
    </section>
</x-marketing-layout>
