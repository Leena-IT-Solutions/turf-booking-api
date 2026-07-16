<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="mb-6 text-center">
        <h2 class="text-xl font-extrabold text-slate-950 dark:text-white tracking-tight">Verify Your Email</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 leading-relaxed">
            Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we'll gladly send you another.
        </p>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-5 p-3.5 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-100 dark:border-emerald-900/30 rounded-xl text-xs text-emerald-800 dark:text-emerald-400 font-medium">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="space-y-4">
        <div>
            <button wire:click="sendVerification" type="button" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white dark:text-slate-950 font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 dark:focus:ring-offset-slate-900">
                {{ __('Resend Verification Email') }}
            </button>
        </div>

        <div class="text-center">
            <button wire:click="logout" type="button" class="text-xs font-bold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 transition-colors focus:outline-none focus:underline">
                {{ __('Log Out') }}
            </button>
        </div>
    </div>
</div>
