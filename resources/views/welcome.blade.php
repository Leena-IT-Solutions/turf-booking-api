<x-marketing-layout>
    <x-slot name="title">
        {{ config('app.name', 'TurfBooking') }} - Sports Venue Management & Slot Booking Platform
    </x-slot>
    <x-slot name="description">
        Manage your turf, schedule bookings, collect online payments, and grow your sports business. Easy turf bookings for players.
    </x-slot>
    <x-slot name="head">
        <script type="application/ld+json">
        {
          "@@context": "https://schema.org",
          "@@type": "SoftwareApplication",
          "name": "TurfBooking",
          "operatingSystem": "All",
          "applicationCategory": "BusinessApplication",
          "offers": {
            "@@type": "Offer",
            "price": "0",
            "priceCurrency": "INR"
          }
        }
        </script>
    </x-slot>

    <!-- Hero Section -->
    <section class="relative w-full bg-gradient-to-br from-slate-900 via-slate-950 to-indigo-950 text-white py-24 lg:py-32 overflow-hidden border-b border-slate-800">
        <!-- Ambient decorative glows -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-0 right-1/4 w-[500px] h-[500px] rounded-full bg-emerald-500/10 blur-3xl"></div>
            <div class="absolute bottom-1/4 left-1/4 w-[400px] h-[400px] rounded-full bg-indigo-500/15 blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-6 relative z-10 text-center space-y-8">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-[10px] font-bold uppercase tracking-wider text-indigo-400">
                ⚡ The Ultimate Sports Venue Suite
            </span>
            <h1 class="text-4xl sm:text-5xl lg:text-7xl font-black tracking-tight leading-[1.1] max-w-4xl mx-auto">
                Manage Your Arena. <br>
                <span class="bg-gradient-to-r from-emerald-400 via-teal-400 to-indigo-400 bg-clip-text text-transparent">Grow Your Bookings.</span>
            </h1>
            <p class="text-sm sm:text-base text-slate-400 max-w-xl mx-auto leading-relaxed font-semibold">
                An all-in-one management dashboard for turf owners synced in real-time with a live booking app for sports players.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center pt-4">
                <a href="{{ route('register') }}" class="px-8 py-4 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 text-slate-955 font-black rounded-xl text-xs sm:text-sm transition-all duration-300 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/35 hover:-translate-y-0.5 uppercase tracking-wider">
                    List Your Turf
                </a>
                <a href="{{ url('/download') }}" class="px-8 py-4 bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 font-bold rounded-xl text-xs sm:text-sm transition-all duration-300 hover:-translate-y-0.5 uppercase tracking-wider">
                    Download TurfBooking App
                </a>
            </div>
        </div>
    </section>

    <!-- Action Bar Section -->
    <section class="relative w-full bg-slate-50 py-12 -mt-16 z-20">
        <div class="max-w-7xl mx-auto px-6">
            <!-- Real HTML Action Bar -->
            <div class="bg-white border border-slate-200/80 px-6 py-5 flex flex-col md:flex-row items-center justify-between gap-6 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.06)]">
                    <!-- Left Section: For Turf Owners -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full md:w-[48%]">
                        <div class="flex items-center gap-3 text-left">
                            <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center text-indigo-600">
                                <!-- Shop Icon SVG -->
                                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="block text-xs font-black text-slate-900 uppercase tracking-wide">For Turf Owners</span>
                                <span class="block text-[10px] text-slate-500 font-medium leading-tight max-w-[200px] sm:max-w-xs">Manage bookings, slots, pricing, staff, earnings and more.</span>
                            </div>
                        </div>
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-[11px] uppercase tracking-wider rounded-xl transition duration-150 shadow-md text-center whitespace-nowrap">
                            List Your Turf
                        </a>
                    </div>

                    <!-- Vertical Divider (only visible on desktop md+) -->
                    <div class="hidden md:block w-[1px] h-10 bg-slate-200"></div>

                    <!-- Right Section: For Players -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 w-full md:w-[48%]">
                        <div class="flex items-center gap-3 text-left">
                            <div class="flex-shrink-0 w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <!-- Phone Icon SVG -->
                                <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <span class="block text-xs font-black text-emerald-600 uppercase tracking-wide">For Players</span>
                                <span class="block text-[10px] text-slate-500 font-medium leading-tight max-w-[200px] sm:max-w-xs">Search nearby turfs, check availability and book in seconds.</span>
                            </div>
                        </div>
                        <div class="flex flex-row md:flex-col xl:flex-row items-center gap-2.5 shrink-0">
                            <!-- Google Play Link -->
                            <a href="https://play.google.com/store/apps/details?id=com.infoleena.turf.booking&hl=en_IN" class="inline-flex items-center gap-2 bg-black text-white hover:bg-slate-900 px-3.5 py-1.5 rounded-xl border border-slate-800 transition duration-150 shadow-sm shrink-0 whitespace-nowrap">
                                <svg class="w-5 h-5 text-white fill-current shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 3.23v17.54c0 .54.37.93.88.97l9.47-9.47L5.88 2.26c-.51.04-.88.43-.88.97zm11.2 8.77l3.66-3.66c.38-.38.38-1 0-1.38L5.88 2.26l10.32 9.74zm4.14 1.38L15.47 9l-9.59 9.59c.51.04.88.43.88.97l13.58-6.19c.38-.17.38-.81 0-.99z"></path>
                                </svg>
                                <div class="text-left leading-tight shrink-0">
                                    <span class="block text-[7px] text-slate-400 font-bold uppercase tracking-wider">GET IT ON</span>
                                    <span class="block text-[10px] font-black text-white">Google Play</span>
                                </div>
                            </a>
                            <!-- App Store Link -->
                            <a href="https://apps.apple.com/in/app/turf-booking/id6788572230" class="inline-flex items-center gap-2 bg-black text-white hover:bg-slate-900 px-3.5 py-1.5 rounded-xl border border-slate-800 transition duration-150 shadow-sm shrink-0 whitespace-nowrap">
                                <svg class="w-5 h-5 text-white fill-current shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-.96.04-2.13.64-2.82 1.45-.6.69-1.12 1.83-.98 2.94.88.08 1.97-.52 2.81-1.33z"></path>
                                </svg>
                                <div class="text-left leading-tight shrink-0">
                                    <span class="block text-[7px] text-slate-400 font-bold uppercase tracking-wider">Download on the</span>
                                    <span class="block text-[10px] font-black text-white">App Store</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>



    <!-- Experiences Section -->
    <section class="py-20 bg-slate-50 border-b border-slate-200/65">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest bg-indigo-50 border border-indigo-100 px-3 py-1 rounded-full">Unified Ecosystem</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-slate-900 leading-tight">
                    One Platform.<br class="sm:hidden"> Two Experiences.
                </h2>
                <p class="text-sm text-slate-500 max-w-xl mx-auto">
                    A complete management suite for venue owners, synced in real-time with an easy-to-use booking app for players.
                </p>
            </div>

            <!-- Two Cards Split -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl mx-auto">
                <!-- Card 1: For Turf Owners -->
                <div class="bg-white border border-slate-200/80 rounded-3xl p-8 sm:p-10 shadow-lg hover:shadow-xl hover:border-slate-300 transition duration-300 flex flex-col justify-between space-y-8">
                    <div class="space-y-6">
                        <!-- Icon & Title -->
                        <div class="flex items-center gap-4">
                            <span class="text-3xl">🏟️</span>
                            <div>
                                <h3 class="text-xl font-black text-slate-900">For Turf Owners</h3>
                                <p class="text-xs text-slate-500 font-medium mt-1">Manage your complete turf business from one dashboard.</p>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="h-[1px] bg-slate-100 w-full"></div>

                        <!-- Features list -->
                        <div class="space-y-3">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dashboard Modules</span>
                            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">
                                @foreach([
                                    'Manage multiple locations',
                                    'Manage multiple turfs',
                                    'Manage slots',
                                    'Set pricing',
                                    'Manage bookings',
                                    'Track payments',
                                    'Manage staff',
                                    'View reports',
                                    'Manage facilities',
                                    'Manage equipment',
                                    'Create offers',
                                    'Manage subscriptions'
                                ] as $feat)
                                    <li class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                        <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {{ $feat }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="pt-4">
                        <a href="{{ route('register') }}" class="inline-flex items-center justify-center w-full px-6 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl transition duration-150 shadow-md">
                            Start Managing Your Turf &rarr;
                        </a>
                    </div>
                </div>

                <!-- Card 2: For Players -->
                <div class="bg-white border border-slate-200/80 rounded-3xl p-8 sm:p-10 shadow-lg hover:shadow-xl hover:border-slate-300 transition duration-300 flex flex-col justify-between space-y-8">
                    <div class="space-y-6">
                        <!-- Icon & Title -->
                        <div class="flex items-center gap-4">
                            <span class="text-3xl">⚽</span>
                            <div>
                                <h3 class="text-xl font-black text-slate-900">For Players</h3>
                                <p class="text-xs text-slate-500 font-medium mt-1">Discover and book turfs from the TurfBooking app.</p>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div class="h-[1px] bg-slate-100 w-full"></div>

                        <!-- Features list -->
                        <div class="space-y-3">
                            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">Player App Features</span>
                            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5">
                                @foreach([
                                    'Search nearby turfs',
                                    'View turf details',
                                    'Check available slots',
                                    'Compare prices',
                                    'View facilities',
                                    'Book turf',
                                    'Make online payment',
                                    'Receive booking confirmation',
                                    'View booking history'
                                ] as $feat)
                                    <li class="flex items-center gap-2 text-xs font-semibold text-slate-700">
                                        <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        {{ $feat }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>

                    <!-- CTA -->
                    <div class="pt-4">
                        <a href="{{ route('download') }}" class="inline-flex items-center justify-center w-full px-6 py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl transition duration-150 shadow-md">
                            Download the App &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pain Points Section -->
    <section class="py-24 bg-white border-b border-slate-200/60 relative overflow-hidden">
        <!-- Ambient background glows -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute -top-40 -left-40 w-96 h-96 rounded-full bg-rose-500/5 blur-3xl"></div>
            <div class="absolute bottom-20 -right-40 w-96 h-96 rounded-full bg-emerald-500/5 blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-6 relative z-10 space-y-16">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <span class="text-xs font-bold text-rose-600 uppercase tracking-widest bg-rose-50 border border-rose-100/60 px-3.5 py-1.5 rounded-full">Manual Hassles</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-slate-900 leading-tight">
                    Still Managing Your Turf Manually?
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 max-w-xl mx-auto leading-relaxed">
                    Manual operations slow down your business, cause scheduling headaches, and leave money on the table.
                </p>
            </div>

            <!-- Problems Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 max-w-6xl mx-auto">
                @php
                    $painPoints = [
                        [
                            'title' => 'Bookings scattered across WhatsApp',
                            'desc' => 'Important booking requests and details get lost in endless chat threads.',
                        ],
                        [
                            'title' => 'Phone calls for every booking',
                            'desc' => 'Answering calls all day disrupts operations and risks missing off-hours bookings.',
                        ],
                        [
                            'title' => 'Difficult slot management',
                            'desc' => 'Tracking open times on paper or spreadsheets is slow and prone to human errors.',
                        ],
                        [
                            'title' => 'Manual payment tracking',
                            'desc' => 'Chasing down bank transfers, screenshots, and cash payments is a constant headache.',
                        ],
                        [
                            'title' => 'No clear revenue reports',
                            'desc' => 'Calculating daily or monthly earnings and profit margins takes hours of manual work.',
                        ],
                        [
                            'title' => 'Double-booking risk',
                            'desc' => 'Accidentally booking two groups for the same slot hurts customer trust and reputation.',
                        ],
                        [
                            'title' => 'Difficult to manage multiple turfs',
                            'desc' => 'Checking availability and managing staff across different locations without one view.',
                        ],
                        [
                            'title' => 'No centralized customer records',
                            'desc' => 'Losing contact lists and booking histories, making customer outreach impossible.',
                        ],
                    ];
                @endphp

                @foreach($painPoints as $point)
                    <div class="group bg-slate-50 border border-slate-200/80 hover:border-rose-300 hover:bg-rose-50/20 rounded-3xl p-7 transition-all duration-300 hover:shadow-xl hover:shadow-rose-500/5 hover:-translate-y-1">
                        <div class="space-y-5">
                            <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-500 shrink-0 group-hover:scale-110 transition duration-300 shadow-sm">
                                <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div class="space-y-2">
                                <h3 class="text-sm font-extrabold text-slate-900 tracking-tight leading-snug">
                                    {{ $point['title'] }}
                                </h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-semibold">
                                    {{ $point['desc'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Solution Banner -->
            <div class="text-center pt-8">
                <div class="inline-flex flex-col sm:flex-row items-center gap-5 bg-gradient-to-r from-emerald-50 to-teal-50/60 border-2 border-emerald-500/20 rounded-3xl p-6 sm:px-8 shadow-md hover:shadow-lg transition-all duration-300 max-w-3xl mx-auto text-left relative overflow-hidden group">
                    <div class="absolute inset-0 bg-gradient-to-r from-emerald-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                    <div class="w-14 h-14 rounded-2xl bg-emerald-500 text-white flex items-center justify-center shrink-0 shadow-lg shadow-emerald-500/20 animate-pulse">
                        <svg class="w-6 h-6 transform -rotate-45" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.63 8.41a6 6 0 01-5.84 7.38h4.8m5.84-2.58l-5.84 5.84" />
                        </svg>
                    </div>
                    <div class="relative z-10 space-y-1">
                        <h4 class="text-base sm:text-lg font-black text-emerald-950">TurfBooking puts everything in one place.</h4>
                        <p class="text-xs text-emerald-850 font-bold leading-relaxed">
                            Stop wasting hours on manual administration. Streamline slot blocks, automate digital payments, and scale your court occupancy rates instantly.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TurfBooking Solution Section -->
    <section class="py-24 bg-slate-50 border-b border-slate-200/65">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <!-- Header description -->
            <div class="text-center max-w-3xl mx-auto space-y-4">
                <span class="text-xs font-bold text-indigo-600 uppercase tracking-widest bg-indigo-50 border border-indigo-100/65 px-3 py-1 rounded-full">TurfBooking Solution</span>
                <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight text-slate-900 leading-tight">
                    Everything You Need to Run Your Turf
                </h2>
                <p class="text-sm text-slate-500 max-w-xl mx-auto">
                    A comprehensive, modern management platform designed to automate operations, boost sales, and delight players.
                </p>
            </div>

            <!-- Features Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-6xl mx-auto">
                @php
                    $solutionFeatures = [
                        [
                            'num' => '01',
                            'title' => 'Turf Management',
                            'desc' => 'Manage multiple turfs, sports, surface types, dimensions and equipment from one dashboard.',
                            'color' => 'from-blue-500 to-indigo-500'
                        ],
                        [
                            'num' => '02',
                            'title' => 'Booking Management',
                            'desc' => 'Track online and offline bookings, payment status and customer information.',
                            'color' => 'from-emerald-500 to-teal-500'
                        ],
                        [
                            'num' => '03',
                            'title' => 'Smart Slot Management',
                            'desc' => 'Create and manage time slots and block slots for maintenance, private events or other purposes.',
                            'color' => 'from-amber-500 to-orange-500'
                        ],
                        [
                            'num' => '04',
                            'title' => 'Flexible Pricing',
                            'desc' => 'Set different prices according to time, day, sport or demand.',
                            'color' => 'from-violet-500 to-purple-500'
                        ],
                        [
                            'num' => '05',
                            'title' => 'Business & Earnings',
                            'desc' => 'Track bookings, commissions, collected revenue, pending payments and payouts.',
                            'color' => 'from-rose-500 to-pink-500'
                        ],
                        [
                            'num' => '06',
                            'title' => 'Reports & Analytics',
                            'desc' => 'Understand your bookings, revenue, payments and business performance.',
                            'color' => 'from-cyan-500 to-sky-500'
                        ],
                        [
                            'num' => '07',
                            'title' => 'Staff Management',
                            'desc' => 'Give managers and staff controlled access to help operate your turf.',
                            'color' => 'from-indigo-500 to-purple-500'
                        ],
                        [
                            'num' => '08',
                            'title' => 'Facilities & Equipment',
                            'desc' => 'Showcase your facilities and manage rentable or included equipment.',
                            'color' => 'from-emerald-500 to-green-500'
                        ]
                    ];
                @endphp

                @foreach($solutionFeatures as $feature)
                    <div class="group bg-white border border-slate-200/80 rounded-3xl p-6 hover:-translate-y-1 hover:shadow-xl hover:border-slate-300 transition duration-300 flex flex-col justify-between space-y-6">
                        <div class="space-y-4">
                            <!-- Number Indicator -->
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 group-hover:text-slate-500 transition duration-150">Module</span>
                                <span class="text-2xl font-black bg-gradient-to-r {{ $feature['color'] }} bg-clip-text text-transparent group-hover:scale-110 transition duration-300 font-mono">
                                    {{ $feature['num'] }}
                                </span>
                            </div>
                            <div class="space-y-2">
                                <h3 class="text-base font-bold text-slate-900 group-hover:text-indigo-600 transition duration-150">
                                    {{ $feature['title'] }}
                                </h3>
                                <p class="text-xs text-slate-500 leading-relaxed font-medium">
                                    {{ $feature['desc'] }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- CTA Button -->
            <div class="text-center pt-4">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs uppercase tracking-wider rounded-2xl transition duration-150 shadow-md hover:shadow-lg gap-2">
                    Manage Your Turf Smarter &rarr;
                </a>
            </div>
        </div>
    </section>

    <!-- Player Feature Teaser (Companion App Mockup/Ad) -->
    <section class="py-24 bg-gradient-to-b from-slate-100 to-white transition duration-300">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center">
            
            <!-- Graphic/App Mockup UI (Left Column) -->
            <div class="lg:col-span-5 relative flex justify-center">
                <div class="absolute inset-0 bg-emerald-500/10 rounded-3xl blur-2xl"></div>
                <div class="relative w-[290px] bg-slate-950 border-[8px] border-slate-900 rounded-[44px] shadow-2xl overflow-hidden aspect-[9/19.5] group">
                    <!-- camera notch -->
                    <div class="absolute top-0 left-1/2 transform -translate-x-1/2 h-5 w-28 bg-slate-950 rounded-b-2xl z-20"></div>
                    
                    <!-- inner app screen mockup -->
                    <div class="relative w-full h-full bg-slate-900 rounded-[34px] overflow-hidden flex flex-col justify-between p-5 pt-8 text-[11px]">
                        <!-- top bar -->
                        <div class="flex items-center justify-between text-[8px] text-slate-400 font-bold font-mono">
                            <span>9:41</span>
                            <div class="flex items-center gap-1">
                                <span>📶</span>
                                <span>🔋</span>
                            </div>
                        </div>

                        <!-- Header info -->
                        <div class="flex items-center justify-between mt-3 text-left">
                            <div>
                                <span class="text-slate-450 block text-[9px] font-bold">YOUR LOCAL COURT</span>
                                <span class="font-bold text-xs text-white">Greenfield Arena ⚽</span>
                            </div>
                            <span class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center cursor-pointer">🔍</span>
                        </div>

                        <!-- Slots Grid inside App -->
                        <div class="my-6 space-y-3 text-left">
                            <span class="font-bold text-[10px] text-slate-400 block">Select Slot Time:</span>
                            <div class="grid grid-cols-2 gap-2">
                                <button class="p-2 rounded bg-slate-800 text-slate-400 border border-slate-700 text-center font-bold text-[9px]">06:00 PM</button>
                                <button class="p-2 rounded bg-emerald-500 text-slate-950 text-center font-black border border-emerald-450 text-[9px]">07:00 PM</button>
                                <button class="p-2 rounded bg-slate-800 text-slate-400 border border-slate-700 text-center font-bold text-[9px]">08:00 PM</button>
                                <button class="p-2 rounded bg-slate-800 text-slate-400 border border-slate-700 text-center font-bold text-[9px]">09:00 PM</button>
                            </div>
                            
                            <!-- Booking summary card -->
                            <div class="p-3 bg-slate-800/80 border border-slate-700/60 rounded-xl space-y-2 mt-4 text-[9px]">
                                <div class="flex justify-between">
                                    <span class="text-slate-450">Hourly Slot Fee:</span>
                                    <span class="text-white font-bold font-mono">₹1,200.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-450">Amenities (Lights):</span>
                                    <span class="text-white font-bold font-mono">₹200.00</span>
                                </div>
                                <hr class="border-slate-700/50">
                                <div class="flex justify-between font-bold text-emerald-400">
                                    <span>Total:</span>
                                    <span class="font-mono">₹1,400.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- CTA Book button -->
                        <button class="w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl font-bold uppercase text-[9px] tracking-wider text-center shadow-lg shadow-emerald-500/10">
                            Book Instantly
                        </button>
                    </div>
                </div>
            </div>

            <!-- Content Details (Right Column) -->
            <div class="lg:col-span-7 space-y-6 text-left">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">For Players & Teams</span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 leading-tight">
                    Find Your Perfect Turf on the TurfBooking App
                </h2>
                <p class="text-xs sm:text-sm text-slate-550 leading-relaxed font-semibold">
                    Search nearby turfs, compare options, check available slots and book your game from your phone.
                </p>

                <!-- App Features list -->
                <div class="space-y-4">
                    <h3 class="text-xs font-bold text-slate-450 uppercase tracking-wider">App Features</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-xs text-slate-600 font-semibold">
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Nearby turf discovery
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Turf details & photos
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Live slot availability
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Pricing information
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Online booking
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Digital payments
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Booking confirmation
                        </div>
                        <div class="flex items-center gap-2.5">
                            <span class="text-emerald-500 font-bold">✔</span> Booking history
                        </div>
                    </div>
                </div>

                <!-- Download Badges -->
                <div class="flex items-center gap-4 pt-4 flex-wrap">
                    <!-- App Store Badge Link -->
                    <a href="https://apps.apple.com/in/app/turf-booking/id6788572230" class="group transition duration-200">
                        <div class="bg-slate-950 text-white hover:bg-slate-900 border border-slate-800 flex items-center gap-3 px-4 py-2.5 rounded-2xl shadow-md">
                            <svg class="w-5 h-5 text-white fill-current shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-.96.04-2.13.64-2.82 1.45-.6.69-1.12 1.83-.98 2.94.88.08 1.97-.52 2.81-1.33z"></path>
                            </svg>
                            <div class="text-left leading-none">
                                <span class="block text-[8px] text-slate-400 font-bold uppercase tracking-wider">Download on the</span>
                                <span class="text-xs font-black text-white">App Store</span>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Play Store Badge Link -->
                    <a href="https://play.google.com/store/apps/details?id=com.infoleena.turf.booking&hl=en_IN" class="group transition duration-200">
                        <div class="bg-slate-950 text-white hover:bg-slate-900 border border-slate-800 flex items-center gap-3 px-4 py-2.5 rounded-2xl shadow-md">
                            <svg class="w-5 h-5 text-white fill-current shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5 3.23v17.54c0 .54.37.93.88.97l9.47-9.47L5.88 2.26c-.51.04-.88.43-.88.97zm11.2 8.77l3.66-3.66c.38-.38.38-1 0-1.38L5.88 2.26l10.32 9.74zm4.14 1.38L15.47 9l-9.59 9.59c.51.04.88.43.88.97l13.58-6.19c.38-.17.38-.81 0-.99z"></path>
                            </svg>
                            <div class="text-left leading-none">
                                <span class="block text-[8px] text-slate-400 font-bold uppercase tracking-wider">GET IT ON</span>
                                <span class="text-xs font-black text-white">Google Play</span>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

        </div>
    </section>

    <!-- How It Works for Owners Section -->
    <section class="py-24 bg-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Venue Management</span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 leading-tight">
                    How It Works for Owners
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 font-semibold max-w-lg mx-auto">
                    Three simple steps to list your sports center, secure daily bookings, and streamline payments.
                </p>
            </div>

            <!-- 3-Column Steps Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-6 relative overflow-hidden group">
                    <span class="absolute top-4 right-6 text-7xl font-black text-slate-200/60 select-none font-mono group-hover:scale-110 transition duration-300">01</span>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div class="space-y-2 relative z-10">
                        <h3 class="text-lg font-bold text-slate-900">Register & List</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Sign up your arena, upload pitch photos, specify field amenities (washrooms, parking), and list your court details online.
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-6 relative overflow-hidden group">
                    <span class="absolute top-4 right-6 text-7xl font-black text-slate-200/60 select-none font-mono group-hover:scale-110 transition duration-300">02</span>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="space-y-2 relative z-10">
                        <h3 class="text-lg font-bold text-slate-900">Configure Calendar</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Define your working hours, assign hourly time slots, block schedules for offline walk-ins, and customize rates.
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-6 relative overflow-hidden group">
                    <span class="absolute top-4 right-6 text-7xl font-black text-slate-200/60 select-none font-mono group-hover:scale-110 transition duration-300">03</span>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="space-y-2 relative z-10">
                        <h3 class="text-lg font-bold text-slate-900">Automate Bookings</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Players book online directly through the mobile app, and booking revenues clear straight to your bank account.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works for Players Section -->
    <section class="py-24 bg-slate-50 border-t border-slate-100 relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-4">
                <span class="text-xs font-bold text-teal-600 uppercase tracking-widest block">Team Scheduling</span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 leading-tight">
                    How It Works for Players
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 font-semibold max-w-lg mx-auto">
                    Three simple steps to explore local pitches, book available slots, and coordinate matching details.
                </p>
            </div>

            <!-- 3-Column Steps Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Step 1 -->
                <div class="p-8 bg-white border border-slate-200 rounded-3xl space-y-6 relative overflow-hidden group">
                    <span class="absolute top-4 right-6 text-7xl font-black text-slate-200/60 select-none font-mono group-hover:scale-110 transition duration-300">01</span>
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="space-y-2 relative z-10">
                        <h3 class="text-lg font-bold text-slate-900">Discover Venues</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Explore available indoor or outdoor turf locations near you using GPS filters, or search directly by sports category.
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="p-8 bg-white border border-slate-200 rounded-3xl space-y-6 relative overflow-hidden group">
                    <span class="absolute top-4 right-6 text-7xl font-black text-slate-200/60 select-none font-mono group-hover:scale-110 transition duration-300">02</span>
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="space-y-2 relative z-10">
                        <h3 class="text-lg font-bold text-slate-900">Select & Reserve</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Choose your preferred slot hour, check active rental prices, review venue rules, and confirm reservation instantly.
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="p-8 bg-white border border-slate-200 rounded-3xl space-y-6 relative overflow-hidden group">
                    <span class="absolute top-4 right-6 text-7xl font-black text-slate-200/60 select-none font-mono group-hover:scale-110 transition duration-300">03</span>
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div class="space-y-2 relative z-10">
                        <h3 class="text-lg font-bold text-slate-900">Play & Split</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Share matching details with teammates directly from the app, split reservation costs, and head out to play!
                        </p>
                    </div>
                </div>
      
                </div>
            </div>

        </div>
    </section>

    <!-- Business Benefits Section -->
    <section class="py-24 bg-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Maximize Growth</span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 leading-tight">
                    Business Benefits
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 font-semibold max-w-lg mx-auto">
                    Automate your operations and elevate player satisfaction with our specialized venue tools.
                </p>
            </div>

            <!-- 3-Column Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Benefit 1: More Bookings -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-5 hover:shadow-md transition duration-300 group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition duration-300">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900">More bookings</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Fill your empty calendar slots automatically. TurfBooking enables players to discover your venue and book slots instantly, day or night.
                        </p>
                    </div>
                </div>

                <!-- Benefit 2: Less Manual Work -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-5 hover:shadow-md transition duration-300 group">
                    <div class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition duration-300">
                        <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900">Less manual work</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Say goodbye to endless phone calls and scattered WhatsApp messages. Coordinate scheduling and pricing details with zero manual overhead.
                        </p>
                    </div>
                </div>

                <!-- Benefit 3: Better Management -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-5 hover:shadow-md transition duration-300 group">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xl group-hover:scale-110 transition duration-300">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-slate-900">Better management</h3>
                        <p class="text-xs text-slate-500 font-semibold leading-relaxed">
                            Track bookings, manage staff roles, set dynamic rates, and monitor daily revenue payouts directly from your central administrator dashboard.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Preview Section -->
    <section class="py-24 bg-slate-50 border-y border-slate-100 relative overflow-hidden">
        <!-- Ambient subtle glow -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-1/2 left-1/4 w-[400px] h-[400px] rounded-full bg-emerald-500/5 blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto px-6 relative z-10 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">Subscription tiers</span>
                <h2 class="text-3xl sm:text-4xl font-black text-slate-900 leading-tight">
                    Simple, Transparent Pricing
                </h2>
                <p class="text-xs sm:text-sm text-slate-500 font-semibold max-w-lg mx-auto">
                    Choose the right plan to list your venue and automate booking calendars without hidden fees.
                </p>
            </div>

            <!-- Two Pricing Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- Free Plan Card -->
                <div class="bg-white border border-slate-200 p-8 rounded-3xl shadow-sm hover:shadow-md transition duration-300 flex flex-col justify-between relative group">
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-600">Standard</span>
                            <h3 class="text-xl font-bold text-slate-900">Free Listing</h3>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-black text-slate-900 font-mono">₹0</span>
                            <span class="text-xs text-slate-400 font-semibold">/ forever</span>
                        </div>
                        <ul class="space-y-3.5 text-xs text-slate-600 font-semibold border-t border-slate-100 pt-6">
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Turf listing visibility
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Basic arena profile
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Standard platform commission
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4">
                        <a href="{{ url('/pricing') }}" class="block w-full py-3.5 bg-slate-50 hover:bg-slate-100 text-slate-700 font-bold rounded-xl text-xs text-center border border-slate-200 transition">
                            Explore Free Plan
                        </a>
                    </div>
                </div>

                <!-- Pro Plan Card -->
                <div class="bg-white border-2 border-emerald-500 p-8 rounded-3xl shadow-md hover:shadow-lg transition duration-300 flex flex-col justify-between relative group">
                    <div class="absolute top-0 right-8 transform -translate-y-1/2">
                        <span class="px-3 py-1 bg-emerald-500 text-slate-950 font-black text-[9px] uppercase tracking-wider rounded-full shadow-sm">Popular Choice</span>
                    </div>
                    <div class="space-y-6">
                        <div class="space-y-2">
                            <span class="inline-flex px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700">Premium Management</span>
                            <h3 class="text-xl font-bold text-slate-900">Pro Tier</h3>
                        </div>
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-black text-slate-900 font-mono">₹3,000</span>
                            <span class="text-xs text-slate-400 font-semibold">/ turf / month</span>
                        </div>
                        <ul class="space-y-3.5 text-xs text-slate-600 font-semibold border-t border-slate-100 pt-6">
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Full booking automation suite
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Dynamic slot & pricing blocks
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Staff manager accounts
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Advanced reports & dashboard
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-emerald-500">✓</span> Lower platform commissions
                            </li>
                        </ul>
                    </div>
                    <div class="mt-8 pt-4">
                        <a href="{{ url('/pricing') }}" class="block w-full py-3.5 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black rounded-xl text-xs text-center shadow-md transition">
                            View Pro Plans
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer CTA link -->
            <div class="text-center pt-4">
                <a href="{{ url('/pricing') }}" class="inline-flex items-center gap-2 text-xs font-bold text-emerald-600 hover:underline">
                    View complete pricing details <span>→</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Testimonials / Success stories -->
    <section class="py-24 bg-white transition duration-300">
        <div class="max-w-7xl mx-auto px-6 space-y-16">
            <div class="text-center max-w-2xl mx-auto space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">TESTIMONIALS</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Loved By Arena Operators</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Testimonial 1 -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-6">
                    <p class="text-sm text-slate-600 italic leading-relaxed">
                        "Before TurfBooking, we had constant double bookings on Friday evenings due to receptionist coordination errors. Moving our schedule online helped us save 8+ hours a week in administration and boosted our slot occupancy by nearly 30%!"
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="h-11 w-11 rounded-full bg-emerald-500 flex items-center justify-center font-bold text-white text-xs">
                            MC
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Marcus Carter</span>
                            <span class="block text-[10px] text-slate-400">Managing Owner, Elite Sports Complex</span>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="p-8 bg-slate-50 border border-slate-200/60 rounded-3xl space-y-6">
                    <p class="text-sm text-slate-600 italic leading-relaxed">
                        "The dynamic pricing module is a complete game changer. We raised our Saturday night pricing for soccer pitches by 20%, and reduced early Wednesday rates to attract high school players. Revenues grew by 25% in just 60 days."
                    </p>
                    <div class="flex items-center gap-4">
                        <div class="h-11 w-11 rounded-full bg-teal-500 flex items-center justify-center font-bold text-white text-xs">
                            SR
                        </div>
                        <div>
                            <span class="block text-xs font-bold text-slate-900">Sandro Rossi</span>
                            <span class="block text-[10px] text-slate-400">Founder, Arena Futbol Club</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 bg-slate-50 border-t border-slate-200/60 transition duration-300">
        <div class="max-w-4xl mx-auto px-6 space-y-12">
            <div class="text-center space-y-4">
                <span class="text-xs font-bold text-emerald-500 uppercase tracking-widest block">HELP & SUPPORT</span>
                <h2 class="text-3xl font-extrabold tracking-tight text-slate-900">Frequently Asked Questions</h2>
            </div>

            <!-- FAQ List -->
            <div class="space-y-4">
                
                <!-- FAQ 1 -->
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">Can I configure separate managers for my specific turf locations?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed">
                        Yes. TurfBooking supports multi-location role-based permissions. You can register multiple venues (e.g., Downtown Arena and Westend Pitch) and delegate specific managers to view and edit calendars solely for their allocated locations.
                    </p>
                </details>

                <!-- FAQ 2 -->
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How does offline cash booking reconcile with online credit cards?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed">
                        When players walk in, your staff can book slots manually from the dashboard calendar and mark them as "On-Field Cash". The system pools this with online stripe transactions to give you a consolidated sales report.
                    </p>
                </details>

                <!-- FAQ 3 -->
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">Is there an integration option to rent gear (like balls and jerseys)?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed">
                        Absolutely. You can customize your inventory items, pricing, and quantity inside the Facilities & Equipment manager. Players can add equipment items directly to their cart when reserving their hourly slots.
                    </p>
                </details>

                <!-- FAQ 4 -->
                <details class="group bg-white border border-slate-200 rounded-2xl p-5 [&_summary::-webkit-details-marker]:hidden">
                    <summary class="flex items-center justify-between cursor-pointer focus:outline-none">
                        <h3 class="text-xs sm:text-sm font-bold text-slate-900">How can we export sales files for our accounting audit?</h3>
                        <span class="shrink-0 transition-transform duration-350 text-slate-400 group-open:-rotate-180">▼</span>
                    </summary>
                    <p class="mt-4 text-xs text-slate-500 leading-relaxed">
                        Inside the Reports Manager, turf admins can export filtered spreadsheets (CSV/Excel) and occupancy charts detailing exact slot fees, coupon reductions, tax parameters, and net revenue distributions.
                    </p>
                </details>

            </div>
        </div>
    </section>

    <!-- Call To Action -->
    <section class="py-24 bg-gradient-to-r from-emerald-600 to-teal-800 text-white relative overflow-hidden text-center">
        <!-- background accents -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-white/10 via-transparent to-transparent opacity-50"></div>
        
        <div class="max-w-4xl mx-auto px-6 relative z-10 space-y-8">
            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black tracking-tight leading-tight">
                Ready to Take Your Turf Business to the Next Level?
            </h2>
            <p class="text-emerald-100 text-sm max-w-xl mx-auto leading-relaxed">
                Join hundreds of sport arena managers using TurfBooking to fill calendars, coordinate staff schedules, and drive recurring sales.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="px-8 py-4 bg-white hover:bg-slate-50 text-emerald-950 font-black rounded-xl text-sm transition-all duration-300 shadow-xl shadow-emerald-950/20 hover:shadow-emerald-950/30 hover:-translate-y-0.5">
                    List Your Turf
                </a>
                <a href="{{ route('download') }}" class="px-8 py-4 bg-white/10 hover:bg-white/20 border border-white/20 font-bold rounded-xl text-sm transition-all duration-300 text-white">
                    Download TurfBooking App
                </a>
            </div>
        </div>
    </section>
</x-marketing-layout>
