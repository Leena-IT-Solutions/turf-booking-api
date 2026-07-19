<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Turf;
use App\Models\Booking;
use App\Models\BookingDate;
use Illuminate\Support\Facades\DB;

new #[Layout('layouts.app')] class extends Component
{
    public function with(): array
    {
        // 1. Core KPIs
        $totalRevenue = BookingDate::whereHas('booking', function ($q) {
            $q->where('status', 'Confirmed');
        })->sum('amount');

        $totalBookings = Booking::where('status', 'Confirmed')->count();
        $totalCustomers = User::count();
        $totalTurfs = Turf::count();

        // 2. Booking Types breakdown
        $dayBookings = Booking::where('booking_type', 'day')->where('status', 'Confirmed')->count();
        $longBookings = Booking::where('booking_type', 'long')->where('status', 'Confirmed')->count();
        $scatteredBookings = Booking::where('booking_type', 'scattered')->where('status', 'Confirmed')->count();

        $totalBreakdown = max(1, $dayBookings + $longBookings + $scatteredBookings);
        $dayPercentage = round(($dayBookings / $totalBreakdown) * 100);
        $longPercentage = round(($longBookings / $totalBreakdown) * 100);
        $scatteredPercentage = round(($scatteredBookings / $totalBreakdown) * 100);

        // 3. Top performing turfs by revenue
        $topTurfs = Turf::with('location')
            ->get()
            ->map(function ($turf) {
                $revenue = BookingDate::whereHas('booking', function ($q) use ($turf) {
                    $q->where('turf_id', $turf->id)->where('status', 'Confirmed');
                })->sum('amount');

                $bookingsCount = Booking::where('turf_id', $turf->id)->where('status', 'Confirmed')->count();

                return [
                    'name' => $turf->name,
                    'location' => $turf->location->name ?? 'N/A',
                    'revenue' => $revenue,
                    'bookings_count' => $bookingsCount,
                ];
            })
            ->sortByDesc('revenue')
            ->take(5)
            ->values()
            ->toArray();

        // 4. Recent Bookings (with user, turf, and dates details)
        $recentBookings = Booking::with(['user', 'turf', 'bookingDates'])
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(function ($booking) {
                $totalAmount = $booking->bookingDates->sum('amount');
                $datesList = $booking->bookingDates->pluck('booking_date')->map(function ($d) {
                    return \Carbon\Carbon::parse($d)->format('M d');
                })->join(', ');

                return [
                    'id' => $booking->id,
                    'customer_name' => $booking->user->name ?? 'Guest',
                    'customer_email' => $booking->user->email ?? 'N/A',
                    'turf_name' => $booking->turf->name ?? 'Unknown Turf',
                    'booking_type' => ucfirst($booking->booking_type),
                    'dates' => $datesList ?: 'N/A',
                    'total_price' => $totalAmount,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'created_at' => $booking->created_at->diffForHumans(),
                ];
            })
            ->toArray();

        return [
            'totalRevenue' => $totalRevenue,
            'totalBookings' => $totalBookings,
            'totalCustomers' => $totalCustomers,
            'totalTurfs' => $totalTurfs,
            'dayBookings' => $dayBookings,
            'longBookings' => $longBookings,
            'scatteredBookings' => $scatteredBookings,
            'dayPercentage' => $dayPercentage,
            'longPercentage' => $longPercentage,
            'scatteredPercentage' => $scatteredPercentage,
            'topTurfs' => $topTurfs,
            'recentBookings' => $recentBookings,
        ];
    }
}; ?>

