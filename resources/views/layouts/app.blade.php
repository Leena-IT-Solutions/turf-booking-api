<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

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
        <div class="min-h-screen bg-gray-50 dark:bg-gray-900" x-data="{ sidebarOpen: false }">
            <livewire:layout.navigation />

            <!-- Page Content Area -->
            <div class="lg:ps-64 flex flex-col min-h-screen pt-16 lg:pt-0">
                <!-- Global Top Bar for Turf Admins & Managers -->
                @auth
                    @if(auth()->user()->hasRole('turf-admin') || auth()->user()->hasRole('manager'))
                        <div class="sticky top-16 lg:top-0 z-10 bg-white dark:bg-gray-800 border-b border-gray-200/50 dark:border-gray-855 px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
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
