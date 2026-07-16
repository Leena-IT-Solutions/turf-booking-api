<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $mobile = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'mobile' => ['required', 'string', 'max:20', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        $user->assignRole('customer');

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-xl font-extrabold text-slate-950 dark:text-white tracking-tight">Create an Account</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Sign up to discover nearby turfs and book slots instantly</p>
    </div>

    <form wire:submit="register" class="space-y-4">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="name" id="name" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="email" id="email" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <!-- Mobile Number -->
        <div>
            <x-input-label for="mobile" :value="__('Mobile')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="mobile" id="mobile" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm" type="text" name="mobile" required autocomplete="tel" />
            <x-input-error :messages="$errors->get('mobile')" class="mt-1" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="__('Password')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="password" id="password" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="text-slate-700 dark:text-slate-350 font-semibold text-xs mb-1" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block w-full px-3.5 py-2.5 bg-slate-50/50 dark:bg-slate-950/50 border-slate-200 dark:border-slate-800 focus:border-emerald-500 focus:ring-emerald-500 dark:focus:border-emerald-400 dark:focus:ring-emerald-400 rounded-xl shadow-sm text-sm"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
        </div>

        <div class="pt-2">
            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-slate-900">
                {{ __('Register') }}
            </button>
        </div>
    </form>

    <div class="mt-6 text-center text-xs text-slate-500 dark:text-slate-400">
        Already registered? 
        <a class="font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-500 dark:hover:text-emerald-300 transition-colors focus:outline-none focus:underline" href="{{ route('login') }}" wire:navigate>
            {{ __('Log in') }}
        </a>
    </div>
</div>
