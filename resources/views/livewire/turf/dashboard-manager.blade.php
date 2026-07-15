<?php

use App\Models\Location;
use App\Models\Turf;
use App\Models\Slot;
use App\Models\Coupon;
use App\Models\Facility;
use App\Models\Equipment;
use App\Models\Sport;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function with()
    {
        $userId = auth()->id();

        // 1. Locations
        $locations = Location::where('user_id', $userId)->get();
        $locationsCount = $locations->count();
        $locationIds = $locations->pluck('id');

        // 2. Turfs
        $turfs = Turf::whereIn('location_id', $locationIds)->get();
        $turfsCount = $turfs->count();
        $turfIds = $turfs->pluck('id');

        // 3. Active Slots (linked to turfs)
        $activeSlotsCount = DB::table('slot_turf')
            ->whereIn('turf_id', $turfIds)
            ->where('is_active', true)
            ->distinct('slot_id')
            ->count();

        // 4. Active Coupons
        $activeCouponsCount = Coupon::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();

        // 5. Facilities
        $facilitiesCount = DB::table('facility_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('facility_id')
            ->count();

        // 6. Equipments
        $equipmentsCount = DB::table('equipment_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('equipment_id')
            ->count();

        // 7. Sports
        $sportsCount = DB::table('sport_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('sport_id')
            ->count();

        return [
            'locationsCount' => $locationsCount,
            'turfsCount' => $turfsCount,
            'activeSlotsCount' => $activeSlotsCount,
            'activeCouponsCount' => $activeCouponsCount,
            'facilitiesCount' => $facilitiesCount,
            'equipmentsCount' => $equipmentsCount,
            'sportsCount' => $sportsCount,
            'locations' => $locations->load('turfs'),
        ];
    }
}; ?>

<div class="py-6">
    <div class="sm:px-6 lg:px-8 space-y-6">

        <!-- Welcome Banner -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-8 rounded-3xl text-white shadow-md relative overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-2xl font-extrabold tracking-tight">{{ __('Welcome, ') . auth()->user()->name }}!</h2>
                <p class="text-xs text-indigo-100 mt-2 max-w-xl leading-relaxed">
                    {{ __('This is your Turf Admin Workspace. Here is a summary of your locations, turfs, active slot schedules, and coupon campaigns.') }}
                </p>
            </div>
            <!-- Decorative Background Graphic -->
            <div class="absolute -right-10 -bottom-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
            <div class="absolute -left-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Locations Card -->
            <a href="{{ route('turf.locations') }}" class="block bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition">
                        📍
                    </span>
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ $locationsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-4">{{ __('Locations') }}</h3>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Manage courts & arenas') }}</p>
            </a>

            <!-- Turfs Card -->
            <a href="{{ route('turf.turfs') }}" class="block bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition">
                        🏟️
                    </span>
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ $turfsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-4">{{ __('Active Turfs') }}</h3>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Courts open for booking') }}</p>
            </a>

            <!-- Active Slots Card -->
            <a href="{{ route('turf.slots') }}" class="block bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 group-hover:scale-110 transition">
                        ⏰
                    </span>
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ $activeSlotsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-4">{{ __('Scheduled Slots') }}</h3>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Active booking slots') }}</p>
            </a>

            <!-- Active Coupons Card -->
            <a href="{{ route('turf.offers') }}" class="block bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group">
                <div class="flex items-center justify-between">
                    <span class="text-2xl shrink-0 p-3 rounded-2xl bg-rose-50 dark:bg-rose-950/20 text-rose-600 dark:text-rose-400 group-hover:scale-110 transition">
                        🎟️
                    </span>
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ $activeCouponsCount }}</span>
                </div>
                <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mt-4">{{ __('Active Coupons') }}</h3>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Discounts & campaigns') }}</p>
            </a>

        </div>

        <!-- Secondary Amenities Stats Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            
            <!-- Supported Sports -->
            <a href="{{ route('turf.sports') }}" class="block bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl p-2 rounded-xl bg-violet-50 dark:bg-violet-950/20 text-violet-600 dark:text-violet-400 group-hover:scale-105 transition">
                        ⚽
                    </span>
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Sports') }}</h4>
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-medium mt-0.5">{{ __('Supported sport categories') }}</p>
                    </div>
                </div>
                <span class="text-lg font-black text-gray-900 dark:text-gray-100 font-mono pr-2">{{ $sportsCount }}</span>
            </a>

            <!-- Facilities -->
            <a href="{{ route('turf.facilities') }}" class="block bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl p-2 rounded-xl bg-cyan-50 dark:bg-cyan-950/20 text-cyan-600 dark:text-cyan-400 group-hover:scale-105 transition">
                        ⚡
                    </span>
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Facilities') }}</h4>
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-medium mt-0.5">{{ __('Amenities & services') }}</p>
                    </div>
                </div>
                <span class="text-lg font-black text-gray-900 dark:text-gray-100 font-mono pr-2">{{ $facilitiesCount }}</span>
            </a>

            <!-- Equipments -->
            <a href="{{ route('turf.equipments') }}" class="block bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm transition hover:shadow-md hover:scale-[1.01] group flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-xl p-2 rounded-xl bg-orange-50 dark:bg-orange-950/20 text-orange-600 dark:text-orange-400 group-hover:scale-105 transition">
                        🎒
                    </span>
                    <div>
                        <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Equipments') }}</h4>
                        <p class="text-[9px] text-gray-400 dark:text-gray-500 font-medium mt-0.5">{{ __('Rentable gears') }}</p>
                    </div>
                </div>
                <span class="text-lg font-black text-gray-900 dark:text-gray-100 font-mono pr-2">{{ $equipmentsCount }}</span>
            </a>

        </div>

        <!-- Locations & Turfs Overview list -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm space-y-6">
            <div class="pb-4 border-b border-gray-50 dark:border-gray-700/40">
                <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 uppercase tracking-wider">{{ __('Your Locations & Arenas') }}</h3>
                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-0.5">{{ __('Detailed view of properties and turfs registered under your account') }}</p>
            </div>

            <div class="space-y-6">
                @forelse ($locations as $loc)
                    <div class="p-5 rounded-2xl border border-gray-100 dark:border-gray-700/30 bg-gray-50/30 dark:bg-gray-900/10 space-y-4">
                        <div class="flex justify-between items-start gap-4">
                            <div>
                                <h4 class="font-bold text-gray-900 dark:text-gray-100 text-sm flex items-center gap-1.5">
                                    <span>📍</span> {{ $loc->name }}
                                </h4>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold mt-1">{{ $loc->address }}</p>
                            </div>
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 dark:text-indigo-400 border border-indigo-100/50 dark:border-indigo-950/30 uppercase tracking-wider">
                                {{ $loc->turfs->count() }} {{ $loc->turfs->count() === 1 ? __('Turf') : __('Turfs') }}
                            </span>
                        </div>

                        <!-- Turfs Grid within Location -->
                        @if ($loc->turfs->isNotEmpty())
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                @foreach ($loc->turfs as $turf)
                                    <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700/50 p-4 rounded-xl flex items-center justify-between">
                                        <div class="space-y-1">
                                            <span class="block font-bold text-gray-900 dark:text-gray-100 text-xs">{{ $turf->name }}</span>
                                            <span class="inline-flex items-center gap-1.5 text-[9px] text-gray-400 dark:text-gray-500 font-semibold">
                                                <span>📐</span> {{ $turf->pricing_wizard_data ? __('Pricing Wizard Active') : __('No Custom Pricing') }}
                                            </span>
                                        </div>
                                        <a href="{{ route('turf.pricing') }}" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300 flex items-center gap-1 transition">
                                            {{ __('Configure') }} <span>→</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-[10px] text-gray-400 dark:text-gray-500 font-semibold py-1">
                                {{ __('No turfs registered for this location yet.') }}
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-12 text-center text-xs text-gray-400 dark:text-gray-500">
                        <span class="text-3xl block mb-3">📭</span>
                        {{ __('No locations or turfs found. Please register a location first.') }}
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
