<?php

use App\Models\Turf;
use App\Models\TurfQrScan;
use App\Services\TurfQrCodeService;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $showRegenerateModal = false;

    #[On('global-context-updated')]
    public function refreshQrContext(): void
    {
        // Re-render when active turf changes in top bar
    }

    public function openRegenerateModal(): void
    {
        if (!auth()->user()?->hasRole('turf-admin') && !auth()->user()?->hasRole('saas-admin')) {
            abort(403, 'Only the Turf Owner or SaaS Admin can regenerate a QR code.');
        }
        $this->showRegenerateModal = true;
    }

    public function closeRegenerateModal(): void
    {
        $this->showRegenerateModal = false;
    }

    public function regenerateQrCode(): void
    {
        if (!auth()->user()?->hasRole('turf-admin') && !auth()->user()?->hasRole('saas-admin')) {
            abort(403, 'Only the Turf Owner or SaaS Admin can regenerate a QR code.');
        }

        $activeTurfId = session('active_turf_id');
        if (!$activeTurfId) {
            return;
        }

        $turf = Turf::manageable()->findOrFail($activeTurfId);
        $newCode = $turf->regenerateQrCode();

        $this->showRegenerateModal = false;
        session()->flash('status', "QR Code regenerated (#{$newCode}). Previous printed QR codes for {$turf->name} are now deactivated.");
    }

    public function with(): array
    {
        $activeTurfId = session('active_turf_id');
        $turf = null;

        if ($activeTurfId) {
            $turf = Turf::manageable()->with('location')->find($activeTurfId);
        }
        if (!$turf) {
            $turf = Turf::manageable()->with('location')->first();
            if ($turf) {
                session([
                    'active_location_id' => $turf->location_id,
                    'active_turf_id' => $turf->id,
                ]);
            }
        }

        $qrDataUri = null;
        $qrUrl = null;
        $stats = [
            'total_scans' => 0,
            'month_scans' => 0,
            'android_scans' => 0,
            'ios_scans' => 0,
            'app_opens' => 0,
            'conversions' => 0,
        ];

        if ($turf) {
            $turf->ensureQrCode();
            $qrDataUri = TurfQrCodeService::pngDataUri($turf, 640);
            $qrUrl = $turf->qrUrl();

            $monthStart = Carbon::now('Asia/Kolkata')->startOfMonth();
            $stats = [
                'total_scans' => TurfQrScan::where('turf_id', $turf->id)->count(),
                'month_scans' => TurfQrScan::where('turf_id', $turf->id)->where('created_at', '>=', $monthStart)->count(),
                'android_scans' => TurfQrScan::where('turf_id', $turf->id)->where('platform', 'android')->count(),
                'ios_scans' => TurfQrScan::where('turf_id', $turf->id)->where('platform', 'ios')->count(),
                'app_opens' => TurfQrScan::where('turf_id', $turf->id)->where('source', 'app')->count(),
                'conversions' => TurfQrScan::where('turf_id', $turf->id)->whereNotNull('converted_user_id')->distinct('converted_user_id')->count('converted_user_id'),
            ];
        }

        $canRegenerate = auth()->user()?->hasRole('turf-admin') || auth()->user()?->hasRole('saas-admin');

        return [
            'turf' => $turf,
            'qrDataUri' => $qrDataUri,
            'qrUrl' => $qrUrl,
            'stats' => $stats,
            'canRegenerate' => $canRegenerate,
        ];
    }
}; ?>

