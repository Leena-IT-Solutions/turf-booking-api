<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6 w-full">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            @if(!(auth()->user()->hasRole('turf-admin') && auth()->user()->hasRole('manager')))
                <livewire:dashboard.turf-prompter />
            @endif
        </div>
    </div>
</x-app-layout>
