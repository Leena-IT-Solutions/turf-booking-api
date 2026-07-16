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

<div class="bg-gradient-to-br from-slate-900 to-slate-850 dark:from-slate-900 dark:to-slate-800 rounded-3xl border border-slate-800 p-6 sm:p-8 relative overflow-hidden shadow-xl shadow-slate-950/20">
    <!-- Ambient Glow Decoration -->
    <div class="absolute -right-20 -top-20 w-48 h-48 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-20 -bottom-20 w-48 h-48 rounded-full bg-indigo-500/5 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div class="space-y-2 max-w-xl text-left">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-500/10 text-[10px] font-bold tracking-wider uppercase text-emerald-400">
                Partner with us
            </span>
            <h4 class="text-xl sm:text-2xl font-black text-white tracking-tight">Do you own or manage a Turf?</h4>
            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                Unlock host features to list sports fields, split time slots (including midnight categories), schedule equipment, hire managers, and accept bookings.
            </p>
        </div>
        <div class="shrink-0 w-full md:w-auto">
            <button 
                wire:click="claimTurfAdmin" 
                class="w-full md:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-sm tracking-wide rounded-xl transition duration-150 shadow-lg shadow-emerald-500/10 cursor-pointer"
            >
                <span>Yes, I have a Turf!</span>
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </div>
    </div>
</div>
