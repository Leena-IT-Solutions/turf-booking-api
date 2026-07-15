<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    // Placeholder component
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">
        <div class="bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('Offer & Discount') }}</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Setup active discount coupon codes, percentage/flat discounts, and dates eligibility.') }}</p>
        </div>
    </div>
</div>
