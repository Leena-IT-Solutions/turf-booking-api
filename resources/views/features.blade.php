<x-marketing-layout>
    <x-slot name="title">
        Platform Modules & Features - Complete Turf Booking Capabilities
    </x-slot>

    <!-- Hero Header -->
    <section class="pt-20 pb-12 text-center relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-6 space-y-6">
            <span class="text-xs font-bold text-emerald-500 dark:text-emerald-400 uppercase tracking-widest block">FEATURE CATALOGUE</span>
            <h1 class="text-4xl sm:text-5xl font-black tracking-tight text-slate-900 dark:text-white leading-tight">
                15 Modular Systems <br>
                <span class="bg-gradient-to-r from-emerald-500 to-teal-500 bg-clip-text text-transparent">Powering Your Arena</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-550 dark:text-slate-400 max-w-xl mx-auto leading-relaxed">
                Discover the deep capabilities of TurfBooking, structured to streamline daily venue operations, maximize occupancy rates, and secure bookings.
            </p>
        </div>
    </section>

    <!-- Interactive Tabs Section (Alpine.js State) -->
    <section x-data="{ activeTab: 'all' }" class="pb-24">
        <!-- Tabs Filters -->
        <div class="max-w-7xl mx-auto px-6 mb-12 flex justify-center">
            <div class="inline-flex flex-wrap items-center gap-1.5 p-1.5 bg-slate-100 dark:bg-slate-900 rounded-2xl border border-slate-200/50 dark:border-slate-800/80 shadow-inner">
                <button 
                    @click="activeTab = 'all'" 
                    :class="activeTab === 'all' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="px-6 py-2 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 focus:outline-none"
                >
                    All Modules
                </button>
                <button 
                    @click="activeTab = 'operations'" 
                    :class="activeTab === 'operations' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="px-6 py-2 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 focus:outline-none"
                >
                    Operations
                </button>
                <button 
                    @click="activeTab = 'finance'" 
                    :class="activeTab === 'finance' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="px-6 py-2 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 focus:outline-none"
                >
                    Finance & Growth
                </button>
                <button 
                    @click="activeTab = 'management'" 
                    :class="activeTab === 'management' ? 'bg-white dark:bg-slate-800 text-slate-900 dark:text-white shadow-sm font-bold' : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="px-6 py-2 rounded-xl text-xs uppercase tracking-wider transition-all duration-200 focus:outline-none"
                >
                    Management & Apps
                </button>
            </div>
        </div>

        <!-- Modules Grid -->
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- Module 1: Turf Profile & Listing (Operations) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'operations'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <!-- SVG Profile -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 01</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Turf Profile & Listing</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Create stunning public showcases for your sports facility. Upload high-resolution photos, configure descriptions, catalog supported sports, and itemize available amenities, locker rooms, or rental equipment.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 2: Smart Slot Management (Operations) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'operations'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <!-- SVG Slots -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 02</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Smart Slot Management</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Fine-tune hourly grids for individual courts or turf sections. Set unique slot durations (e.g. 60 or 90 minutes), configure specific pricing rules per slot, and define day-wise operations calendars.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 3: Booking Management (Operations) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'operations'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <!-- SVG Calendar -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 03</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Booking Management</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Maintain control over incoming reservations. Screen, approve, or cancel client bookings, support walk-in registers, and process complex multi-date block reservations for academies and tournaments.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 4: Payment Integration (Finance) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'finance'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600 dark:text-teal-400">
                            <!-- SVG Card -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 04</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Payment Integration</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Collect online slot fees securely via built-in Razorpay gateways. Configure partial booking deposits (split payments) to secure commitments, or track offline pay-at-location collections.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 5: Cancellation & Refunds (Finance) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'finance'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600 dark:text-teal-400">
                            <!-- SVG Refund -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 7.89M9 11l3-3 3 3m-3-3v12"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 05</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Cancellation & Refunds</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Define your terms clearly. Create custom time-scoped cancellation rules (e.g. up to 12 hours before slot kickoff) and automatically calculate refund quotas based on rules.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 6: Staff & Manager Portal (Management) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'management'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <!-- SVG Staff -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 06</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Staff & Manager Portal</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Delegate operational controls securely. Add site receptionists and managers, assign them to specific locations, and restrict access parameters using role-based configurations (Manager, Admin).
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 7: Coupons & Discounts (Finance) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'finance'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600 dark:text-teal-400">
                            <!-- SVG Coupon -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 07</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Coupons & Discounts</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Boost slot occupancy rates during slower hours. Create custom promo codes with either flat or percentage discounts, restrict validity periods, and configure maximum usage metrics.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 8: Reviews & Ratings (Finance) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'finance'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-teal-50 dark:bg-teal-500/10 flex items-center justify-center text-teal-600 dark:text-teal-400">
                            <!-- SVG Review -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 08</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Reviews & Ratings</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Establish trust in the community. Let verified players score their match experiences and submit feedback. Showcase average ratings directly on public venue listings.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 9: Location & Discovery (Management) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'management'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <!-- SVG Pin -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 09</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Location & Discovery</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Connect with players in your area. Our geo-based search allows players to locate sports complexes based on proximity, read coordinates, and load directions via Google Maps API.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 10: Push Notifications (Management) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'management'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <!-- SVG Bell -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 10</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Push Notifications</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Maintain consistent client communication. Send real-time transactional alert notifications regarding booking registrations, status approvals, slots adjustments, or cancellation confirmations.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 11: Analytics Dashboard (Management) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'management'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <!-- SVG Chart -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 11</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Analytics Dashboard</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Unlock accounting visibility. Review consolidated sales metrics, track field occupancy patterns, discover peak slot utilization trends, and monitor customer retention distributions.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 12: Slot Lock / Hold (Operations) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'operations'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <!-- SVG Lock -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 12</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Slot Lock / Hold</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Completely eradicate double bookings. Place temporary locks on specific slot times during player checkout sequences to prevent other teams from reserving the same slot concurrently.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 13: Support Messages (Management) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'management'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <!-- SVG Chat -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 13</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Support Messages</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Solve venue disputes directly. Establish an in-app ticketing and support chat channel between arena owners and the platform's primary SaaS administration team.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 14: Mobile-First Customer App (Management) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'management'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-center text-indigo-600 dark:text-indigo-400">
                            <!-- SVG Phone -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 14</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Mobile-First Customer App</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Provide an exceptional player experience. Customers can browse court configurations, check real-time slots, configure add-on equipment, and book instantly via a modern mobile app.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Module 15: Multi-Sport Support (Operations) -->
            <div 
                x-show="activeTab === 'all' || activeTab === 'operations'"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 hover:border-slate-350 dark:hover:border-slate-700 hover:shadow-md transition duration-300 flex flex-col justify-between"
            >
                <div class="space-y-6">
                    <div class="flex items-center justify-between">
                        <div class="w-11 h-11 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <!-- SVG Multi-Sport -->
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                            </svg>
                        </div>
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider bg-slate-100 dark:bg-slate-800 px-2.5 py-1 rounded-lg">Module 15</span>
                    </div>
                    <div class="space-y-2 text-left">
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Multi-Sport Support</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                            Flexibility for all sports. Manage allocations for Football pitches, Cricket nets, Badminton complexes, Basketball courts, tennis facilities, and multi-game fields simultaneously.
                        </p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <!-- Bottom CTA -->
    <section class="py-20 text-center max-w-4xl mx-auto px-6 space-y-6">
        <h2 class="text-3xl font-black text-slate-900 dark:text-white">Ready to digitalize your sports venue?</h2>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 max-w-xl mx-auto">
            From single-court owners to multi-venue sports systems, our software adapts to cover all your slot logistics and operational needs.
        </p>
        <div class="flex items-center justify-center gap-4">
            <a href="{{ route('register') }}" class="px-6 py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs uppercase tracking-wider shadow-md">
                Register Your Venue
            </a>
            <a href="{{ url('/pricing') }}" class="px-6 py-3.5 bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-800 hover:bg-slate-200 rounded-xl text-xs uppercase tracking-wider">
                Explore Pricing
            </a>
        </div>
    </section>
</x-marketing-layout>
