<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'TurfBooking') }} - Premium Turf Scheduling & Booking Platform</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="antialiased font-sans bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white">
        
        <!-- Background Ambient Glow -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-0 right-1/4 w-[500px] h-[500px] rounded-full bg-emerald-500/5 dark:bg-emerald-400/5 blur-3xl"></div>
            <div class="absolute top-1/3 -left-20 w-[400px] h-[400px] rounded-full bg-indigo-500/5 dark:bg-indigo-400/5 blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-[450px] h-[450px] rounded-full bg-emerald-500/5 dark:bg-emerald-500/10 blur-3xl"></div>
        </div>

        <!-- Sticky Header / Navigation -->
        <header 
            x-data="{ mobileMenuOpen: false, scrolled: false }"
            x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
            :class="scrolled ? 'bg-white/80 dark:bg-slate-900/80 border-slate-200/50 dark:border-slate-800/50 shadow-md backdrop-blur-md' : 'bg-white/30 dark:bg-slate-950/30 backdrop-blur-sm border-transparent shadow-none'"
            class="sticky top-0 z-50 w-full border-b bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-slate-200/50 dark:border-slate-800/50 shadow-sm transition-all duration-300 py-4"
        >
            <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
                <!-- Logo & Brand -->
                <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                    <div class="relative flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-md shadow-emerald-500/20 group-hover:scale-105 transition duration-300">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <span class="font-black text-xl tracking-tight text-slate-900 dark:text-white group-hover:text-emerald-500 dark:group-hover:text-emerald-400 transition duration-150">
                        {{ config('app.name', 'TurfBooking') }}
                    </span>
                </a>

                <!-- Desktop Navigation Menu -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="{{ url('/') }}" class="text-sm font-semibold {{ Request::is('/') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400' }} transition duration-150">Home</a>
                    <a href="{{ url('/features') }}" class="text-sm font-semibold {{ Request::is('features') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400' }} transition duration-150">Features</a>
                    <a href="{{ url('/pricing') }}" class="text-sm font-semibold {{ Request::is('pricing') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400' }} transition duration-150">Pricing</a>
                    <a href="{{ url('/contact') }}" class="text-sm font-semibold {{ Request::is('contact') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400' }} transition duration-150">Contact</a>
                </nav>

                <!-- Navigation Auth Actions -->
                <div class="hidden md:flex items-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white dark:text-slate-950 rounded-xl transition-all duration-300 shadow-md shadow-emerald-500/10 hover:shadow-lg hover:shadow-emerald-500/20 hover:-translate-y-0.5">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-xs font-bold uppercase tracking-wider text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition duration-150 py-2.5 px-4 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white dark:text-slate-950 rounded-xl transition-all duration-300 shadow-md shadow-emerald-500/10 hover:shadow-lg hover:shadow-emerald-500/20 hover:-translate-y-0.5">
                                Get Started
                            </a>
                        @endif
                    @endauth
                </div>

                <!-- Mobile Menu Button -->
                <button 
                    @click="mobileMenuOpen = !mobileMenuOpen" 
                    type="button" 
                    class="md:hidden p-2 rounded-xl text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 focus:outline-none"
                    aria-label="Toggle menu"
                >
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path x-show="mobileMenuOpen" style="display: none;" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Slide-Down Menu -->
            <div 
                x-show="mobileMenuOpen" 
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 -translate-y-4"
                style="display: none;"
                class="md:hidden absolute top-full left-0 w-full bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 py-6 px-6 shadow-xl"
            >
                <div class="flex flex-col gap-4">
                    <a @click="mobileMenuOpen = false" href="{{ url('/') }}" class="text-base font-semibold py-2 {{ Request::is('/') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">Home</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/features') }}" class="text-base font-semibold py-2 {{ Request::is('features') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">Features</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/pricing') }}" class="text-base font-semibold py-2 {{ Request::is('pricing') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">Pricing</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/contact') }}" class="text-base font-semibold py-2 {{ Request::is('contact') ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-400' }}">Contact</a>
                    
                    <hr class="border-slate-200 dark:border-slate-800 my-2">
                    
                    @auth
                        <a @click="mobileMenuOpen = false" href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold rounded-xl text-center shadow-md">
                            Dashboard
                        </a>
                    @else
                        <a @click="mobileMenuOpen = false" href="{{ route('login') }}" class="inline-flex items-center justify-center w-full py-3 text-slate-700 dark:text-slate-300 font-semibold border border-slate-200 dark:border-slate-800 rounded-xl text-center hover:bg-slate-50 dark:hover:bg-slate-850">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a @click="mobileMenuOpen = false" href="{{ route('register') }}" class="inline-flex items-center justify-center w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold rounded-xl text-center shadow-md mt-2">
                                Get Started
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <!-- Page Main Content Slot -->
        <main class="relative flex-grow w-full">
            {{ $slot }}
        </main>

        <!-- Premium Footer -->
        <footer class="relative z-10 w-full border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/50 backdrop-blur-sm pt-16 pb-12 transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <!-- Branding and App Downloads -->
                <div class="md:col-span-1 space-y-5">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-md shadow-emerald-500/20">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <span class="font-extrabold text-lg text-slate-900 dark:text-white">{{ config('app.name', 'TurfBooking') }}</span>
                    </a>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        The all-in-one platform for sports facility owners and players. Simplify operations, automate slots scheduling, and boost occupancy rates instantly.
                    </p>
                    
                    <!-- App Badges -->
                    <div class="pt-2 space-y-2">
                        <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Download companion app</span>
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- App Store Badge Link -->
                            <a href="#" class="group transition duration-200">
                                <div class="bg-black text-white hover:bg-slate-900 border border-slate-800 flex items-center gap-2 px-2.5 py-1.5 rounded-lg shadow-sm">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-.96.04-2.13.64-2.82 1.45-.6.69-1.12 1.83-.98 2.94.88.08 1.97-.52 2.81-1.33z"></path>
                                    </svg>
                                    <div class="text-left leading-none">
                                        <span class="block text-[7px] text-slate-400 font-medium">Download on the</span>
                                        <span class="text-[9px] font-bold">App Store</span>
                                    </div>
                                </div>
                            </a>
                            <!-- Play Store Badge Link -->
                            <a href="#" class="group transition duration-200">
                                <div class="bg-black text-white hover:bg-slate-900 border border-slate-800 flex items-center gap-2 px-2.5 py-1.5 rounded-lg shadow-sm">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 3.23v17.54c0 .54.37.93.88.97l9.47-9.47L5.88 2.26c-.51.04-.88.43-.88.97zm11.2 8.77l3.66-3.66c.38-.38.38-1 0-1.38L5.88 2.26l10.32 9.74zm4.14 1.38L15.47 9l-9.59 9.59c.51.04.88.43.88.97l13.58-6.19c.38-.17.38-.81 0-.99z"></path>
                                    </svg>
                                    <div class="text-left leading-none">
                                        <span class="block text-[7px] text-slate-400 font-medium">GET IT ON</span>
                                        <span class="text-[9px] font-bold">Google Play</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Column 2: Navigation Links -->
                <div class="space-y-4">
                    <span class="block text-xs font-bold text-slate-950 dark:text-white uppercase tracking-widest">Platform</span>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ url('/') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Home Overview</a></li>
                        <li><a href="{{ url('/features') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Key Features</a></li>
                        <li><a href="{{ url('/pricing') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Pricing Plans</a></li>
                        <li><a href="{{ route('register') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Register Venue</a></li>
                    </ul>
                </div>

                <!-- Column 3: Legal & Support -->
                <div class="space-y-4">
                    <span class="block text-xs font-bold text-slate-950 dark:text-white uppercase tracking-widest">Support & Legal</span>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ url('/contact') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Contact Us</a></li>
                        <li><a href="{{ route('privacy-policy') }}" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Privacy Policy</a></li>
                        <li><a href="mailto:support@turfbooking.com" class="text-slate-500 dark:text-slate-400 hover:text-emerald-600 dark:hover:text-emerald-400 transition">Support Email</a></li>
                        <li><span class="text-slate-400 dark:text-slate-500 font-medium">Venue Hotline: +1 (800) 555-TURF</span></li>
                    </ul>
                </div>

                <!-- Column 4: Newsletter -->
                <div class="space-y-4">
                    <span class="block text-xs font-bold text-slate-950 dark:text-white uppercase tracking-widest">Stay Updated</span>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Get business advice and software update alerts directly in your inbox.
                    </p>
                    <form x-data="{ email: '', subscribed: false }" @submit.prevent="subscribed = true; email = ''" class="flex gap-2">
                        <div class="relative w-full">
                            <input 
                                x-show="!subscribed"
                                type="email" 
                                required 
                                x-model="email"
                                placeholder="owner@sportscomplex.com" 
                                class="w-full text-xs px-3 py-2 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg focus:outline-none focus:border-emerald-500"
                            >
                            <span x-show="subscribed" class="block text-xs font-semibold text-emerald-600 dark:text-emerald-400 py-2">
                                ✓ Subscribed successfully!
                            </span>
                        </div>
                        <button 
                            x-show="!subscribed"
                            type="submit" 
                            class="px-4 py-2 text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-400 rounded-lg shadow-sm transition"
                        >
                            Subscribe
                        </button>
                    </form>
                </div>
            </div>

            <!-- Copyright and Social Icons -->
            <div class="max-w-7xl mx-auto px-6 border-t border-slate-200 dark:border-slate-800 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'TurfBooking') }}. All rights reserved.</p>
                <div class="flex items-center gap-6">
                    <a href="#" class="hover:text-emerald-500 transition"><span class="sr-only">Twitter</span>𝕏</a>
                    <a href="#" class="hover:text-emerald-500 transition"><span class="sr-only">Instagram</span>📸</a>
                    <a href="#" class="hover:text-emerald-500 transition"><span class="sr-only">LinkedIn</span>💼</a>
                </div>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
