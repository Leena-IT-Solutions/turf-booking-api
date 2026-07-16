<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-6 text-center">
        <h2 class="text-xl font-extrabold text-slate-950 dark:text-white tracking-tight">Welcome Back</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Sign in to book your next match and manage your turfs</p>
    </div>

    <form wire:submit="login" class="space-y-4">
        <!-- Email or Mobile -->
        <div>
            <x-input-label for="login" :value="__('Email or Mobile')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="form.login" id="login" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm" type="text" name="login" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.login')" class="mt-1" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="form.password" id="password" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-1" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember" class="inline-flex items-center cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-slate-200 dark:border-slate-800 text-emerald-500 shadow-sm focus:ring-emerald-500 dark:focus:ring-emerald-400 dark:focus:ring-offset-slate-900 dark:bg-slate-950" name="remember">
                <span class="ms-2 text-xs text-slate-600 dark:text-slate-400 select-none font-medium">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition-colors focus:outline-none focus:underline" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-slate-900">
                {{ __('Log in') }}
            </button>
        </div>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
        Don't have an account? 
        <a href="{{ route('register') }}" class="font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition-colors focus:outline-none focus:underline" wire:navigate>
            Sign up
        </a>
    </div>
</div>
