<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-xl font-extrabold text-slate-950 dark:text-white tracking-tight">Confirm Password</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
            This is a secure area of the application. Please confirm your password before continuing.
        </p>
    </div>

    <form wire:submit="confirmPassword" class="space-y-4">
        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="password"
                          id="password"
                          class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm"
                          type="password"
                          name="password"
                          required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-slate-900">
                {{ __('Confirm') }}
            </button>
        </div>
    </form>
</div>
