<?php

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Turf;
use App\Models\Slot;
use App\Models\Facility;
use App\Models\Equipment;
use App\Models\Sport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[On('global-context-updated')]
    public function refreshContext()
    {
        // Re-renders when active location or turf changes in top header
    }

    public function with()
    {
        $userId = auth()->id();

        // 1. Locations
        $locations = Location::manageable()->get();
        $locationsCount = $locations->count();
        $locationIds = $locations->pluck('id');

        // 2. Turfs
        $turfs = Turf::manageable()->get();
        $turfsCount = $turfs->count();
        $turfIds = $turfs->pluck('id');

        // 3. Active Slots (linked to turfs)
        $activeSlotsCount = DB::table('slot_turf')
            ->whereIn('turf_id', $turfIds)
            ->where('is_active', true)
            ->distinct('slot_id')
            ->count();

        // 4. Facilities
        $facilitiesCount = DB::table('facility_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('facility_id')
            ->count();

        // 5. Equipments
        $equipmentsCount = DB::table('equipment_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('equipment_id')
            ->count();

        // 6. Sports
        $sportsCount = DB::table('sport_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('sport_id')
            ->count();

        $activeTurfId = session('active_turf_id');
        $activeTurf = $activeTurfId ? Turf::manageable()->find($activeTurfId) : null;
        if (!$activeTurf) {
            $activeTurf = Turf::manageable()->first();
        }
        $missingGstState = $activeTurf ? (\App\Services\GstStateResolver::turfStateCode($activeTurf) === null) : false;

        // === TODAY AT A GLANCE (LIVE OPERATIONS) ===
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $todayDayOfWeek = strtolower($now->format('D')); // 'mon', 'tue', ...

        $manageableTurfIds = $turfIds->toArray();
        if ($activeTurfId && in_array($activeTurfId, $manageableTurfIds)) {
            $scopedTurfIds = [$activeTurfId];
            $selectedTurf = $turfs->firstWhere('id', $activeTurfId);
            $scopedVenueName = $selectedTurf ? $selectedTurf->name : 'Active Turf';
        } else {
            $scopedTurfIds = $manageableTurfIds;
            $scopedVenueName = 'All Venues';
        }

        // Today's Booking Sessions
        $todayDates = BookingDate::where('booking_date', $today)
            ->whereHas('booking', function ($q) use ($scopedTurfIds) {
                $q->whereIn('turf_id', $scopedTurfIds);
            })
            ->with(['booking.user', 'booking.turf', 'bookingSlots.slot.category'])
            ->get();

        $todayConfirmed = $todayDates->where('status', 'Confirmed');
        $todayBookingsCount = $todayConfirmed->count();
        $todayRevenue = (float) $todayConfirmed->sum('paid_amount');
        $todayTotalAmount = (float) $todayConfirmed->sum('amount');
        $todayBalance = (float) $todayConfirmed->sum('balance_amount');

        // Booked Slots Count Today
        $todayBookedSlotsCount = 0;
        foreach ($todayConfirmed as $bDate) {
            $todayBookedSlotsCount += $bDate->bookingSlots->count();
        }

        // Scheduled Slots Today for Scoped Turfs
        $totalScheduledSlotsToday = DB::table('slot_turf')
            ->whereIn('turf_id', $scopedTurfIds)
            ->where('is_active', true)
            ->where($todayDayOfWeek, true)
            ->count();

        if ($totalScheduledSlotsToday === 0) {
            $totalScheduledSlotsToday = DB::table('slot_turf')
                ->whereIn('turf_id', $scopedTurfIds)
                ->where('is_active', true)
                ->count();
        }

        // Occupancy Rate
        $occupancyRate = ($totalScheduledSlotsToday > 0)
            ? min(100, (int) round(($todayBookedSlotsCount / $totalScheduledSlotsToday) * 100))
            : ($todayBookedSlotsCount > 0 ? 100 : 0);

        // Next / In-Progress Match
        $activeOrUpcomingSlot = null;
        $slotStatus = null; // 'live' or 'upcoming'

        $sortedSessions = $todayConfirmed->map(function ($bDate) {
            $earliest = null;
            $latest = null;
            foreach ($bDate->bookingSlots as $bs) {
                if ($bs->slot) {
                    if ($earliest === null || $bs->slot->from_time < $earliest) {
                        $earliest = $bs->slot->from_time;
                    }
                    if ($latest === null || $bs->slot->to_time > $latest) {
                        $latest = $bs->slot->to_time;
                    }
                }
            }
            $bDate->earliest_from = $earliest;
            $bDate->latest_to = $latest;
            return $bDate;
        })->sortBy('earliest_from');

        $liveSession = $sortedSessions->first(function ($s) use ($currentTime) {
            return $s->earliest_from && $s->latest_to && $s->earliest_from <= $currentTime && $s->latest_to >= $currentTime;
        });

        if ($liveSession) {
            $activeOrUpcomingSlot = $liveSession;
            $slotStatus = 'live';
        } else {
            $upcomingSession = $sortedSessions->first(function ($s) use ($currentTime) {
                return $s->earliest_from && $s->earliest_from > $currentTime;
            });
            if ($upcomingSession) {
                $activeOrUpcomingSlot = $upcomingSession;
                $slotStatus = 'upcoming';
            }
        }

        return [
            'locationsCount' => $locationsCount,
            'turfsCount' => $turfsCount,
            'activeSlotsCount' => $activeSlotsCount,
            'facilitiesCount' => $facilitiesCount,
            'equipmentsCount' => $equipmentsCount,
            'sportsCount' => $sportsCount,
            'locations' => $locations->load('turfs'),
            'missingGstState' => $missingGstState,
            'todayDateFormatted' => $now->format('l, d M Y'),
            'scopedVenueName' => $scopedVenueName,
            'todayBookingsCount' => $todayBookingsCount,
            'todayRevenue' => $todayRevenue,
            'todayTotalAmount' => $todayTotalAmount,
            'todayBalance' => $todayBalance,
            'todayBookedSlotsCount' => $todayBookedSlotsCount,
            'totalScheduledSlotsToday' => $totalScheduledSlotsToday,
            'occupancyRate' => $occupancyRate,
            'activeOrUpcomingSlot' => $activeOrUpcomingSlot,
            'slotStatus' => $slotStatus,
        ];
    }
}; ?>

<div class="w-full space-y-6">

        @if ($missingGstState)
            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-5 py-4 rounded-2xl text-xs font-semibold flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0 text-base">
                        ⚠️
                    </div>
                    <span>{{ __("Your turf's state is not set. Set it on the Taxation page so GST on your bookings and commission is charged correctly.") }}</span>
                </div>
                <a href="{{ route('turf.taxation') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-xs transition shrink-0 text-center">
                    {{ __('Configure Taxation →') }}
                </a>
            </div>
        @endif

        <!-- Today at a Glance (Live Operational Strip) -->
        <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs space-y-5">
            <!-- Header Row -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-gray-900 tracking-tight">{{ __('Today at a Glance') }}</h2>
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/50">
                                <span class="relative flex h-1.5 w-1.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                                </span>
                                {{ __('Live') }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 mt-0.5 flex items-center gap-1.5">
                            <span>{{ $todayDateFormatted }}</span>
                            <span class="text-gray-300">•</span>
                            <span class="font-medium text-indigo-600">{{ $scopedVenueName }}</span>
                        </p>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center gap-2 self-start sm:self-auto">
                    <a href="{{ route('turf.client-bookings') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition duration-150">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        <span>{{ __('New Booking') }}</span>
                    </a>
                    <a href="{{ route('turf.slot-locks') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-semibold transition duration-150">
                        <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        <span>{{ __('Lock Slot') }}</span>
                    </a>
                </div>
            </div>

            <!-- Operational Metrics Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                
                <!-- Today's Bookings -->
                <a href="{{ route('turf.bookings') }}" wire:navigate class="block bg-slate-50/70 hover:bg-slate-50 border border-slate-100/90 rounded-2xl p-4 transition group">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __("Today's Bookings") }}</span>
                        <span class="w-7 h-7 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm group-hover:scale-110 transition">
                            📅
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-gray-900 font-mono">{{ $todayBookingsCount }}</span>
                        <span class="text-[11px] text-gray-500 font-medium">
                            {{ $todayBookingsCount === 1 ? __('session') : __('sessions') }}
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1.5 font-medium">
                        {{ $todayBookedSlotsCount }} {{ $todayBookedSlotsCount === 1 ? __('slot') : __('slots') }} {{ __('reserved today') }}
                    </p>
                </a>

                <!-- Today's Collections -->
                <a href="{{ route('turf.business') }}" wire:navigate class="block bg-slate-50/70 hover:bg-slate-50 border border-slate-100/90 rounded-2xl p-4 transition group">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __("Today's Revenue") }}</span>
                        <span class="w-7 h-7 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm group-hover:scale-110 transition">
                            💰
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-gray-900 font-mono">₹{{ number_format($todayRevenue, 0) }}</span>
                        @if ($todayBalance > 0)
                            <span class="text-[10px] text-amber-600 font-semibold bg-amber-50 px-1.5 py-0.5 rounded">
                                +₹{{ number_format($todayBalance, 0) }} {{ __('due') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1.5 font-medium">
                        {{ __('Total value: ₹') . number_format($todayTotalAmount, 0) }}
                    </p>
                </a>

                <!-- Occupancy Rate -->
                <a href="{{ route('turf.slots') }}" wire:navigate class="block bg-slate-50/70 hover:bg-slate-50 border border-slate-100/90 rounded-2xl p-4 transition group">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __('Slot Occupancy') }}</span>
                        <span class="w-7 h-7 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-sm group-hover:scale-110 transition">
                            ⏱️
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-2xl font-black text-gray-900 font-mono">{{ $occupancyRate }}%</span>
                        <span class="text-[11px] text-gray-500 font-medium">
                            {{ $todayBookedSlotsCount }}/{{ $totalScheduledSlotsToday }} {{ __('slots') }}
                        </span>
                    </div>
                    <!-- Mini Progress Bar -->
                    <div class="w-full bg-gray-200/80 rounded-full h-1.5 mt-2.5 overflow-hidden">
                        <div class="bg-violet-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $occupancyRate }}%"></div>
                    </div>
                </a>

                <!-- Live / Next Upcoming Match -->
                <div class="bg-slate-50/70 border border-slate-100/90 rounded-2xl p-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">
                            @if ($slotStatus === 'live')
                                <span class="text-rose-600 font-black flex items-center gap-1">
                                    <span class="relative flex h-1.5 w-1.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-rose-500"></span>
                                    </span>
                                    {{ __('Match Live Now') }}
                                </span>
                            @elseif ($slotStatus === 'upcoming')
                                <span class="text-emerald-700 font-black flex items-center gap-1">
                                    <span>🟢</span> {{ __('Next Match') }}
                                </span>
                            @else
                                {{ __('Next Activity') }}
                            @endif
                        </span>
                        <span class="w-7 h-7 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                            ⚡
                        </span>
                    </div>
                    <div class="mt-2">
                        @if ($activeOrUpcomingSlot)
                            <div class="text-sm font-bold text-gray-900 truncate" title="{{ $activeOrUpcomingSlot->booking->user?->name ?? __('Customer') }}">
                                {{ Str::limit($activeOrUpcomingSlot->booking->user?->name ?? __('Customer'), 18) }}
                            </div>
                            <p class="text-[11px] font-semibold text-indigo-600 mt-1">
                                {{ date('h:i A', strtotime($activeOrUpcomingSlot->earliest_from)) }} - {{ date('h:i A', strtotime($activeOrUpcomingSlot->latest_to)) }}
                            </p>
                        @else
                            <div class="text-sm font-bold text-gray-400">
                                {{ __('No matches right now') }}
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1 font-medium">
                                {{ __('All remaining slots are open') }}
                            </p>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            
            <!-- Locations Card -->
            <a href="{{ route('turf.locations') }}" class="block bg-white p-6 rounded-3xl border border-gray-100 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-indigo-50 text-indigo-600 group-hover:scale-110 transition">
                        📍
                    </span>
                    <span class="text-2xl font-black text-gray-900 font-mono">{{ $locationsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mt-4">{{ __('Locations') }}</h3>
                <p class="text-[10px] text-gray-400 mt-1">{{ __('Manage courts & arenas') }}</p>
            </a>

            <!-- Turfs Card -->
            <a href="{{ route('turf.turfs') }}" class="block bg-white p-6 rounded-3xl border border-gray-100 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-emerald-50 text-emerald-600 group-hover:scale-110 transition">
                        🏟️
                    </span>
                    <span class="text-2xl font-black text-gray-900 font-mono">{{ $turfsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mt-4">{{ __('Active Turfs') }}</h3>
                <p class="text-[10px] text-gray-400 mt-1">{{ __('Courts open for booking') }}</p>
            </a>

            <!-- Active Slots Card -->
            <a href="{{ route('turf.slots') }}" class="block bg-white p-6 rounded-3xl border border-gray-100 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-amber-50 text-amber-600 group-hover:scale-110 transition">
                        ⏰
                    </span>
                    <span class="text-2xl font-black text-gray-900 font-mono">{{ $activeSlotsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mt-4">{{ __('Scheduled Slots') }}</h3>
                <p class="text-[10px] text-gray-400 mt-1">{{ __('Active booking slots') }}</p>
            </a>
        </div>

        <!-- Secondary Amenities Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            
            <!-- Supported Sports -->
            <a href="{{ route('turf.sports') }}" class="block bg-white p-5 rounded-3xl border border-gray-100 shadow-sm transition hover:shadow-md hover:scale-[1.01] group flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl p-2 rounded-xl bg-violet-50 text-violet-600 group-hover:scale-105 transition">
                        ⚽
                    </span>
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">{{ __('Sports') }}</h4>
                        <p class="text-[9px] text-gray-400 font-medium mt-0.5">{{ __('Supported sport categories') }}</p>
                    </div>
                </div>
                <span class="text-lg font-black text-gray-900 font-mono pr-2">{{ $sportsCount }}</span>
            </a>

            <!-- Facilities -->
            <a href="{{ route('turf.facilities') }}" class="block bg-white p-5 rounded-3xl border border-gray-100 shadow-sm transition hover:shadow-md hover:scale-[1.01] group flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl p-2 rounded-xl bg-cyan-50 text-cyan-600 group-hover:scale-105 transition">
                        ⚡
                    </span>
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">{{ __('Facilities') }}</h4>
                        <p class="text-[9px] text-gray-400 font-medium mt-0.5">{{ __('Amenities & services') }}</p>
                    </div>
                </div>
                <span class="text-lg font-black text-gray-900 font-mono pr-2">{{ $facilitiesCount }}</span>
            </a>

            <!-- Equipments -->
            <a href="{{ route('turf.equipments') }}" class="block bg-white p-5 rounded-3xl border border-gray-100 shadow-sm transition hover:shadow-md hover:scale-[1.01] group flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl p-2 rounded-xl bg-orange-50 text-orange-600 group-hover:scale-105 transition">
                        🎒
                    </span>
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 uppercase tracking-wider">{{ __('Equipments') }}</h4>
                        <p class="text-[9px] text-gray-400 font-medium mt-0.5">{{ __('Rentable gears') }}</p>
                    </div>
                </div>
                <span class="text-lg font-black text-gray-900 font-mono pr-2">{{ $equipmentsCount }}</span>
            </a>

        </div>

        <!-- Locations & Turfs Overview list -->
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-6">
            <div class="pb-4 border-b border-gray-50">
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Your Locations & Arenas') }}</h3>
                <p class="text-[10px] text-gray-400 font-semibold mt-0.5">{{ __('Detailed view of properties and turfs registered under your account') }}</p>
            </div>

            <div class="space-y-6">
                @forelse ($locations as $loc)
                    <div class="p-5 rounded-2xl border border-gray-100 bg-gray-50/30 space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <h4 class="font-bold text-gray-900 text-sm flex items-center gap-1.5">
                                    <span>📍</span> {{ $loc->name }}
                                </h4>
                                <p class="text-[10px] text-gray-400 font-semibold mt-1">{{ $loc->address }}</p>
                            </div>
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 border border-indigo-100/50 uppercase tracking-wider">
                                {{ $loc->turfs->count() }} {{ $loc->turfs->count() === 1 ? __('Turf') : __('Turfs') }}
                            </span>
                        </div>

                        <!-- Turfs Grid within Location -->
                        @if ($loc->turfs->isNotEmpty())
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($loc->turfs as $turf)
                                    <div class="bg-white border border-gray-100 p-4 rounded-xl flex items-center justify-between">
                                        <div class="space-y-1">
                                            <span class="block font-bold text-gray-900 text-xs">{{ $turf->name }}</span>
                                            <span class="inline-flex items-center gap-1.5 text-[9px] text-gray-400 font-semibold">
                                                <span>📐</span> {{ $turf->pricing_wizard_data ? __('Pricing Wizard Active') : __('No Custom Pricing') }}
                                            </span>
                                        </div>
                                        <a href="{{ route('turf.pricing') }}" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-700 flex items-center gap-1 transition">
                                            {{ __('Configure') }} <span>→</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-[10px] text-gray-400 font-semibold py-1">
                                {{ __('No turfs registered for this location yet.') }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-gray-400">
                        <span class="text-3xl block mb-3">📭</span>
                        {{ __('No locations or turfs found. Please register a location first.') }}
                    </div>
                @endforelse
            </div>
    </div>
</div>
