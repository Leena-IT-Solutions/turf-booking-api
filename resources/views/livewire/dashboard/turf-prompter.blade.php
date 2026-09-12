<?php

use Livewire\Volt\Component;

new class extends Component
{
    public bool $showConfirmModal = false;

    public function openConfirmModal(): void
    {
        $this->showConfirmModal = true;
    }

    public function closeConfirmModal(): void
    {
        $this->showConfirmModal = false;
    }

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

        <!-- Action Button opening Confirmation Modal -->
        <div class="shrink-0 w-full lg:w-auto pt-2 lg:pt-0">
            <button 
                wire:click="openConfirmModal" 
                type="button"
                class="w-full lg:w-auto inline-flex items-center justify-center gap-3 px-8 sm:px-10 py-4 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs sm:text-sm uppercase tracking-wider rounded-2xl transition duration-200 shadow-xl shadow-emerald-600/30 hover:shadow-emerald-600/40 whitespace-nowrap cursor-pointer"
            >
                <span>{{ __('Yes, I have a Turf!') }}</span>
                <svg class="h-4 w-4 stroke-[3]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </button>
        </div>
    </div>

    <!-- Confirmation Modal -->
    @if ($showConfirmModal)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="relative bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl border border-gray-100 space-y-6 text-center">
                <div class="w-16 h-16 mx-auto rounded-3xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl shadow-inner">
                    🏟️
                </div>
                
                <div class="space-y-2">
                    <h3 class="text-xl font-black text-gray-900 tracking-tight">
                        {{ __('Become a Turf Partner') }}
                    </h3>
                    <p class="text-xs sm:text-sm text-gray-600 font-medium leading-relaxed">
                        {{ __('Are you sure you want to activate host features? This will grant your account Turf Admin & Manager privileges to list sports grounds, configure slot pricing, hire staff, and manage bookings.') }}
                    </p>
                </div>

                <div class="pt-2 flex items-center gap-3">
                    <button 
                        wire:click="closeConfirmModal" 
                        type="button" 
                        class="flex-1 py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-2xl transition cursor-pointer"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button 
                        wire:click="claimTurfAdmin" 
                        type="button" 
                        class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-emerald-600/30 transition cursor-pointer flex items-center justify-center gap-2"
                    >
                        <span>{{ __('Yes, Activate') }}</span>
                        <svg class="w-4 h-4 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
