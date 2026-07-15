<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $userNames = explode(' ', auth()->user()->name);
    $userInitials = strtoupper(substr($userNames[0], 0, 1) . (isset($userNames[1]) ? substr($userNames[1], 0, 1) : ''));
@endphp

<div>
    <!-- Mobile Header -->
    <header class="lg:hidden fixed top-0 left-0 right-0 z-30 h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-4">
        <div class="flex items-center gap-2">
            <button @click="sidebarOpen = true" class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="font-bold text-lg text-gray-900 dark:text-white">TurfBooking</span>
        </div>
        <div class="flex items-center">
            <a href="{{ route('profile') }}" wire:navigate class="h-9 w-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-xs shadow-md shadow-indigo-500/20 dark:shadow-none">
                {{ $userInitials }}
            </a>
        </div>
    </header>

    <!-- Desktop Sidebar (always visible on lg screens) -->
    <aside class="fixed inset-y-0 left-0 z-20 hidden w-64 border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 lg:flex flex-col justify-between">
        <div>
            <!-- Sidebar Brand Section -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100 dark:border-gray-800">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                    <span class="font-bold text-lg text-gray-900 dark:text-white">{{ config('app.name', 'TurfBooking') }}</span>
                </a>
            </div>
            <!-- Navigation Links -->
            <nav class="p-4 space-y-6">
                <!-- Administrator Section -->
                @if (auth()->user()->hasRole('saas-admin'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Administrator') }}</div>
                        <a href="{{ route('saas.administrator') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.administrator') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.administrator') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Administrator') }}</span>
                        </a>
                        <a href="{{ route('saas.sliders') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.sliders') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.sliders') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Slider Manager') }}</span>
                        </a>
                        <a href="{{ route('saas.users') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.users') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.users') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="ms-3">{{ __('User Accounts') }}</span>
                        </a>
                        <a href="{{ route('saas.slot-categories') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.slot-categories') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.slot-categories') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Slot Categories') }}</span>
                        </a>
                        <a href="{{ route('saas.slots') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.slots') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.slots') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Slots') }}</span>
                        </a>
                        <a href="{{ route('saas.settings') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.settings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.settings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('SAAS Settings') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Turf Admin Section -->
                @if (auth()->user()->hasRole('turf-admin'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Turf Admin') }}</div>
                        <a href="{{ route('turf.dashboard') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span class="ms-3">{{ __('Turf Dashboard') }}</span>
                        </a>
                        <a href="{{ route('turf.locations') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.locations') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.locations') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Locations') }}</span>
                        </a>
                        <a href="{{ route('turf.turfs') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.turfs') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.turfs') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="ms-3">{{ __('Turfs') }}</span>
                        </a>
                        <a href="{{ route('turf.bookings') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.bookings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.bookings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Bookings') }}</span>
                        </a>
                        <a href="{{ route('turf.offers') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.offers') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.offers') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Offer & Discount') }}</span>
                        </a>
                        <a href="{{ route('turf.settings') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.settings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.settings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Settings') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Manager Section -->
                @if (auth()->user()->hasRole('manager'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Manager') }}</div>
                        <a href="{{ route('turf.dashboard') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span class="ms-3">{{ __('Turf Dashboard') }}</span>
                        </a>
                        <a href="{{ route('turf.locations') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.locations') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.locations') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Locations') }}</span>
                        </a>
                        <a href="{{ route('turf.turfs') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.turfs') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.turfs') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="ms-3">{{ __('Turfs') }}</span>
                        </a>
                        <a href="{{ route('turf.bookings') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.bookings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.bookings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Bookings') }}</span>
                        </a>
                        <a href="{{ route('turf.offers') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.offers') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.offers') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Offer & Discount') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Customer Section -->
                @if (auth()->user()->hasRole('customer'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Customer') }}</div>
                        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span class="ms-3">{{ __('Dashboard') }}</span>
                        </a>
                        <a href="{{ route('profile') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('profile') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('profile') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="ms-3">{{ __('Profile') }}</span>
                        </a>
                    </div>
                @endif
            </nav>
        </div>

        <!-- Sidebar User Card -->
        <div class="border-t border-gray-100 dark:border-gray-800 p-5 bg-gray-50/30 dark:bg-gray-900/10">
            <div class="flex items-center gap-4">
                <div class="h-11 w-11 shrink-0 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-500/20 dark:shadow-none">
                    {{ $userInitials }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <div class="mt-5 space-y-1.5">
                <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2.5 w-full px-3 py-2.5 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/60 rounded-xl transition">
                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ __('Profile Settings') }}
                </a>
                <button wire:click="logout" class="flex items-center gap-2.5 w-full px-3 py-2.5 text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/15 rounded-xl transition text-start">
                    <svg class="h-4 w-4 text-red-500 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    {{ __('Log Out') }}
                </button>
            </div>
        </div>
    </aside>

    <!-- Mobile Drawer Backdrop -->
    <div x-show="sidebarOpen" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false" 
         class="fixed inset-0 z-45 bg-gray-950/40 dark:bg-gray-950/60 backdrop-blur-sm lg:hidden"
         style="display: none;"></div>

    <!-- Mobile Drawer Sidebar -->
    <div x-show="sidebarOpen" 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-x-full"
         x-transition:enter-end="translate-x-0"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-x-0"
         x-transition:leave-end="-translate-x-full"
         class="fixed inset-y-0 left-0 z-50 w-64 bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 flex flex-col justify-between lg:hidden"
         style="display: none;">
        <div>
            <!-- Sidebar Brand Section -->
            <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-2">
                    <x-application-logo class="h-8 w-auto fill-current text-indigo-600 dark:text-indigo-400" />
                    <span class="font-bold text-lg text-gray-900 dark:text-white">{{ config('app.name', 'TurfBooking') }}</span>
                </div>
                <button @click="sidebarOpen = false" class="p-1 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <!-- Navigation Links -->
            <nav class="p-4 space-y-6">
                <!-- Administrator Section -->
                @if (auth()->user()->hasRole('saas-admin'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Administrator') }}</div>
                        <a href="{{ route('saas.administrator') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.administrator') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.administrator') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Administrator') }}</span>
                        </a>
                        <a href="{{ route('saas.sliders') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.sliders') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.sliders') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Slider Manager') }}</span>
                        </a>
                        <a href="{{ route('saas.users') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.users') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.users') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span class="ms-3">{{ __('User Accounts') }}</span>
                        </a>
                        <a href="{{ route('saas.slot-categories') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.slot-categories') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.slot-categories') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Slot Categories') }}</span>
                        </a>
                        <a href="{{ route('saas.slots') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.slots') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.slots') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Slots') }}</span>
                        </a>
                        <a href="{{ route('saas.settings') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('saas.settings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('saas.settings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('SAAS Settings') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Turf Admin Section -->
                @if (auth()->user()->hasRole('turf-admin'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Turf Admin') }}</div>
                        <a href="{{ route('turf.dashboard') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span class="ms-3">{{ __('Turf Dashboard') }}</span>
                        </a>
                        <a href="{{ route('turf.locations') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.locations') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.locations') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Locations') }}</span>
                        </a>
                        <a href="{{ route('turf.turfs') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.turfs') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.turfs') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="ms-3">{{ __('Turfs') }}</span>
                        </a>
                        <a href="{{ route('turf.bookings') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.bookings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.bookings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Bookings') }}</span>
                        </a>
                        <a href="{{ route('turf.offers') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.offers') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.offers') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Offer & Discount') }}</span>
                        </a>
                        <a href="{{ route('turf.settings') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.settings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.settings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Settings') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Manager Section -->
                @if (auth()->user()->hasRole('manager'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Manager') }}</div>
                        <a href="{{ route('turf.dashboard') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span class="ms-3">{{ __('Turf Dashboard') }}</span>
                        </a>
                        <a href="{{ route('turf.locations') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.locations') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.locations') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Locations') }}</span>
                        </a>
                        <a href="{{ route('turf.turfs') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.turfs') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.turfs') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                            <span class="ms-3">{{ __('Turfs') }}</span>
                        </a>
                        <a href="{{ route('turf.bookings') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.bookings') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.bookings') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="ms-3">{{ __('Bookings') }}</span>
                        </a>
                        <a href="{{ route('turf.offers') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('turf.offers') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('turf.offers') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="ms-3">{{ __('Offer & Discount') }}</span>
                        </a>
                    </div>
                @endif

                <!-- Customer Section -->
                @if (auth()->user()->hasRole('customer'))
                    <div class="space-y-2">
                        <div class="px-4 py-1.5 text-[9px] font-black uppercase text-gray-400 dark:text-gray-500 tracking-widest">{{ __('Customer') }}</div>
                        <a href="{{ route('dashboard') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            <span class="ms-3">{{ __('Dashboard') }}</span>
                        </a>
                        <a href="{{ route('profile') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('profile') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                            <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('profile') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="ms-3">{{ __('Profile') }}</span>
                        </a>
                    </div>
                @endif
            </nav>
        </div>

        <!-- Sidebar User Card -->
        <div class="border-t border-gray-100 dark:border-gray-800 p-5 bg-gray-50/30 dark:bg-gray-900/10">
            <div class="flex items-center gap-4">
                <div class="h-11 w-11 shrink-0 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-500/20 dark:shadow-none">
                    {{ $userInitials }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100 truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate mt-0.5">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <div class="mt-5 space-y-1.5">
                <a href="{{ route('profile') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center gap-2.5 w-full px-3 py-2.5 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800/60 rounded-xl transition">
                    <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    {{ __('Profile Settings') }}
                </a>
                <button wire:click="logout" class="flex items-center gap-2.5 w-full px-3 py-2.5 text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/15 rounded-xl transition text-start">
                    <svg class="h-4 w-4 text-red-500 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    {{ __('Log Out') }}
                </button>
            </div>
        </div>
    </div>
</div>
