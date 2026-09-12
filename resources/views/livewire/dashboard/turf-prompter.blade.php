<?php

use Livewire\Volt\Component;

new class extends Component
{
    public function claimTurfAdmin(): void
    {
        $user = auth()->user();
        
        // Assign turf-admin and manager roles to the customer
        $user->assignRole('turf-admin');
        $user->assignRole('manager');
        
        session()->flash('success', 'Congratulations! You are now a Turf Admin and Manager. Welcome to your Turf Dashboard.');
        
        // Redirect to the turf dashboard
        $this->redirect(route('turf.dashboard'), navigate: true);
    }
}; ?>

<div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-6 sm:p-8 md:p-10 border border-slate-800 shadow-xl shadow-slate-900/10 text-white">
    <!-- Ambient Glow Decorators -->
    <div class="absolute -right-20 -top-20 w-72 h-72 rounded-full bg-emerald-500/15 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-20 -bottom-20 w-72 h-72 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 sm:gap-8">
        <div class="space-y-3 max-w-2xl text-left">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-emerald-300 text-[10px] sm:text-[11px] font-black tracking-widest uppercase backdrop-blur-md">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>{{ __('Partner With Us') }}</span>
            </div>
            <h3 class="text-xl sm:text-2xl md:text-3xl font-black text-white tracking-tight leading-snug">
                {{ __('Do you own or manage a Turf?') }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-medium">
                {{ __('Unlock host features to list sports fields, split time slots (including midnight categories), schedule equipment, hire managers, and accept online bookings seamlessly.') }}
            </p>
        </div>

        <div class="shrink-0 w-full lg:w-auto pt-2 lg:pt-0">
            <button 
                wire:click="claimTurfAdmin" 
                class="w-full lg:w-auto inline-flex items-center justify-center gap-2.5 px-7 py-3.5 bg-emerald-500 hover:bg-emerald-600 active:scale-95 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition duration-200 shadow-lg shadow-emerald-500/25 hover:shadow-emerald-500/35 cursor-pointer"
            >
                <span>{{ __('Yes, I have a Turf!') }}</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </div>
    </div>
</div>