<div class="w-full space-y-6">
    @if (session('status'))
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-3.5 rounded-2xl text-xs font-bold flex items-center gap-2.5 shadow-2xs">
            <span>✓</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if (!$turf)
        <div class="bg-white p-12 rounded-3xl border border-gray-100 shadow-xs text-center space-y-3">
            <div class="h-16 w-16 rounded-2xl bg-amber-50 text-amber-500 flex items-center justify-center mx-auto border border-amber-100">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                </svg>
            </div>
            <h3 class="text-lg font-black text-gray-900">{{ __('No Turf Selected') }}</h3>
            <p class="text-xs text-gray-500 max-w-md mx-auto">
                {{ __('Create your Location and Turf first using the Setup Wizard to generate your branded booking QR code.') }}
            </p>
            <a href="{{ route('turf.setup-wizard') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-wider">
                <span>✨ Open Setup Wizard</span>
            </a>
        </div>
    @else
        <!-- Top Header Card -->
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xs flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <h2 class="text-xl font-black text-gray-900">{{ __('Turf Deep-Link QR Code') }}</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-black bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                        #{{ $turf->qr_code }}
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase {{ $turf->status === 'Approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-800 border border-amber-200' }}">
                        {{ $turf->status }}
                    </span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('Players who scan this QR code open') }} <strong class="text-indigo-600">{{ $turf->name }}</strong> {{ __('directly inside the TurfBooking app (or are guided to install & register first).') }}
                </p>
            </div>

            @if ($canRegenerate)
                <button type="button"
                        wire:click="openRegenerateModal"
                        class="px-4 py-2.5 rounded-xl bg-gray-100 hover:bg-rose-50 text-gray-700 hover:text-rose-700 border border-gray-200 hover:border-rose-200 text-xs font-bold transition cursor-pointer shrink-0">
                    🔄 {{ __('Regenerate QR Code') }}
                </button>
            @endif
        </div>

        @if ($turf->status !== 'Approved')
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs font-semibold flex items-center justify-between gap-3">
                <span>⚠️ {{ __('This turf is currently :status. You can download and print your QR code now—live slot bookings will activate automatically once verified.', ['status' => $turf->status]) }}</span>
            </div>
        @endif

        <!-- Scan & Conversion Analytics Strip -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5">
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400 block">{{ __('Total Scans') }}</span>
                <span class="text-2xl font-black text-gray-900 font-mono mt-1 block">{{ number_format($stats['total_scans']) }}</span>
                <span class="text-[10px] text-gray-400">{{ __('All-time QR hits') }}</span>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-500 block">{{ __('This Month') }}</span>
                <span class="text-2xl font-black text-indigo-700 font-mono mt-1 block">{{ number_format($stats['month_scans']) }}</span>
                <span class="text-[10px] text-gray-400">{{ now('Asia/Kolkata')->format('F Y') }}</span>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 block">{{ __('Android Scans') }}</span>
                <span class="text-2xl font-black text-gray-900 font-mono mt-1 block">{{ number_format($stats['android_scans']) }}</span>
                <span class="text-[10px] text-gray-400">{{ __('Google Play devices') }}</span>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-sky-600 block">{{ __('iOS Scans') }}</span>
                <span class="text-2xl font-black text-gray-900 font-mono mt-1 block">{{ number_format($stats['ios_scans']) }}</span>
                <span class="text-[10px] text-gray-400">{{ __('iPhone / iPad') }}</span>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-violet-600 block">{{ __('Opened in App') }}</span>
                <span class="text-2xl font-black text-violet-700 font-mono mt-1 block">{{ number_format($stats['app_opens']) }}</span>
                <span class="text-[10px] text-gray-400">{{ __('Direct deep-links') }}</span>
            </div>
            <div class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs">
                <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 block">{{ __('App Conversions') }}</span>
                <span class="text-2xl font-black text-amber-700 font-mono mt-1 block">{{ number_format($stats['conversions']) }}</span>
                <span class="text-[10px] text-gray-400">{{ __('Registered / logged in') }}</span>
            </div>
        </div>

        <!-- Main Grid: Left Live Branded QR Card | Right Download & Print Formats -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Live Branded QR Preview Card -->
            <div class="lg:col-span-5 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-3xl p-6 sm:p-8 shadow-lg flex flex-col items-center text-center justify-between space-y-5">
                <div class="space-y-1.5">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                        Official TurfBooking QR
                    </span>
                    <h3 class="text-xl font-black tracking-tight">{{ $turf->name }}</h3>
                    <p class="text-xs text-slate-300">{{ $turf->location?->name }}</p>
                </div>

                <!-- QR Image with Embedded TurfBooking Logo -->
                <div class="bg-white p-4 rounded-3xl shadow-2xl border-4 border-emerald-400">
                    <img src="{{ $qrDataUri }}" alt="Turf QR Code with Logo" class="w-60 h-60 sm:w-64 sm:h-64 object-contain mx-auto" />
                </div>

                <!-- Deep-Link URL & Copy / WhatsApp Share -->
                <div class="w-full space-y-3" x-data="{ copied: false }">
                    <div class="flex items-center gap-2 bg-white/10 border border-white/15 rounded-2xl px-3.5 py-2.5">
                        <input type="text" readonly value="{{ $qrUrl }}" class="w-full bg-transparent text-xs font-mono text-emerald-300 focus:outline-none truncate" />
                        <button type="button"
                                @click="navigator.clipboard.writeText(@js($qrUrl)); copied = true; setTimeout(() => copied = false, 2000)"
                                class="px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-[11px] font-black uppercase tracking-wider shrink-0 cursor-pointer">
                            <span x-text="copied ? 'Copied!' : 'Copy Link'">Copy Link</span>
                        </button>
                    </div>

                    <div class="flex items-center justify-center gap-2.5">
                        <a href="https://wa.me/?text={{ rawurlencode('Book ' . $turf->name . ' (' . ($turf->location?->name ?? '') . ') directly on TurfBooking: ' . $qrUrl) }}"
                           target="_blank"
                           class="flex-1 py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black uppercase tracking-wider text-center transition">
                            📲 {{ __('Share Link on WhatsApp') }}
                        </a>
                        <a href="{{ route('turf.qr.landing', $turf->qr_code) }}"
                           target="_blank"
                           class="py-2.5 px-4 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold border border-white/15 transition">
                            {{ __('Preview Page ↗') }}
                        </a>
                    </div>
                </div>
            </div>

            <!-- Right: Download Options (PNG, SVG, and A3 / A4 / A5 Printable Standee Posters) -->
            <div class="lg:col-span-7 space-y-6">
                <!-- Raw QR Image Downloads -->
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
                    <div>
                        <h3 class="text-base font-black text-gray-900">{{ __('1. Download Standalone QR Code Image (With Logo)') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Includes the TurfBooking logo centered inside the QR code with high error-correction (Level H) so it scans cleanly everywhere.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <a href="{{ route('turf.qr.download.png', $turf->id) }}"
                           class="p-4 rounded-2xl border-2 border-indigo-100 hover:border-indigo-600 bg-indigo-50/30 hover:bg-indigo-50/70 transition flex items-center justify-between gap-3 group">
                            <div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-indigo-600 text-white">PNG · 1024 × 1024 px</span>
                                <h4 class="text-sm font-black text-gray-900 mt-1.5">{{ __('High-Res PNG Image') }}</h4>
                                <p class="text-[11px] text-gray-500">{{ __('Best for WhatsApp, Instagram & social posts') }}</p>
                            </div>
                            <span class="h-10 w-10 rounded-xl bg-white text-indigo-600 flex items-center justify-center shadow-xs border border-indigo-100 group-hover:bg-indigo-600 group-hover:text-white transition font-bold">
                                ↓
                            </span>
                        </a>

                        <a href="{{ route('turf.qr.download.svg', $turf->id) }}"
                           class="p-4 rounded-2xl border-2 border-emerald-100 hover:border-emerald-600 bg-emerald-50/30 hover:bg-emerald-50/70 transition flex items-center justify-between gap-3 group">
                            <div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-600 text-white">SVG · Vector</span>
                                <h4 class="text-sm font-black text-gray-900 mt-1.5">{{ __('Scalable Vector SVG') }}</h4>
                                <p class="text-[11px] text-gray-500">{{ __('Best for graphic designers & large flex banners') }}</p>
                            </div>
                            <span class="h-10 w-10 rounded-xl bg-white text-emerald-600 flex items-center justify-center shadow-xs border border-emerald-100 group-hover:bg-emerald-600 group-hover:text-white transition font-bold">
                                ↓
                            </span>
                        </a>
                    </div>
                </div>

                <!-- Printable Poster PDFs (A3, A4, A5) -->
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-xs space-y-4">
                    <div>
                        <h3 class="text-base font-black text-gray-900">{{ __('2. Download Ready-to-Print Venue Poster / Standee PDF') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Complete branded poster with TurfBooking logo, venue name, address, 3-step scan instructions, and QR code scaled for each paper size.') }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- A3 Poster -->
                        <a href="{{ route('turf.qr.download.poster', ['turf' => $turf->id, 'size' => 'a3']) }}"
                           class="p-4 rounded-2xl border-2 border-gray-200 hover:border-indigo-600 bg-white hover:bg-indigo-50/20 transition flex flex-col justify-between gap-3">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-purple-100 text-purple-800">A3 Size</span>
                                    <span class="text-[10px] font-mono text-gray-400">297 × 420 mm</span>
                                </div>
                                <h4 class="text-sm font-black text-gray-900 mt-2">{{ __('Large Wall / Gate Poster') }}</h4>
                                <p class="text-[11px] text-gray-500 mt-1">{{ __('Ideal for turf entrance gate, box-cricket net exterior, or reception wall.') }}</p>
                            </div>
                            <div class="pt-2 border-t border-gray-100 text-xs font-black text-indigo-600 flex items-center justify-between">
                                <span>{{ __('Download A3 PDF') }}</span>
                                <span>↓</span>
                            </div>
                        </a>

                        <!-- A4 Poster -->
                        <a href="{{ route('turf.qr.download.poster', ['turf' => $turf->id, 'size' => 'a4']) }}"
                           class="p-4 rounded-2xl border-2 border-indigo-600 bg-indigo-50/20 hover:bg-indigo-50/50 transition flex flex-col justify-between gap-3">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-indigo-600 text-white">A4 Size · Popular</span>
                                    <span class="text-[10px] font-mono text-gray-500">210 × 297 mm</span>
                                </div>
                                <h4 class="text-sm font-black text-gray-900 mt-2">{{ __('Standard Counter Poster') }}</h4>
                                <p class="text-[11px] text-gray-500 mt-1">{{ __('Fits any standard office printer or acrylic wall frame at the venue.') }}</p>
                            </div>
                            <div class="pt-2 border-t border-indigo-100 text-xs font-black text-indigo-700 flex items-center justify-between">
                                <span>{{ __('Download A4 PDF') }}</span>
                                <span>↓</span>
                            </div>
                        </a>

                        <!-- A5 Standee -->
                        <a href="{{ route('turf.qr.download.poster', ['turf' => $turf->id, 'size' => 'a5']) }}"
                           class="p-4 rounded-2xl border-2 border-gray-200 hover:border-emerald-600 bg-white hover:bg-emerald-50/20 transition flex flex-col justify-between gap-3">
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 text-emerald-800">A5 Size</span>
                                    <span class="text-[10px] font-mono text-gray-400">148 × 210 mm</span>
                                </div>
                                <h4 class="text-sm font-black text-gray-900 mt-2">{{ __('Table Standee / Tent Card') }}</h4>
                                <p class="text-[11px] text-gray-500 mt-1">{{ __('Compact size for cash counter acrylic table standees.') }}</p>
                            </div>
                            <div class="pt-2 border-t border-gray-100 text-xs font-black text-emerald-700 flex items-center justify-between">
                                <span>{{ __('Download A5 PDF') }}</span>
                                <span>↓</span>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- How Deep-Linking Works Info Box -->
                <div class="bg-gray-50 p-5 rounded-3xl border border-gray-200/80 space-y-2.5">
                    <div class="text-xs font-black uppercase tracking-wider text-gray-700">📱 {{ __('How Player Scanning Works') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-600">
                        <div class="bg-white p-3.5 rounded-2xl border border-gray-100">
                            <strong class="text-gray-900 block mb-0.5">1. Has App & Logged In</strong>
                            Opens <strong>{{ $turf->name }}</strong> directly inside the TurfBooking app.
                        </div>
                        <div class="bg-white p-3.5 rounded-2xl border border-gray-100">
                            <strong class="text-gray-900 block mb-0.5">2. Has App (Not Logged In)</strong>
                            Opens the Register/Login screen first, then automatically lands on <strong>{{ $turf->name }}</strong>.
                        </div>
                        <div class="bg-white p-3.5 rounded-2xl border border-gray-100">
                            <strong class="text-gray-900 block mb-0.5">3. No App Installed</strong>
                            Opens Google Play / App Store to install the app, then lands on <strong>{{ $turf->name }}</strong> after registration.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Regenerate QR Code Confirmation Modal -->
    @if ($showRegenerateModal && $turf)
        <div class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-950/60 backdrop-blur-sm transition-opacity" wire:click="closeRegenerateModal"></div>

            <div class="bg-white rounded-3xl overflow-hidden shadow-xl transform transition-all w-full max-w-md z-50 border border-gray-100 p-6 sm:p-8 space-y-5">
                <div class="flex items-center gap-3.5 text-rose-600">
                    <div class="h-12 w-12 rounded-2xl bg-rose-50 flex items-center justify-center border border-rose-100 shrink-0 font-black text-lg">
                        🔄
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">{{ __('Regenerate QR Code?') }}</h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ __('Current code: #') }}{{ $turf->qr_code }}</p>
                    </div>
                </div>

                <p class="text-xs text-gray-600 leading-relaxed">
                    {{ __('Generating a new QR code will immediately deactivate the current link (:url). Any previously printed posters or standees at the venue will need to be reprinted.', ['url' => $qrUrl]) }}
                </p>

                <div class="flex justify-end gap-3 pt-3 border-t border-gray-100">
                    <button type="button" wire:click="closeRegenerateModal" class="px-4 py-2.5 border border-gray-200 hover:bg-gray-50 rounded-xl font-bold text-xs uppercase text-gray-700 cursor-pointer">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" wire:click="regenerateQrCode" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-sm cursor-pointer">
                        {{ __('Yes, Regenerate QR') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
