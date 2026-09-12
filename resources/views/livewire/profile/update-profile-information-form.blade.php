<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $mobile = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name;
        $this->email = $user->email;
        $this->mobile = $user->mobile ?? '';
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'mobile' => ['required', 'string', 'max:15', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section class="space-y-6">
    <!-- Header with Icon & Subtitle -->
    <div class="flex items-start justify-between gap-4 border-b border-gray-100 pb-5">
        <div class="space-y-1">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm font-bold shadow-xs">
                    👤
                </div>
                <h2 class="text-xl font-black text-gray-900 tracking-tight">
                    {{ __('Personal Information') }}
                </h2>
            </div>
            <p class="text-xs text-gray-500 leading-relaxed font-medium pl-10">
                {{ __("Manage your account's primary contact details, name, and registered mobile number.") }}
            </p>
        </div>
    </div>

    <form wire:submit="updateProfileInformation" class="space-y-6 max-w-2xl">
        <!-- Full Name Field -->
        <div class="space-y-1.5">
            <label for="name" class="block text-xs font-black uppercase tracking-wider text-gray-700">
                {{ __('Full Name') }} <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <input 
                    wire:model="name" 
                    id="name" 
                    name="name" 
                    type="text" 
                    class="block w-full pl-11 pr-4 py-3 text-xs sm:text-sm font-semibold text-gray-900 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 shadow-xs transition" 
                    required 
                    autofocus 
                    autocomplete="name" 
                    placeholder="Your Full Name"
                />
            </div>
            <x-input-error class="mt-1" :messages="$errors->get('name')" />
        </div>

        <!-- Email Address Field -->
        <div class="space-y-1.5">
            <label for="email" class="block text-xs font-black uppercase tracking-wider text-gray-700">
                {{ __('Email Address') }} <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>
                <input 
                    wire:model="email" 
                    id="email" 
                    name="email" 
                    type="email" 
                    class="block w-full pl-11 pr-4 py-3 text-xs sm:text-sm font-semibold text-gray-900 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 shadow-xs transition" 
                    required 
                    autocomplete="username" 
                    placeholder="you@example.com"
                />
            </div>
            <x-input-error class="mt-1" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-3 p-3.5 rounded-2xl bg-amber-50 border border-amber-200/80 text-amber-800 text-xs flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span>{{ __('Your email address is unverified.') }}</span>
                    </div>

                    <button wire:click.prevent="sendVerification" type="button" class="underline font-bold text-amber-900 hover:text-amber-700 cursor-pointer">
                        {{ __('Resend Link') }}
                    </button>
                </div>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 font-bold text-xs text-emerald-600 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ __('A new verification link has been sent to your email address.') }}</span>
                    </p>
                @endif
            @endif
        </div>

        <!-- Mobile Number Field -->
        <div class="space-y-1.5">
            <label for="mobile" class="block text-xs font-black uppercase tracking-wider text-gray-700">
                {{ __('Mobile Number') }} <span class="text-rose-500">*</span>
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                </div>
                <input 
                    wire:model="mobile" 
                    id="mobile" 
                    name="mobile" 
                    type="text" 
                    class="block w-full pl-11 pr-4 py-3 text-xs sm:text-sm font-semibold text-gray-900 rounded-2xl border border-gray-200 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10 shadow-xs transition" 
                    required 
                    autocomplete="tel" 
                    placeholder="9876543210"
                />
            </div>
            <x-input-error class="mt-1" :messages="$errors->get('mobile')" />
        </div>

        <!-- Action Buttons & Toast -->
        <div class="flex items-center gap-4 pt-4">
            <button 
                type="submit" 
                class="px-8 py-3.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-black text-xs uppercase tracking-wider rounded-2xl shadow-lg shadow-indigo-600/25 hover:shadow-indigo-600/35 transition duration-200 flex items-center gap-2 cursor-pointer"
            >
                <span wire:loading.remove wire:target="updateProfileInformation">{{ __('Save Changes') }}</span>
                <span wire:loading wire:target="updateProfileInformation" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span>{{ __('Saving...') }}</span>
                </span>
            </button>

            <x-action-message class="text-xs font-bold text-emerald-600 inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200/80" on="profile-updated">
                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                <span>{{ __('Saved successfully.') }}</span>
            </x-action-message>
        </div>
    </form>
</section>
