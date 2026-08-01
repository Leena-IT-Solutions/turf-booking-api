<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6 w-full">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50 p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __("Welcome, ") . auth()->user()->name }}!</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Initially kept blank. Your bookings and history will appear here.') }}</p>
                </div>
                <div class="flex items-center gap-2 px-4 py-1.5 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-full text-xs font-black uppercase tracking-wider">
                    <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                    {{ __('Account Active') }}
                </div>
            </div>

            @if(!(auth()->user()->hasRole('turf-admin') && auth()->user()->hasRole('manager')))
                <livewire:dashboard.turf-prompter />
            @endif
        </div>
    </div>
</x-app-layout>
