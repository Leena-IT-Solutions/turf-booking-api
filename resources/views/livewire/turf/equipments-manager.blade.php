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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider">{{ __('Equipments Manager') }}</h2>
            <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('The equipments manager is currently under construction. Soon, you will be able to manage inventory of sports accessories (e.g. balls, rackets, wickets, bibs) and associate rental prices.') }}</p>
        </div>
    </div>
</div>
