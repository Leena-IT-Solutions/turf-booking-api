<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'TurfBooking - Premium Turf Scheduling & Booking Platform' }}</title>
        <meta name="description" content="{{ $description ?? 'Manage your turf, schedule bookings, collect online payments, and grow your sports business. Easy turf bookings for players.' }}">
        <link rel="canonical" href="{{ url()->current() }}">

        <!-- Open Graph / Facebook -->
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $title ?? 'TurfBooking - Premium Turf Scheduling & Booking Platform' }}">
        <meta property="og:description" content="{{ $description ?? 'Manage your turf, schedule bookings, collect online payments, and grow your sports business. Easy turf bookings for players.' }}">
        <meta property="og:image" content="{{ asset('images/og-image.png') }}">

        <!-- Twitter -->
        <meta property="twitter:card" content="summary_large_image">
        <meta property="twitter:url" content="{{ url()->current() }}">
        <meta property="twitter:title" content="{{ $title ?? 'TurfBooking - Premium Turf Scheduling & Booking Platform' }}">
        <meta property="twitter:description" content="{{ $description ?? 'Manage your turf, schedule bookings, collect online payments, and grow your sports business. Easy turf bookings for players.' }}">
        <meta property="twitter:image" content="{{ asset('images/og-image.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:300,400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        {{ $head ?? '' }}
    </head>
    <body class="antialiased font-sans bg-slate-50 text-slate-800 min-h-screen flex flex-col justify-between selection:bg-emerald-500 selection:text-white">
        
        <!-- Background Ambient Glow -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute top-0 right-1/4 w-[500px] h-[500px] rounded-full bg-emerald-500/5 blur-3xl"></div>
            <div class="absolute top-1/3 -left-20 w-[400px] h-[400px] rounded-full bg-indigo-500/5 blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-[450px] h-[450px] rounded-full bg-emerald-500/5 blur-3xl"></div>
        </div>

        <!-- Sticky Header / Navigation -->
        <header 
            x-data="{ mobileMenuOpen: false, scrolled: false }"
            x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 20 })"
            :class="scrolled ? 'bg-white/80 border-slate-200/50 shadow-md backdrop-blur-md' : 'bg-white/30 backdrop-blur-sm border-transparent shadow-none'"
            class="sticky top-0 z-50 w-full border-b bg-white/80 backdrop-blur-md border-slate-200/50 shadow-sm transition-all duration-300 py-4"
        >
            <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
                <!-- Logo & Brand -->
                <a href="{{ url('/') }}" class="flex items-center gap-3 group">
                    <img src="{{ asset('images/logo.png') }}" class="h-10 w-10 rounded-2xl object-cover border border-emerald-100/50 shadow-sm group-hover:scale-105 transition-all duration-300" alt="Logo" />
                    <span class="font-black text-xl tracking-tight text-slate-900 group-hover:text-emerald-500 transition duration-150">
                        {{ config('app.name', 'TurfBooking') }}
                    </span>
                </a>

                <!-- Desktop Navigation Menu -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="{{ url('/') }}" class="text-sm font-semibold {{ Request::is('/') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">Home</a>
                    <a href="{{ url('/features') }}" class="text-sm font-semibold {{ Request::is('features') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">Features</a>
                    <a href="{{ url('/how-it-works') }}" class="text-sm font-semibold {{ Request::is('how-it-works') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">How It Works</a>
                    <a href="{{ url('/for-turf-owners') }}" class="text-sm font-semibold {{ Request::is('for-turf-owners') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">For Turf Owners</a>
                    <a href="{{ url('/pricing') }}" class="text-sm font-semibold {{ Request::is('pricing') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">Pricing</a>
                    <a href="{{ url('/faqs') }}" class="text-sm font-semibold {{ Request::is('faqs') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">FAQs</a>
                    <a href="{{ url('/contact') }}" class="text-sm font-semibold {{ Request::is('contact') ? 'text-emerald-600 ' : 'text-slate-600 hover:text-emerald-600 ' }} transition duration-150">Contact</a>
                </nav>

                <!-- Navigation Auth Actions -->
                <div class="hidden md:flex items-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white rounded-xl transition-all duration-300 shadow-md shadow-emerald-500/10 hover:shadow-lg hover:shadow-emerald-500/20 hover:-translate-y-0.5">
                            Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-xs font-bold uppercase tracking-wider text-slate-600 hover:text-slate-900 transition duration-150 py-2.5 px-4 rounded-xl hover:bg-slate-100">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center px-5 py-2.5 text-xs font-bold uppercase tracking-wider bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white rounded-xl transition-all duration-300 shadow-md shadow-emerald-500/10 hover:shadow-lg hover:shadow-emerald-500/20 hover:-translate-y-0.5">
                                Get Started
                            </a>
                        @endif
                    @endauth
                </div>

                <!-- Mobile Menu Button -->
                <button 
                    @click="mobileMenuOpen = !mobileMenuOpen" 
                    type="button" 
                    class="md:hidden p-2 rounded-xl text-slate-600 hover:bg-slate-100 focus:outline-none"
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
                class="md:hidden absolute top-full left-0 w-full bg-white border-b border-slate-200 py-6 px-6 shadow-xl"
            >
                <div class="flex flex-col gap-4">
                    <a @click="mobileMenuOpen = false" href="{{ url('/') }}" class="text-base font-semibold py-2 {{ Request::is('/') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">Home</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/features') }}" class="text-base font-semibold py-2 {{ Request::is('features') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">Features</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/how-it-works') }}" class="text-base font-semibold py-2 {{ Request::is('how-it-works') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">How It Works</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/for-turf-owners') }}" class="text-base font-semibold py-2 {{ Request::is('for-turf-owners') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">For Turf Owners</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/pricing') }}" class="text-base font-semibold py-2 {{ Request::is('pricing') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">Pricing</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/faqs') }}" class="text-base font-semibold py-2 {{ Request::is('faqs') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">FAQs</a>
                    <a @click="mobileMenuOpen = false" href="{{ url('/contact') }}" class="text-base font-semibold py-2 {{ Request::is('contact') ? 'text-emerald-600 ' : 'text-slate-600 ' }}">Contact</a>
                    
                    <hr class="border-slate-200 my-2">
                    
                    @auth
                        <a @click="mobileMenuOpen = false" href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl text-center shadow-md">
                            Dashboard
                        </a>
                    @else
                        <a @click="mobileMenuOpen = false" href="{{ route('login') }}" class="inline-flex items-center justify-center w-full py-3 text-slate-700 font-semibold border border-slate-200 rounded-xl text-center hover:bg-slate-50">
                            Log in
                        </a>
                        @if (Route::has('register'))
                            <a @click="mobileMenuOpen = false" href="{{ route('register') }}" class="inline-flex items-center justify-center w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl text-center shadow-md mt-2">
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
        <footer class="relative z-10 w-full border-t border-slate-200 bg-white backdrop-blur-sm pt-16 pb-12 transition-colors duration-300">
            <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <!-- Column 1: Brand Info -->
                <div class="space-y-5">
                    <a href="{{ url('/') }}" class="flex items-center gap-3">
                        <img src="{{ asset('images/logo.png') }}" class="h-9 w-9 rounded-xl object-cover border border-emerald-100/50 shadow-sm" alt="Logo" />
                        <span class="font-extrabold text-lg text-slate-900">TurfBooking</span>
                    </a>
                    <p class="text-xs text-slate-500 leading-relaxed font-medium">
                        The smarter way to discover, book and manage sports turfs.
                    </p>
                    
                    <!-- App Download Badges -->
                    <div class="space-y-3 pt-2">
                        <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">DOWNLOAD COMPANION APP</span>
                        <div class="flex items-center gap-3 flex-wrap">
                            <!-- App Store Badge Link -->
                            <a href="https://apps.apple.com/in/app/turf-booking/id6788572230" class="group transition duration-200">
                                <div class="bg-black text-white hover:bg-slate-900 border border-slate-800 flex items-center gap-2 px-3 py-1.5 rounded-xl shadow-sm">
                                    <svg class="w-4 h-4 text-white fill-current shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52(..)" />
                                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 4.17c.66-.81 1.11-1.93.99-3.06-.96.04-2.13.64-2.82 1.45-.6.69-1.12 1.83-.98 2.94.88.08 1.97-.52 2.81-1.33z"></path>
                                    </svg>
                                    <div class="text-left leading-none shrink-0">
                                        <span class="block text-[7px] text-slate-400 font-bold uppercase tracking-wider">Download on the</span>
                                        <span class="text-[10px] font-black text-white">App Store</span>
                                    </div>
                                </div>
                            </a>
                            <!-- Play Store Badge Link -->
                            <a href="https://play.google.com/store/apps/details?id=com.infoleena.turf.booking&hl=en_IN" class="group transition duration-200">
                                <div class="bg-black text-white hover:bg-slate-900 border border-slate-800 flex items-center gap-2 px-3 py-1.5 rounded-xl shadow-sm">
                                    <svg class="w-4 h-4 text-white fill-current shrink-0" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M5 3.23v17.54c0 .54.37.93.88.97l9.47-9.47L5.88 2.26c-.51.04-.88.43-.88.97zm11.2 8.77l3.66-3.66c.38-.38.38-1 0-1.38L5.88 2.26l10.32 9.74zm4.14 1.38L15.47 9l-9.59 9.59c.51.04.88.43.88.97l13.58-6.19c.38-.17.38-.81 0-.99z"></path>
                                    </svg>
                                    <div class="text-left leading-none shrink-0">
                                        <span class="block text-[7px] text-slate-400 font-bold uppercase tracking-wider">GET IT ON</span>
                                        <span class="text-[10px] font-black text-white">Google Play</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Column 2: For Players -->
                <div class="space-y-4">
                    <span class="block text-xs font-bold text-slate-955 uppercase tracking-widest">For Players</span>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ url('/download') }}" class="text-slate-500 hover:text-emerald-600 transition">Download App</a></li>
                        <li><a href="{{ url('/how-it-works') }}" class="text-slate-500 hover:text-emerald-600 transition">How It Works</a></li>
                        <li><a href="{{ url('/faqs#player-faqs') }}" class="text-slate-500 hover:text-emerald-600 transition">FAQs</a></li>
                        <li><a href="{{ url('/contact') }}" class="text-slate-500 hover:text-emerald-600 transition">Support</a></li>
                    </ul>
                </div>

                <!-- Column 3: For Turf Owners -->
                <div class="space-y-4">
                    <span class="block text-xs font-bold text-slate-955 uppercase tracking-widest">For Turf Owners</span>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ route('register') }}" class="text-slate-500 hover:text-emerald-600 transition">List Your Turf</a></li>
                        <li><a href="{{ url('/features') }}" class="text-slate-500 hover:text-emerald-600 transition">Features</a></li>
                        <li><a href="{{ url('/pricing') }}" class="text-slate-500 hover:text-emerald-600 transition">Pricing</a></li>
                        <li><a href="{{ route('login') }}" class="text-slate-500 hover:text-emerald-600 transition">Owner Login</a></li>
                        <li><a href="{{ url('/faqs#owner-faqs') }}" class="text-slate-500 hover:text-emerald-600 transition">Owner FAQs</a></li>
                    </ul>
                </div>

                <!-- Column 4: Company -->
                <div class="space-y-4">
                    <span class="block text-xs font-bold text-slate-955 uppercase tracking-widest">Company</span>
                    <ul class="space-y-2 text-xs">
                        <li><a href="{{ url('/#') }}" class="text-slate-500 hover:text-emerald-600 transition">About Us</a></li>
                        <li><a href="{{ url('/contact') }}" class="text-slate-500 hover:text-emerald-600 transition">Contact</a></li>
                        <li><a href="{{ route('privacy-policy') }}" class="text-slate-500 hover:text-emerald-600 transition">Privacy Policy</a></li>
                        <li><a href="{{ route('terms-and-conditions') }}" class="text-slate-500 hover:text-emerald-600 transition">Terms & Conditions</a></li>
                        <li><a href="{{ route('refund-policy') }}" class="text-slate-500 hover:text-emerald-600 transition">Refund/Cancellation Policy</a></li>
                    </ul>
                </div>
            </div>

            <!-- Copyright and Social Icons -->
            <div class="max-w-7xl mx-auto px-6 border-t border-slate-200 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'TurfBooking') }}. All rights reserved. | Designed & developed by <a href="https://leenaitsolutions.in" target="_blank" rel="noopener noreferrer" class="hover:text-emerald-500 font-medium transition-colors">Leena IT Solutions</a></p>
                <div class="flex items-center gap-6">
                    <a href="https://facebook.com/turfbooking" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-emerald-500 transition-colors" title="Facebook">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                        </svg>
                    </a>
                    <a href="https://instagram.com/turfbooking" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-emerald-500 transition-colors" title="Instagram">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M12.315 2c2.43 0 2.784.01 3.71.054 1.139.052 2.03.232 2.698.497a5.03 5.03 0 011.8 1.17 4.884 4.884 0 011.17 1.8c.263.668.443 1.56.497 2.698.043.924.053 1.282.053 3.71s-.01 2.784-.054 3.71c-.052 1.14-.232 2.03-.497 2.698a5.022 5.022 0 01-1.17 1.8 4.88 4.88 0 01-1.8 1.17c-.669.263-1.56.443-2.698.497-.923.043-1.282.054-3.71.054s-2.784-.01-3.71-.054c-1.139-.052-2.03-.232-2.699-.497a5.03 5.03 0 01-1.8-1.17 4.87 4.87 0 01-1.17-1.8c-.263-.669-.443-1.56-.497-2.698C2.01 14.8 2 14.442 2 12s.01-2.784.054-3.71c.052-1.139.232-2.03.497-2.698a5.029 5.029 0 011.17-1.8 4.883 4.883 0 011.8-1.17c.669-.263 1.56-.443 2.698-.497.923-.043 1.282-.054 3.71-.054zM12 5.38c-3.655 0-6.62 2.965-6.62 6.62s2.965 6.62 6.62 6.62 6.62-2.965 6.62-6.62-2.965-6.62-6.62-6.62zm0 10.925c-2.378 0-4.305-1.927-4.305-4.305s1.927-4.305 4.305-4.305 4.305 1.927 4.305 4.305-1.927 4.305-4.305 4.305zm5.305-10.09a1.094 1.094 0 11-2.188 0 1.094 1.094 0 012.188 0z" clip-rule="evenodd" />
                        </svg>
                    </a>
                    <a href="https://youtube.com/@turfbooking" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-emerald-500 transition-colors" title="YouTube">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M23.498 6.163a3.003 3.003 0 00-2.11-2.11C19.518 3.545 12 3.545 12 3.545s-7.518 0-9.388.508a3.003 3.003 0 00-2.11 2.11C0 8.033 0 12 0 12s0 3.967.502 5.837a3.003 3.003 0 002.11 2.11c1.87.508 9.388.508 9.388.508s7.518 0 9.388-.508a3.003 3.003 0 002.11-2.11C24 15.967 24 12 24 12s0-3.967-.502-5.837zM9.545 15.568V8.432L15.818 12l-6.273 3.568z" clip-rule="evenodd" />
                        </svg>
                    </a>
                    <a href="https://twitter.com/turfbooking" target="_blank" rel="noopener noreferrer" class="text-slate-400 hover:text-emerald-500 transition-colors" title="Twitter / X">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M13.682 10.629L20.22 3h-1.55l-5.678 6.602L8.455 3H3.22l6.857 9.979L3.22 21h1.55l6.007-6.982L15.545 21h5.235l-7.098-10.371zm-2.122 2.47L10.865 12.1l-5.518-7.902h2.38l4.316 6.176.695.996 5.793 8.29h-2.38l-4.708-6.734z" />
                        </svg>
                    </a>
                </div>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
