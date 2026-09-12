<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto space-y-6 sm:space-y-8">
        @if(!(auth()->user()->hasRole('turf-admin') && auth()->user()->hasRole('manager')))
            <livewire:dashboard.turf-prompter />
        @endif

        <livewire:dashboard.customer-bookings />
    </div>
</x-app-layout>
