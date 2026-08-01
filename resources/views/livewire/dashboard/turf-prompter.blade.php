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

<div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 p-6 sm:p-8 md:p-10 border border-slate-800/80 shadow-2xl shadow-indigo-950/20 text-white">
    <!-- Ambient Glow Decorators -->
    <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-emerald-500/15 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-16 -bottom-16 w-64 h-64 rounded-full bg-indigo-500/15 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8">
        <div class="space-y-3 max-w-2xl text-left">
            <span class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-[10px] font-black tracking-widest uppercase text-emerald-400">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                {{ __('Partner With Us') }}
            </span>
            <h3 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-snug">
                {{ __('Do you own or manage a Turf?') }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed font-medium">
                {{ __('Unlock host features to list sports fields, split time slots (including midnight categories), schedule equipment, hire managers, and accept online bookings seamlessly.') }}
            </p>
        </div>

        <div class="shrink-0 w-full lg:w-auto">
            <button 
                wire:click="claimTurfAdmin" 
                class="w-full lg:w-auto inline-flex items-center justify-center gap-3 px-8 py-4 bg-emerald-500 hover:bg-emerald-400 active:scale-95 text-slate-950 font-black text-xs uppercase tracking-wider rounded-2xl transition duration-200 shadow-xl shadow-emerald-500/20 hover:shadow-emerald-500/30 cursor-pointer"
            >
                <span>{{ __('Yes, I have a Turf!') }}</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </div>
    </div>
</div>
