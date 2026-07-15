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
            <a href="{{ route('profile') }}" wire:navigate class="h-9 w-9 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-xs shadow-md shadow-indigo-200 dark:shadow-none">
                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
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
                    <span class="font-bold text-lg text-gray-900 dark:text-white">TurfBooking</span>
                </a>
            </div>
            <!-- Navigation Links -->
            <nav class="p-4 space-y-2">
                <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                    <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="ms-3">{{ __('Dashboard') }}</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar User Card -->
        <div class="border-t border-gray-100 dark:border-gray-800 p-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-200 dark:shadow-none">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('profile') }}" wire:navigate class="flex-grow text-center text-xs font-semibold py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    {{ __('Profile') }}
                </a>
                <button wire:click="logout" class="flex-grow text-center text-xs font-semibold py-2 px-3 rounded-lg bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition">
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
         class="fixed inset-0 z-40 bg-gray-950/40 dark:bg-gray-950/60 backdrop-blur-sm lg:hidden"
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
                    <span class="font-bold text-lg text-gray-900 dark:text-white">TurfBooking</span>
                </div>
                <button @click="sidebarOpen = false" class="p-1 rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none">
                    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <!-- Navigation Links -->
            <nav class="p-4 space-y-2">
                <a href="{{ route('dashboard') }}" wire:navigate @click="sidebarOpen = false" class="flex items-center px-4 py-3 text-sm font-medium rounded-xl transition duration-150 ease-in-out {{ request()->routeIs('dashboard') ? 'bg-indigo-50/70 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-gray-100' }}">
                    <svg class="h-5 w-5 text-gray-500 {{ request()->routeIs('dashboard') ? 'text-indigo-600 dark:text-indigo-400' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="ms-3">{{ __('Dashboard') }}</span>
                </a>
            </nav>
        </div>

        <!-- Sidebar User Card -->
        <div class="border-t border-gray-100 dark:border-gray-800 p-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold text-sm shadow-md shadow-indigo-200 dark:shadow-none">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <a href="{{ route('profile') }}" wire:navigate @click="sidebarOpen = false" class="flex-grow text-center text-xs font-semibold py-2 px-3 rounded-lg border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                    {{ __('Profile') }}
                </a>
                <button wire:click="logout" class="flex-grow text-center text-xs font-semibold py-2 px-3 rounded-lg bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 transition">
                    {{ __('Log Out') }}
                </button>
            </div>
        </div>
    </div>
</div>
