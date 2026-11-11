<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="w-full space-y-6 sm:space-y-8">
        @if(auth()->user()->hasRole('marketing') && !auth()->user()->hasRole('saas-admin'))
            <div class="bg-gradient-to-r from-indigo-600 via-violet-600 to-purple-700 rounded-3xl p-6 sm:p-8 text-white shadow-lg border border-indigo-500/20">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 text-white font-black text-[10px] tracking-widest uppercase">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Marketing & Turf Onboarding Workspace
                        </span>
                        <h3 class="text-2xl font-black tracking-tight">Welcome, {{ auth()->user()->name }}</h3>
                        <p class="text-xs sm:text-sm text-indigo-100 leading-relaxed">
                            Create new Turf Owner accounts, set up their Locations, Turfs, Photos, Slots, Pricing, Banking & GST, and verify/approve turfs to go live.
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 shrink-0">
                        <a href="{{ route('saas.users') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-3 bg-white text-indigo-700 hover:bg-indigo-50 font-black text-xs uppercase tracking-wider rounded-xl shadow-sm transition">
                            <span>1. User Accounts</span>
                        </a>
                        <a href="{{ route('saas.turfs') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-sm transition">
                            <span>2. Turfs Directory & Setup</span>
                        </a>
                        <a href="{{ route('saas.turf-verification') }}" wire:navigate class="inline-flex items-center gap-2 px-4 py-3 bg-white/15 hover:bg-white/25 text-white font-black text-xs uppercase tracking-wider rounded-xl border border-white/20 transition">
                            <span>3. Verify & Approve Turfs</span>
                        </a>
                    </div>
                </div>
            </div>
        @elseif(!(auth()->user()->hasRole('turf-admin') && auth()->user()->hasRole('manager')) && !auth()->user()->hasRole('saas-admin'))
            <livewire:dashboard.turf-prompter />
        @endif

        <livewire:dashboard.customer-bookings />
    </div>
</x-app-layout>
