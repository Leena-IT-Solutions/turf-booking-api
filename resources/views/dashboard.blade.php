<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-3xl border border-gray-100 dark:border-gray-700/50 p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ __("Welcome, ") . auth()->user()->name }}!</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Initially kept blank. Your bookings and history will appear here.') }}</p>
            </div>
        </div>
    </div>
</x-app-layout>
