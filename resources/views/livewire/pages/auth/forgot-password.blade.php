<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-xl font-extrabold text-slate-950 dark:text-white tracking-tight">Reset Password</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
            No problem. Enter your email address and we'll send you a password reset link to choose a new one.
        </p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="space-y-4">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="email" id="email" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm" type="email" name="email" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-slate-900">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
        Remember your password? 
        <a href="{{ route('login') }}" class="font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition-colors focus:outline-none focus:underline" wire:navigate>
            Back to Login
        </a>
    </div>
</div>
