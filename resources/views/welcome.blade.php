<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'TurfBooking') }} - Book Your Next Match</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,900&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans bg-slate-900 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white">
        
        <!-- Background Accents -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
            <div class="absolute top-1/2 -left-40 w-96 h-96 rounded-full bg-indigo-500/5 blur-3xl"></div>
        </div>

        <!-- Header -->
        <header class="relative z-10 w-full max-w-7xl mx-auto px-6 py-6 flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                <div class="h-10 w-10 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-black text-xl shadow-lg shadow-emerald-500/5">
                    T
                </div>
                <span class="font-extrabold text-xl tracking-tight text-white">{{ config('app.name', 'TurfBooking') }}</span>
            </a>

            @if (Route::has('login'))
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-4.5 py-2 text-xs font-bold uppercase tracking-wider bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl transition duration-150 shadow-md shadow-emerald-500/10">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-xs font-semibold text-slate-400 hover:text-white transition duration-150">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-4.5 py-2 text-xs font-bold uppercase tracking-wider bg-emerald-500 hover:bg-emerald-400 text-slate-950 rounded-xl transition duration-150 shadow-md shadow-emerald-500/10">
                                Get Started
                            </a>
                        @endif
                    @endauth
                </div>
            @endif
        </header>

        <!-- Hero Section -->
        <main class="relative z-10 w-full max-w-7xl mx-auto px-6 py-16 sm:py-24 flex-grow flex flex-col items-center justify-center text-center">
            
            <!-- Badge Promo -->
            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800/80 border border-slate-700/50 text-[10px] font-bold tracking-wider uppercase text-emerald-400 mb-6">
                <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Play More, Coordinate Less
            </div>

            <!-- Title -->
            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-black text-white tracking-tight leading-tight max-w-4xl">
                Book Your Next Turf <span class="bg-gradient-to-r from-emerald-400 to-teal-300 bg-clip-text text-transparent">Instantly</span>
            </h1>

            <!-- Description -->
            <p class="mt-6 text-sm sm:text-base text-slate-400 max-w-2xl leading-relaxed">
                Discover the best local sports fields, view live calendar slots, and lock in your booking in seconds. Seamless coordination for players, powerful management systems for owners.
            </p>

            <!-- Actions -->
            <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center w-full max-w-md">
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm tracking-wide transition duration-150 shadow-lg shadow-emerald-500/10">
                    Find a Turf to Play
                </a>
                <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-6 py-3.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-sm tracking-wide border border-slate-700 transition duration-150">
                    Register Your Turf
                </a>
            </div>

            <!-- Features Grid -->
            <section class="mt-24 w-full grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Card 1 -->
                <div class="bg-slate-800/40 border border-slate-800 rounded-3xl p-8 text-left transition duration-200 hover:border-slate-700">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xl font-bold mb-6">
                        🔍
                    </div>
                    <h3 class="text-lg font-bold text-white">Seamless Discovery</h3>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        Find premium turfs sorted by proximity. Filter by field type, length, width, and amenities with absolute ease.
                    </p>
                </div>

                <!-- Card 2 -->
                <div class="bg-slate-800/40 border border-slate-800 rounded-3xl p-8 text-left transition duration-200 hover:border-slate-700">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xl font-bold mb-6">
                        ⚡
                    </div>
                    <h3 class="text-lg font-bold text-white">Instant Slots</h3>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        Say goodbye to endless phone calls. Browse calendar schedules, split custom hourly slots, and book your game instantly.
                    </p>
                </div>

                <!-- Card 3 -->
                <div class="bg-slate-800/40 border border-slate-800 rounded-3xl p-8 text-left transition duration-200 hover:border-slate-700">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xl font-bold mb-6">
                        👥
                    </div>
                    <h3 class="text-lg font-bold text-white">Manager & Staff Control</h3>
                    <p class="text-xs text-slate-400 mt-2 leading-relaxed">
                        Add staff admins and managers. Grant access permissions to specific locations or individual turfs with scoped security.
                    </p>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="relative z-10 w-full max-w-7xl mx-auto px-6 py-10 border-t border-slate-800/80 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'TurfBooking') }}. All rights reserved.</p>
            <div class="flex items-center gap-6">
                <a href="{{ route('privacy-policy') }}" class="hover:text-emerald-400 transition duration-150">Privacy Policy</a>
                <span class="text-slate-700">|</span>
                <a href="mailto:support@turfbooking.com" class="hover:text-emerald-400 transition duration-150">Support Contact</a>
            </div>
        </footer>

    </body>
</html>