<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Welcome banner -->
        <div class="bg-white dark:bg-gray-800 p-6 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700/50 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ __('SaaS Business Analytics') }}</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">{{ __('Real-time platform usage metrics, overall financial statistics, and venue operations review.') }}</p>
            </div>
            <div class="flex items-center gap-2 px-4 py-1.5 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 rounded-full text-xs font-black uppercase tracking-wider">
                <span class="w-2.5 h-2.5 bg-indigo-500 rounded-full animate-pulse"></span>
                {{ __('Platform Dashboard') }}
            </div>
        </div>

        <!-- KPI Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Revenue Stat Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-blue-50/50 dark:bg-blue-900/10 rounded-full group-hover:scale-110 transition duration-300"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Total Revenue') }}</span>
                    <span class="p-2 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-xl">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4 relative z-10">
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">₹{{ number_format($totalRevenue, 0) }}</span>
                    <span class="block text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Accumulated booking sales') }}</span>
                </div>
            </div>

            <!-- Bookings Stat Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-emerald-50/50 dark:bg-emerald-900/10 rounded-full group-hover:scale-110 transition duration-300"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Confirmed Bookings') }}</span>
                    <span class="p-2 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-xl">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4 relative z-10">
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ number_format($totalBookings) }}</span>
                    <span class="block text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Successfully booked slots') }}</span>
                </div>
            </div>

            <!-- Active Turfs Stat Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-violet-50/50 dark:bg-violet-900/10 rounded-full group-hover:scale-110 transition duration-300"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Managed Courts') }}</span>
                    <span class="p-2 bg-violet-50 dark:bg-violet-950/30 text-violet-600 dark:text-violet-400 rounded-xl">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4 relative z-10">
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ number_format($totalTurfs) }}</span>
                    <span class="block text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Active turf courts registered') }}</span>
                </div>
            </div>

            <!-- Registered Customers Stat Card -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 w-24 h-24 bg-amber-50/50 dark:bg-amber-900/10 rounded-full group-hover:scale-110 transition duration-300"></div>
                <div class="flex items-center justify-between relative z-10">
                    <span class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">{{ __('Total Customers') }}</span>
                    <span class="p-2 bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 rounded-xl">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </span>
                </div>
                <div class="mt-4 relative z-10">
                    <span class="text-2xl font-black text-gray-900 dark:text-gray-100 font-mono">{{ number_format($totalCustomers) }}</span>
                    <span class="block text-[10px] text-gray-400 dark:text-gray-500 mt-1">{{ __('Total registered accounts') }}</span>
                </div>
            </div>

        </div>

        <!-- Detail breakdowns -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Recent Bookings Table -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm p-6 lg:col-span-2">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-700/50">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ __('Recent Bookings') }}</h3>
                    <span class="text-[10px] font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded-md uppercase tracking-wider">{{ __('Latest Activity') }}</span>
                </div>

                <div class="mt-4 overflow-x-auto">
                    @if(empty($recentBookings))
                        <div class="text-center py-12 text-gray-500 dark:text-gray-450">
                            <p class="text-xs font-semibold">{{ __('No bookings placed yet.') }}</p>
                        </div>
                    @else
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-700/50 text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">
                                    <th class="pb-3">{{ __('Customer') }}</th>
                                    <th class="pb-3">{{ __('Turf / Court') }}</th>
                                    <th class="pb-3">{{ __('Date Details') }}</th>
                                    <th class="pb-3 text-right">{{ __('Amount') }}</th>
                                    <th class="pb-3 text-center">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentBookings as $bk)
                                    <tr class="border-b border-gray-100/50 dark:border-gray-750/30 last:border-0 hover:bg-gray-50/50 dark:hover:bg-gray-900/10 transition">
                                        <!-- Customer Info -->
                                        <td class="py-3.5 pr-2">
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 text-white font-bold text-xs flex items-center justify-center">
                                                    {{ strtoupper(substr($bk['customer_name'], 0, 2)) }}
                                                </div>
                                                <div>
                                                    <span class="text-xs font-bold text-gray-800 dark:text-gray-250 block">{{ $bk['customer_name'] }}</span>
                                                    <span class="text-[9px] text-gray-400 dark:text-gray-500 block truncate max-w-[120px]">{{ $bk['customer_email'] }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- Turf / Court -->
                                        <td class="py-3.5 px-2">
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-250 block">{{ $bk['turf_name'] }}</span>
                                            <span class="text-[9px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider block mt-0.5">{{ $bk['booking_type'] }}</span>
                                        </td>
                                        <!-- Dates -->
                                        <td class="py-3.5 px-2">
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-300 block">{{ $bk['dates'] }}</span>
                                            <span class="text-[9px] text-gray-400 dark:text-gray-550 block mt-0.5">{{ $bk['created_at'] }}</span>
                                        </td>
                                        <!-- Amount -->
                                        <td class="py-3.5 px-2 text-right">
                                            <span class="text-xs font-bold text-gray-950 dark:text-gray-100 font-mono">₹{{ number_format($bk['total_price'], 0) }}</span>
                                        </td>
                                        <!-- Status Badge -->
                                        <td class="py-3.5 pl-2 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider {{ $bk['status'] === 'Confirmed' ? 'bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400' : 'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-400' }}">
                                                {{ $bk['status'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            <!-- Side Widgets: Types and Top Performing Turfs -->
            <div class="space-y-6">
                
                <!-- Booking Types distribution -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 pb-3 border-b border-gray-100 dark:border-gray-700/50">{{ __('Booking Distribution') }}</h3>
                    
                    <div class="mt-4 space-y-4">
                        <!-- Day Bookings -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-600 dark:text-gray-400">
                                <span>{{ __('Day-to-day slots') }}</span>
                                <span class="font-mono text-gray-900 dark:text-gray-100">{{ $dayBookings }} ({{ $dayPercentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                <div class="bg-indigo-500 h-1.5 rounded-full" style="width: {{ $dayPercentage }}%"></div>
                            </div>
                        </div>

                        <!-- Long Bookings -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-600 dark:text-gray-400">
                                <span>{{ __('Long bookings') }}</span>
                                <span class="font-mono text-gray-900 dark:text-gray-100">{{ $longBookings }} ({{ $longPercentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                <div class="bg-violet-500 h-1.5 rounded-full" style="width: {{ $longPercentage }}%"></div>
                            </div>
                        </div>

                        <!-- Scattered Bookings -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-600 dark:text-gray-400">
                                <span>{{ __('Scattered blocks') }}</span>
                                <span class="font-mono text-gray-900 dark:text-gray-100">{{ $scatteredBookings }} ({{ $scatteredPercentage }}%)</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                <div class="bg-amber-500 h-1.5 rounded-full" style="width: {{ $scatteredPercentage }}%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Performing Turfs -->
                <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700/50 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-gray-100 pb-3 border-b border-gray-100 dark:border-gray-700/50">{{ __('Top Performing Courts') }}</h3>
                    
                    <div class="mt-4 space-y-4">
                        @if(empty($topTurfs))
                            <div class="text-center py-6 text-gray-500 dark:text-gray-450">
                                <p class="text-xs font-semibold">{{ __('No performing courts statistics yet.') }}</p>
                            </div>
                        @else
                            @foreach($topTurfs as $idx => $t)
                                <div class="flex items-center justify-between hover:bg-gray-50/50 dark:hover:bg-gray-900/10 p-2 rounded-xl transition duration-150">
                                    <div class="flex items-center gap-3">
                                        <div class="w-6 h-6 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 text-xs font-black flex items-center justify-center">
                                            {{ $idx + 1 }}
                                        </div>
                                        <div>
                                            <span class="text-xs font-bold text-gray-800 dark:text-gray-250 block">{{ $t['name'] }}</span>
                                            <span class="text-[9px] text-gray-400 dark:text-gray-500 block">{{ $t['location'] }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-xs font-bold text-gray-900 dark:text-gray-100 font-mono block">₹{{ number_format($t['revenue'], 0) }}</span>
                                        <span class="text-[9px] text-gray-400 dark:text-gray-550 block">{{ $t['bookings_count'] }} {{ __('bookings') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

            </div>

        </div>

    </div>
</div>
