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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider">{{ __('Sports Manager') }}</h2>
            <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('The sports manager is currently under construction. Soon, you will be able to manage sporting categories (e.g. Football, Cricket, Tennis) and map them to respective playing turfs.') }}</p>
        </div>
    </div>
</div>
