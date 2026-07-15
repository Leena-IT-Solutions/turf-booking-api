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
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
            <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 uppercase tracking-wider">{{ __('Photos Manager') }}</h2>
            <p class="text-xs text-gray-400 mt-2 max-w-sm mx-auto leading-relaxed">{{ __('The photo gallery manager is currently under construction. Soon, you will be able to upload, sort, and display high-quality visual showcases for your playing fields.') }}</p>
        </div>
    </div>
</div>
