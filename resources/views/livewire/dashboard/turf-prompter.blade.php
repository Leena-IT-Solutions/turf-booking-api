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

<div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-emerald-50/90 via-teal-50/60 to-indigo-50/80 p-6 sm:p-8 md:p-10 border-2 border-emerald-500/20 shadow-xl shadow-emerald-600/10">
    <!-- Left Highlight Strip -->
    <div class="absolute left-0 top-0 bottom-0 w-2.5 bg-gradient-to-b from-emerald-500 to-teal-600"></div>

    <!-- Background Decorative Glows -->
    <div class="absolute -right-16 -top-16 w-72 h-72 rounded-full bg-emerald-400/20 blur-3xl pointer-events-none"></div>
    <div class="absolute -left-16 -bottom-16 w-72 h-72 rounded-full bg-teal-400/20 blur-3xl pointer-events-none"></div>

    <div class="relative z-10 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6 sm:gap-10 pl-2">
        <div class="space-y-3.5 max-w-2xl text-left">
            <!-- Highlighted "Partner With Us" Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-emerald-600 text-white font-black text-xs tracking-widest uppercase shadow-md shadow-emerald-600/30 border border-emerald-500">
                <span class="w-2.5 h-2.5 rounded-full bg-white animate-pulse"></span>
                <span>{{ __('Partner With Us') }}</span>
            </div>

            <!-- Title -->
            <h3 class="text-2xl sm:text-3xl font-black text-gray-900 tracking-tight leading-snug">
                {{ __('Do you own or manage a Turf?') }}
            </h3>

            <!-- Description -->
            <p class="text-xs sm:text-sm text-gray-700 leading-relaxed font-medium">
                {{ __('Unlock host features to list sports fields, split time slots (including midnight categories), schedule equipment, hire managers, and accept online bookings seamlessly.') }}
            </p>
        </div>

        <!-- Action Button with Generous Horizontal Padding -->
        <div class="shrink-0 w-full lg:w-auto pt-2 lg:pt-0">
            <button 
                wire:click="claimTurfAdmin" 
                class="w-full lg:w-auto inline-flex items-center justify-center gap-3 px-8 sm:px-10 py-4 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs sm:text-sm uppercase tracking-wider rounded-2xl transition duration-200 shadow-xl shadow-emerald-600/30 hover:shadow-emerald-600/40 whitespace-nowrap cursor-pointer"
            >
                <span>{{ __('Yes, I have a Turf!') }}</span>
                <svg class="h-4 w-4 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </div>
    </div>
</div>
