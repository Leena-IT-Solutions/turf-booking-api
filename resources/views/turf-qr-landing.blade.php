<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $turf->name }} — Book on TurfBooking</title>

    @php
        $coverPhoto = $turf->photos->where('is_active', true)->first();
        $coverUrl = $coverPhoto ? asset('storage/' . $coverPhoto->photo) : asset('turf-logo.png');
        $locationName = $turf->location?->name ?? 'Sports Venue';
        $locationAddress = $turf->location?->address ?? '';
        $sportsList = $turf->sports->pluck('name')->implode(', ');
    @endphp

    <!-- Open Graph / WhatsApp Rich Link Preview -->
    <meta property="og:type" content="website" />
    <meta property="og:title" content="Book {{ $turf->name }} ({{ $locationName }}) on TurfBooking" />
    <meta property="og:description" content="{{ $locationAddress }}{{ $sportsList ? ' • Sports: ' . $sportsList : '' }} — Scan or tap to book your slot instantly!" />
    <meta property="og:image" content="{{ $coverUrl }}" />
    <meta property="og:url" content="{{ $turf->qrUrl() }}" />

    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white font-sans antialiased flex flex-col justify-between p-4 sm:p-6">

    <!-- Top Brand Header -->
    <header class="max-w-lg w-full mx-auto flex items-center justify-between py-2">
        <a href="{{ url('/') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('turf-logo.png') }}" alt="TurfBooking" class="h-9 w-9 rounded-xl bg-white p-1 shadow" />
            <div>
                <span class="font-black text-base tracking-tight text-white block leading-none">TurfBooking</span>
                <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-400">Instant Court Booking</span>
            </div>
        </a>
        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/10 text-emerald-300 border border-white/15">
            QR Code #{{ $turf->qr_code }}
        </span>
    </header>

    <!-- Center Smart Turf Card -->
    <main class="max-w-lg w-full mx-auto my-auto py-4">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
            <!-- Cover Photo -->
            <div class="relative h-52 bg-slate-800">
                @if ($coverPhoto)
                    <img src="{{ $coverUrl }}" alt="{{ $turf->name }}" class="w-full h-full object-cover" />
                @else
                    <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-emerald-900/50 to-indigo-950">
                        <img src="{{ asset('turf-logo.png') }}" alt="TurfBooking" class="h-16 w-16 rounded-2xl bg-white p-2 shadow-lg mb-2" />
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/30 to-transparent"></div>

                <div class="absolute top-3.5 left-3.5 flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow">
                        {{ $turf->type }}
                    </span>
                    @if ($turf->area)
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-black/60 backdrop-blur-md text-white border border-white/15">
                            {{ $turf->area }}
                        </span>
                    @endif
                </div>

                <div class="absolute bottom-3.5 left-4 right-4">
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-400">{{ $locationName }}</p>
                    <h1 class="text-2xl font-black text-white tracking-tight leading-tight">{{ $turf->name }}</h1>
                </div>
            </div>

            <!-- Venue Details & Deep-Link CTAs -->
            <div class="p-6 space-y-5">
                @if ($turf->status !== 'Approved')
                    <div class="p-3.5 rounded-2xl bg-amber-500/15 border border-amber-400/30 text-amber-200 text-xs font-semibold">
                        ⏳ This turf is currently being onboarded and will be open for live slot bookings shortly.
                    </div>
                @endif

                @if ($locationAddress)
                    <div class="flex items-start gap-2.5 text-xs text-slate-300">
                        <span class="text-base leading-none">📍</span>
                        <span>{{ $locationAddress }}</span>
                    </div>
                @endif

                @if ($turf->sports->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($turf->sports as $sport)
                            <span class="px-2.5 py-1 rounded-xl text-[11px] font-bold bg-white/5 text-slate-200 border border-white/10">
                                🏅 {{ $sport->name }}
                            </span>
                        @endforeach
                    </div>
                @endif

                <!-- Primary Action: Open in App -->
                <div class="space-y-3 pt-2">
                    <a id="openInAppBtn"
                       href="{{ $appDeepLink }}"
                       class="w-full py-3.5 px-5 rounded-2xl bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-emerald-500/20 flex items-center justify-center gap-2 transition">
                        <span>⚡ Open {{ $turf->name }} in App</span>
                    </a>

                    <div class="relative flex py-1 items-center">
                        <div class="flex-grow border-t border-slate-800"></div>
                        <span class="flex-shrink mx-3 text-[10px] font-bold uppercase tracking-widest text-slate-500">Don't have the app yet?</span>
                        <div class="flex-grow border-t border-slate-800"></div>
                    </div>

                    <p class="text-[11px] text-center text-slate-400">
                        Download the <strong>TurfBooking</strong> app below — after you sign up or log in, you'll land directly on <strong>{{ $turf->name }}</strong>!
                    </p>

                    <!-- Store Buttons (Android Play Store with Referrer + iOS App Store with Clipboard Token) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="{{ $playStoreUrl }}"
                           onclick="copyDeferredToken()"
                           class="py-3 px-4 rounded-2xl border {{ $platform === 'android' ? 'border-emerald-500 bg-emerald-500/15 text-white' : 'border-slate-700 bg-slate-800/80 text-slate-200' }} hover:bg-slate-800 flex items-center justify-center gap-2.5 text-xs font-black transition">
                            <span>🤖 Google Play Store</span>
                        </a>

                        <a href="{{ $appStoreUrl }}"
                           onclick="copyDeferredToken()"
                           class="py-3 px-4 rounded-2xl border {{ $platform === 'ios' ? 'border-emerald-500 bg-emerald-500/15 text-white' : 'border-slate-700 bg-slate-800/80 text-slate-200' }} hover:bg-slate-800 flex items-center justify-center gap-2.5 text-xs font-black transition">
                            <span>🍎 Apple App Store</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="max-w-lg w-full mx-auto text-center py-3 text-[11px] text-slate-500">
        &copy; {{ date('Y') }} {{ config('app.name', 'TurfBooking') }} &bull; Scan QR at venue to book slots online
    </footer>

    <script>
        function copyDeferredToken() {
            const token = 'TURFBOOKING:{{ $turf->qr_code }}';
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(token).catch(() => {});
            }
        }

        // On Android / iOS mobile browsers, automatically attempt to launch the installed app
        document.addEventListener('DOMContentLoaded', function () {
            const platform = @json($platform);
            if (platform === 'android' || platform === 'ios') {
                setTimeout(function () {
                    window.location.href = @json($appDeepLink);
                }, 250);
            }
        });
    </script>
</body>
</html>
