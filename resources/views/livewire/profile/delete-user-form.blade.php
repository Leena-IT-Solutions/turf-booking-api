<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <!-- Header with Icon & Subtitle -->
    <div class="flex items-start justify-between gap-4 border-b border-rose-100 pb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-sm font-bold shadow-xs">
                    ⚠️
                </div>
                <h2 class="text-xl font-black text-rose-950 tracking-tight">
                    {{ __('Delete Account') }}
                </h2>
            </div>
            <p class="text-xs text-rose-700/80 leading-relaxed font-medium pl-10">
                {{ __('Permanently delete your account, personal data, and related booking history.') }}
            </p>
        </div>
    </div>

    <div class="p-5 rounded-2xl bg-rose-50/70 border border-rose-200/80 space-y-3 max-w-2xl">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-xs text-rose-900 leading-relaxed font-medium">
                {{ __('Once your account is deleted, all associated reservations, wallet credits, and profile data will be permanently wiped. This action cannot be reversed. Please make sure to download any details you wish to keep.') }}
            </p>
        </div>

        <div class="pt-2">
            <button
                x-data=""
                x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
                type="button"
                class="px-6 py-3 bg-rose-600 hover:bg-rose-700 active:scale-95 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-rose-600/25 hover:shadow-rose-600/35 transition duration-200 inline-flex items-center gap-2 cursor-pointer"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                <span>{{ __('Delete My Account') }}</span>
            </button>
        </div>
    </div>

    <!-- Confirmation Modal Dialog -->
    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6 sm:p-8 space-y-6">
            <div class="w-14 h-14 rounded-3xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl mx-auto shadow-inner">
                🚨
            </div>

            <div class="text-center space-y-2">
                <h2 class="text-xl font-black text-gray-900 tracking-tight">
                    {{ __('Are you sure you want to delete your account?') }}
                </h2>

                <p class="text-xs text-gray-500 leading-relaxed font-medium max-w-md mx-auto">
                    {{ __('This action will permanently delete your account. Please enter your account password below to confirm this request.') }}
                </p>
            </div>

            <div class="space-y-1.5 max-w-sm mx-auto text-left">
                <label for="password" class="block text-xs font-black uppercase tracking-wider text-gray-700">
                    {{ __('Account Password') }} <span class="text-rose-500">*</span>
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <input
                        wire:model="password"
                        id="password"
                        name="password"
                        type="password"
                        class="block w-full pl-11 pr-4 py-3 text-xs sm:text-sm font-semibold text-gray-900 rounded-2xl border border-gray-200 focus:border-rose-500 focus:ring-4 focus:ring-rose-500/10 shadow-xs transition"
                        placeholder="{{ __('Enter your password') }}"
                    />
                </div>
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <div class="pt-2 flex items-center justify-center gap-3">
                <button 
                    x-on:click="$dispatch('close')" 
                    type="button" 
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold text-xs rounded-2xl transition cursor-pointer"
                >
                    {{ __('Cancel') }}
                </button>

                <button 
                    type="submit" 
                    class="px-6 py-3 bg-rose-600 hover:bg-rose-700 active:scale-95 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-rose-600/30 transition cursor-pointer flex items-center gap-2"
                >
                    <span wire:loading.remove wire:target="deleteUser">{{ __('Confirm Permanent Deletion') }}</span>
                    <span wire:loading wire:target="deleteUser" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ __('Deleting...') }}</span>
                    </span>
                </button>
            </div>
        </form>
    </x-modal>
</section>
