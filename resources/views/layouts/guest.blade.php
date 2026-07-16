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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-800 dark:text-slate-100 antialiased bg-slate-50 dark:bg-slate-950 min-h-screen relative overflow-x-hidden flex flex-col justify-center items-center py-12 px-4 sm:px-6 lg:px-8 selection:bg-emerald-500 selection:text-white">
        <!-- Background Accents -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none z-0">
            <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full bg-emerald-500/5 dark:bg-emerald-500/10 blur-3xl"></div>
            <div class="absolute bottom-10 -left-40 w-96 h-96 rounded-full bg-indigo-500/5 dark:bg-indigo-500/10 blur-3xl"></div>
        </div>

        <div class="relative z-10 w-full sm:max-w-md">
            <!-- Brand Logo & Header Info -->
            <div class="mb-8 flex flex-col items-center text-center">
                <a href="/" wire:navigate class="inline-flex items-center gap-2.5 group">
                    <div class="h-12 w-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-100 dark:border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400 font-black text-2xl shadow-sm dark:shadow-lg dark:shadow-emerald-500/5 group-hover:scale-105 transition-all duration-300">
                        T
                    </div>
                    <span class="font-extrabold text-2xl tracking-tight text-slate-950 dark:text-white group-hover:text-emerald-500 transition-colors duration-300">{{ config('app.name', 'TurfBooking') }}</span>
                </a>
            </div>

            <!-- Card Shell -->
            <div class="w-full px-8 py-10 bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl border border-slate-150/80 dark:border-slate-800/80 shadow-xl rounded-2xl">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
