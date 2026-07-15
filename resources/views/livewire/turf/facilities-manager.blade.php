<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    //
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <!-- Header Card -->
        <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm text-center">
            <div class="h-16 w-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-4 border border-indigo-100/50 dark:border-indigo-950/50">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider">{{ __('Facilities Manager') }}</h2>
            <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('The facilities manager is currently under construction. Soon, you will be able to manage amenities like parking, locker rooms, washrooms, and cafeteria options.') }}</p>
        </div>
    </div>
</div>
