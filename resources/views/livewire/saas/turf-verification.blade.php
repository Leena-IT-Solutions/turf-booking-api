<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    //
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        
        <!-- Header Section -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Turf Verification') }}</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage and verify registered turf grounds and facilities in the system.') }}</p>
            </div>
        </div>

        <!-- Blank Body Card -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('This is the blank page for the Turf Verification process.') }}</p>
        </div>

    </div>
</div>
