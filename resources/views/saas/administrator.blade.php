<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Administrator') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="sm:px-6 lg:px-8 space-y-6">
            <!-- Welcome Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-bold">{{ __("Welcome back, ") . auth()->user()->name }}!</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ __("You're logged in as administrator.") }}</p>
                    </div>
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 rounded-full text-xs font-bold uppercase tracking-wider">
                        <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        System Online
                    </div>
                </div>
            </div>

            <!-- Stats Placeholder Card -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 sm:p-8">
                <div class="flex flex-col items-center justify-center text-center py-12 space-y-3">
                    <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 rounded-2xl flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2z" />
                        </svg>
                    </div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Business Overview & Statistics') }}</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 max-w-sm leading-relaxed">
                        {{ __('This dashboard is currently initialized. Business insights, booking rates, and platform usage metrics will be displayed here soon.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
