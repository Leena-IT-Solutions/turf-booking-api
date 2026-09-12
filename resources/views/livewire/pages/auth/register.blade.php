<?php

use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public int $step = 1;
    public string $name = '';
    public string $email = '';
    public string $mobile = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $otp = '';
    public string $statusMessage = '';

    /**
     * Send WhatsApp OTP for registration.
     */
    public function sendOtp(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'mobile' => ['required', 'string', 'regex:/^[6-9]\d{9}$/', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $cleanMobile = preg_replace('/[^0-9]/', '', $this->mobile);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile;
        }

        $generatedOtp = strval(rand(100000, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'whatsapp_' . $cleanMobile],
            [
                'token' => Hash::make($generatedOtp),
                'created_at' => now()
            ]
        );

        $whatsAppService = new WhatsAppService();
        $whatsAppService->sendOtp($cleanMobile, $generatedOtp, 'registration');

        $this->step = 2;
        $this->statusMessage = "We've sent a 6-digit verification code to your WhatsApp number +91 " . $this->mobile . ".";
    }

    /**
     * Resend WhatsApp OTP.
     */
    public function resendOtp(): void
    {
        $cleanMobile = preg_replace('/[^0-9]/', '', $this->mobile);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile;
        }

        $generatedOtp = strval(rand(100000, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'whatsapp_' . $cleanMobile],
            [
                'token' => Hash::make($generatedOtp),
                'created_at' => now()
            ]
        );

        $whatsAppService = new WhatsAppService();
        $whatsAppService->sendOtp($cleanMobile, $generatedOtp, 'registration');

        $this->statusMessage = "A new 6-digit OTP code has been sent to your WhatsApp number.";
    }

    /**
     * Verify WhatsApp OTP and register the user.
     */
    public function verifyAndRegister(): void
    {
        $this->validate([
            'otp' => ['required', 'string', 'min:6', 'max:6'],
        ]);

        $cleanMobile = preg_replace('/[^0-9]/', '', $this->mobile);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile;
        }

        $reset = DB::table('password_reset_tokens')->where('email', 'whatsapp_' . $cleanMobile)->first();

        if (!$reset || !Hash::check($this->otp, $reset->token)) {
            $this->addError('otp', 'The OTP code entered is invalid.');
            return;
        }

        if (\Carbon\Carbon::parse($reset->created_at)->addMinutes(15)->isPast()) {
            $this->addError('otp', 'The OTP code has expired. Please click resend.');
            return;
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'password' => Hash::make($this->password),
        ]);

        $user->assignRole('customer');

        DB::table('password_reset_tokens')->where('email', 'whatsapp_' . $cleanMobile)->delete();

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    @if ($step === 1)
        <div class="mb-6 text-center">
            <h2 class="text-xl font-extrabold text-slate-950 tracking-tight">Create an Account</h2>
            <p class="text-xs text-slate-500 mt-1.5">Sign up to discover nearby turfs and book slots instantly</p>
        </div>

        <form wire:submit="sendOtp" class="space-y-4">
            <!-- Name -->
            <div>
                <x-input-label for="name" :value="__('Name')" class="text-slate-700 font-semibold text-xs mb-1" />
                <x-text-input wire:model="name" id="name" class="block w-full px-3.5 py-2.5 bg-slate-50/50 border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm" type="text" name="name" required autofocus autocomplete="name" placeholder="John Doe" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" class="text-slate-700 font-semibold text-xs mb-1" />
                <x-text-input wire:model="email" id="email" class="block w-full px-3.5 py-2.5 bg-slate-50/50 border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm" type="email" name="email" required autocomplete="username" placeholder="john@example.com" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <!-- Mobile Number -->
            <div>
                <x-input-label for="mobile" :value="__('WhatsApp Mobile Number')" class="text-slate-700 font-semibold text-xs mb-1" />
                <div class="relative flex items-center">
                    <span class="absolute left-3.5 text-xs font-bold text-slate-400">+91</span>
                    <x-text-input wire:model="mobile" id="mobile" class="block w-full pl-12 pr-3.5 py-2.5 bg-slate-50/50 border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm" type="text" name="mobile" required autocomplete="tel" inputmode="numeric" pattern="[6-9][0-9]{9}" maxlength="10" placeholder="9876543210" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)" />
                </div>
                <x-input-error :messages="$errors->get('mobile')" class="mt-1" />
            </div>

            <!-- Password -->
            <div>
                <x-input-label for="password" :value="__('Password')" class="text-slate-700 font-semibold text-xs mb-1" />
                <x-text-input wire:model="password" id="password" class="block w-full px-3.5 py-2.5 bg-slate-50/50 border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm"
                                type="password"
                                name="password"
                                required autocomplete="new-password" placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
            </div>

            <!-- Confirm Password -->
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="text-slate-700 font-semibold text-xs mb-1" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block w-full px-3.5 py-2.5 bg-slate-50/50 border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-sm"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    <svg class="w-4 h-4 fill-current" viewBox="0 0 24 24">
                        <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                    </svg>
                    {{ __('Verify Mobile via WhatsApp') }}
                </button>
            </div>
        </form>
    @else
        <!-- Step 2: WhatsApp OTP Verification -->
        <div class="mb-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 mb-3 shadow-inner">
                <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                </svg>
            </div>
            <h2 class="text-xl font-extrabold text-slate-950 tracking-tight">Verify WhatsApp OTP</h2>
            <p class="text-xs text-slate-500 mt-1.5 leading-relaxed">{{ $statusMessage }}</p>
        </div>

        <form wire:submit="verifyAndRegister" class="space-y-5">
            <!-- 6-Digit OTP Code -->
            <div>
                <x-input-label for="otp" :value="__('6-Digit Verification Code')" class="text-slate-700 font-semibold text-xs mb-1.5 text-center" />
                <x-text-input wire:model="otp" id="otp" class="block w-full px-4 py-3 bg-slate-50 border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 rounded-xl shadow-sm text-center font-mono text-xl tracking-[0.4em] font-bold text-slate-800" type="text" inputmode="numeric" maxlength="6" autofocus required placeholder="000000" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6)" />
                <x-input-error :messages="$errors->get('otp')" class="mt-2 text-center" />
            </div>

            <div class="space-y-2 pt-1">
                <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-3 bg-emerald-500 hover:bg-emerald-400 text-white font-bold uppercase tracking-wider text-xs rounded-xl shadow-md shadow-emerald-500/10 hover:shadow-emerald-500/20 active:scale-[0.98] transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500">
                    {{ __('Verify & Create Account') }}
                </button>

                <div class="flex items-center justify-between pt-2 text-xs">
                    <button type="button" wire:click="resendOtp" class="font-bold text-emerald-600 hover:text-emerald-500 transition-colors focus:outline-none">
                        {{ __('Resend Code') }}
                    </button>
                    <button type="button" wire:click="$set('step', 1)" class="font-medium text-slate-500 hover:text-slate-700 transition-colors focus:outline-none">
                        {{ __('Change Mobile Number') }}
                    </button>
                </div>
            </div>
        </form>
    @endif

    <div class="mt-6 text-center text-xs text-slate-500">
        Already registered? 
        <a class="font-bold text-emerald-600 hover:text-emerald-500 transition-colors focus:outline-none focus:underline" href="{{ route('login') }}" wire:navigate>
            {{ __('Log in') }}
        </a>
    </div>
</div>
