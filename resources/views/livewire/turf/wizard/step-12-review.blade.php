<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-gray-100">
        <div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                Step 12 of 12 · Final Review & Submission
            </span>
            <h3 class="text-lg font-black text-gray-900 mt-1.5">{{ __('Review Turf Setup & Submit for Verification') }}</h3>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Verify all onboarding items below. You can click Edit on any section to make changes before submitting.') }}</p>
        </div>

        @if ($currentTurf)
            <span class="px-3.5 py-1.5 rounded-full text-xs font-black uppercase tracking-wider {{ $currentTurf->status === 'Approved' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : ($currentTurf->status === 'Pending' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-100 text-slate-700 border border-slate-200') }}">
                {{ __('Current Status:') }} {{ $currentTurf->status }}
            </span>
        @endif
    </div>

    <!-- Customer-Facing Preview Card + Trial / Commission Summary -->
    @if ($currentTurf && $currentLocation)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <!-- Venue Preview Card -->
            <div class="lg:col-span-2 p-5 rounded-3xl border border-gray-200 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white flex flex-col sm:flex-row items-start sm:items-center gap-5 shadow-md">
                @php
                    $coverPhoto = $turfPhotos->where('is_active', true)->first();
                @endphp
                <div class="w-full sm:w-40 h-32 rounded-2xl overflow-hidden bg-white/10 shrink-0 border border-white/15">
                    @if ($coverPhoto)
                        <img src="{{ Storage::url($coverPhoto->photo) }}" alt="{{ $currentTurf->name }}" class="w-full h-full object-cover" />
                    @else
                        <div class="w-full h-full flex items-center justify-center text-xs text-slate-400">{{ __('No Cover Photo') }}</div>
                    @endif
                </div>
                <div class="space-y-1.5 min-w-0 flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                            {{ $currentTurf->type }}
                        </span>
                        @if ($currentTurf->area)
                            <span class="text-xs text-indigo-200 font-mono">{{ $currentTurf->area }}</span>
                        @endif
                    </div>
                    <h4 class="text-xl font-black tracking-tight truncate">{{ $currentTurf->name }}</h4>
                    <p class="text-xs text-slate-300 truncate">📍 {{ $currentLocation->name }} · {{ $currentLocation->address }}</p>
                    <div class="flex flex-wrap items-center gap-2 pt-1 text-[11px] text-indigo-200">
                        <span>🏅 {{ count($selectedSportIds) }} {{ __('Sports') }}</span>
                        <span>•</span>
                        <span>⏰ {{ count($selectedSlotIds) }} {{ __('Slots') }}</span>
                        <span>•</span>
                        <span>📸 {{ $turfPhotos->where('is_active', true)->count() }} {{ __('Photos') }}</span>
                    </div>
                </div>
            </div>

            <!-- Owner Trial & Commission Info -->
            @php
                $ownerUser = $currentTurf->owner() ?? auth()->user();
            @endphp
            <div class="p-5 rounded-3xl border border-emerald-200 bg-emerald-50/50 flex flex-col justify-between space-y-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">{{ __('Plan & Commission Status') }}</span>
                    @if ($ownerUser && $ownerUser->onTrial())
                        <div class="text-base font-black text-gray-900 mt-1">
                            🎉 {{ __('Free Trial Active (0% Commission)') }}
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5">
                            {{ $ownerUser->trialDaysLeft() }} {{ __('days remaining (ends') }} {{ $ownerUser->trial_ends_at->format('d M Y') }}).
                        </p>
                    @else
                        <div class="text-base font-black text-gray-900 mt-1">
                            {{ __('Platform Commission:') }} {{ number_format($currentTurf->commission_percentage, 2) }}%
                        </div>
                        <p class="text-xs text-gray-600 mt-0.5">{{ __('Applied on confirmed bookings.') }}</p>
                    @endif
                </div>
                <div class="text-[11px] text-gray-500 border-t border-emerald-200/60 pt-2">
                    {{ __('Owner:') }} <strong class="text-gray-800">{{ $ownerUser?->name }}</strong>
                </div>
            </div>
        </div>
    @endif

    <!-- 11-Item Verification Checklist Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        @foreach ($progress['steps'] as $stepNum => $stepMeta)
            @if ($stepNum < 12 && !$stepMeta['hidden'])
                <div class="p-4 rounded-2xl border {{ $stepMeta['completed'] ? 'border-emerald-200 bg-emerald-50/20' : ($stepMeta['required'] ? 'border-rose-200 bg-rose-50/30' : 'border-gray-200 bg-white') }} flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-8 w-8 rounded-xl flex items-center justify-center text-xs font-black shrink-0 {{ $stepMeta['completed'] ? 'bg-emerald-600 text-white' : ($stepMeta['required'] ? 'bg-rose-100 text-rose-700' : 'bg-gray-100 text-gray-600') }}">
                            @if ($stepMeta['completed'])
                                ✓
                            @else
                                {{ $stepNum }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-gray-900">{{ $stepMeta['title'] }}</span>
                                @if ($stepMeta['required'] && !$stepMeta['completed'])
                                    <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-rose-100 text-rose-700">Required</span>
                                @endif
                            </div>
                            <p class="text-[11px] text-gray-500 truncate mt-0.5">{{ $stepMeta['summary'] }}</p>
                        </div>
                    </div>

                    <button type="button"
                            wire:click="goToStep({{ $stepNum }})"
                            class="px-3 py-1.5 rounded-xl bg-white hover:bg-indigo-50 text-indigo-600 border border-gray-200 text-xs font-bold shrink-0 cursor-pointer">
                        {{ __('Edit') }}
                    </button>
                </div>
            @endif
        @endforeach
    </div>

    <!-- Submission Action Box (Role-Aware: Manager vs Owner / Impersonator) -->
    @if ($isManagerOnly)
        <div class="p-6 rounded-3xl bg-amber-50 border-2 border-amber-200 text-amber-900 space-y-2">
            <div class="text-sm font-black uppercase tracking-wider">🔒 {{ __('Setup Complete — Owner Submission Required') }}</div>
            <p class="text-xs leading-relaxed">
                {{ __('You are logged in as a Venue Manager. All your turf setup progress has been saved, but only the Turf Owner (or a TurfBooking Onboarding Executive) can submit this turf for final verification.') }}
            </p>
        </div>
    @else
        <div class="p-6 rounded-3xl bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 shadow-lg">
            <div class="space-y-1">
                <h4 class="text-base font-black tracking-tight">
                    @if ($currentTurf?->status === 'Approved')
                        {{ __('This Turf is Live & Approved!') }}
                    @elseif ($currentTurf?->status === 'Pending')
                        {{ __('This Turf is Currently Pending Verification') }}
                    @else
                        {{ __('Ready to Submit Your Turf for Verification?') }}
                    @endif
                </h4>
                <p class="text-xs text-emerald-100">
                    @if ($currentTurf?->status === 'Approved')
                        {{ __('Your updates are already saved and live for players.') }}
                    @else
                        {{ __('Submitting notifies our onboarding & verification team immediately via email.') }}
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                @if (session()->has('impersonator_id'))
                    <a href="{{ route('saas.impersonation.leave') }}"
                       class="px-4 py-3 rounded-2xl bg-white/15 hover:bg-white/25 text-white text-xs font-black uppercase tracking-wider border border-white/25 transition">
                        {{ __('Exit to Turfs Directory') }}
                    </a>
                @endif

                <button type="button"
                        wire:click="submitForVerification"
                        @disabled(!$progress['required_completed'])
                        class="px-6 py-3.5 rounded-2xl font-black text-xs uppercase tracking-wider shadow-md transition {{ $progress['required_completed'] ? 'bg-white text-emerald-800 hover:bg-emerald-50 cursor-pointer' : 'bg-white/40 text-white/70 cursor-not-allowed' }}">
                    @if ($currentTurf?->status === 'Approved')
                        ✓ {{ __('Save & Finish') }}
                    @elseif ($currentTurf?->status === 'Pending')
                        ✓ {{ __('Update Pending Submission') }}
                    @else
                        🚀 {{ __('Submit Turf for Verification') }}
                    @endif
                </button>
            </div>
        </div>
    @endif
</div>

