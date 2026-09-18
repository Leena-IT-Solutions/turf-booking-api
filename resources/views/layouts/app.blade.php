<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'TurfBooking') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50" x-data="{ sidebarOpen: false }">
            <!-- Impersonation Banner for SaaS Admin -->
            @if(session()->has('impersonator_id'))
                @php
                    $impersonatedTurf = \App\Models\Turf::with('location')->find(session('active_turf_id'));
                @endphp
                <div class="sticky top-0 z-50 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white px-4 sm:px-6 py-2.5 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs shadow-lg border-b border-indigo-500/30">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                        <span class="font-medium text-slate-200">
                            SaaS Admin Impersonation: Managing <strong>{{ $impersonatedTurf?->name ?? 'Turf' }}</strong> as Owner <strong>{{ auth()->user()->name }}</strong>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('saas.impersonation.leave') }}" class="px-3 py-1 bg-amber-400 hover:bg-amber-300 text-gray-950 font-black rounded-lg transition shadow-xs">
                            Exit & Return to SaaS Admin
                        </a>
                        <button onclick="window.close()" class="px-2.5 py-1 bg-white/10 hover:bg-white/20 text-white rounded-lg transition font-medium">
                            Close Tab
                        </button>
                    </div>
                </div>
            @endif

            <livewire:layout.navigation />

            <!-- Page Content Area -->
            <div class="lg:ps-64 flex flex-col min-h-screen pt-16 lg:pt-0">
                <!-- Global Top Bar for Turf Admins & Managers -->
                @auth
                    @if(auth()->user()->hasRole('turf-admin') || auth()->user()->hasRole('manager'))
                        <div class="sticky top-16 lg:top-0 z-10 bg-white border-b border-gray-200/50 px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
                            <livewire:layout.global-context-selector />
                        </div>
                    @endif
                @endauth



                <!-- Page Content -->
                <main class="flex-grow p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
