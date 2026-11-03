<?php

use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotLock;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Services\CommissionInvoiceService;
use App\Services\GstStateResolver;
use App\Services\WalletLedgerService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $period = 'month'; // 'today', 'week', 'month', 'year'
    public string $cursorDate = '';
    public string $chartMetric = 'revenue'; // 'revenue' or 'bookings'

    public function mount(): void
    {
        if (empty($this->cursorDate)) {
            $this->cursorDate = Carbon::now('Asia/Kolkata')->toDateString();
        }
    }

    #[On('global-context-updated')]
    public function refreshContext(): void
    {
        // Re-renders when active location or turf changes in top header
    }

    public function setPeriod(string $period): void
    {
        if (in_array($period, ['today', 'week', 'month', 'year'], true)) {
            $this->period = $period;
            $this->cursorDate = Carbon::now('Asia/Kolkata')->toDateString();
        }
    }

    public function setChartMetric(string $metric): void
    {
        if (in_array($metric, ['revenue', 'bookings'], true)) {
            $this->chartMetric = $metric;
        }
    }

    public function previousPeriod(): void
    {
        $cursor = $this->resolveCursor();
        $this->cursorDate = match ($this->period) {
            'today' => $cursor->subDay()->toDateString(),
            'week' => $cursor->subWeek()->toDateString(),
            'month' => $cursor->subMonthNoOverflow()->toDateString(),
            'year' => $cursor->subYear()->toDateString(),
            default => $cursor->subMonthNoOverflow()->toDateString(),
        };
    }

    public function nextPeriod(): void
    {
        $cursor = $this->resolveCursor();
        $next = match ($this->period) {
            'today' => $cursor->copy()->addDay(),
            'week' => $cursor->copy()->addWeek(),
            'month' => $cursor->copy()->addMonthNoOverflow(),
            'year' => $cursor->copy()->addYear(),
            default => $cursor->copy()->addMonthNoOverflow(),
        };

        $now = Carbon::now('Asia/Kolkata');
        if ($next->greaterThan($now)) {
            $this->cursorDate = $now->toDateString();
        } else {
            $this->cursorDate = $next->toDateString();
        }
    }

    public function goToCurrentPeriod(): void
    {
        $this->cursorDate = Carbon::now('Asia/Kolkata')->toDateString();
    }

    protected function resolveCursor(): Carbon
    {
        try {
            return $this->cursorDate
                ? Carbon::parse($this->cursorDate, 'Asia/Kolkata')
                : Carbon::now('Asia/Kolkata');
        } catch (\Throwable $e) {
            return Carbon::now('Asia/Kolkata');
        }
    }

    protected function calculateGrowth(float|int $current, float|int $previous): array
    {
        if ($previous <= 0) {
            if ($current > 0) {
                return ['pct' => 100.0, 'direction' => 'up', 'label' => '+100%'];
            }
            return ['pct' => 0.0, 'direction' => 'neutral', 'label' => '0%'];
        }

        $diff = (($current - $previous) / $previous) * 100;
        $rounded = round($diff, 1);

        return [
            'pct' => abs($rounded),
            'direction' => $rounded > 0 ? 'up' : ($rounded < 0 ? 'down' : 'neutral'),
            'label' => ($rounded > 0 ? '+' : '') . $rounded . '%',
        ];
    }

    public function with(): array
    {
        $now = Carbon::now('Asia/Kolkata');
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $todayDayOfWeek = strtolower($now->format('D'));

        // 1. Locations & Turfs
        $locations = Location::manageable()->with('turfs')->get();
        $locationsCount = $locations->count();
        $turfs = Turf::manageable()->with(['location.user', 'activeSubscription'])->get();
        $turfsCount = $turfs->count();
        $turfIds = $turfs->pluck('id');
        $manageableTurfIds = $turfIds->toArray();

        // 2. Setup Counts
        $activeSlotsCount = DB::table('slot_turf')
            ->whereIn('turf_id', $turfIds)
            ->where('is_active', true)
            ->distinct('slot_id')
            ->count();

        $facilitiesCount = DB::table('facility_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('facility_id')
            ->count();

        $equipmentsCount = DB::table('equipment_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('equipment_id')
            ->count();

        $sportsCount = DB::table('sport_turf')
            ->whereIn('turf_id', $turfIds)
            ->distinct('sport_id')
            ->count();

        // 3. Active Turf Scoping
        $activeTurfId = session('active_turf_id');
        $activeTurf = $activeTurfId ? $turfs->firstWhere('id', (int) $activeTurfId) : null;
        if (!$activeTurf) {
            $activeTurf = $turfs->first();
        }
        $missingGstState = $activeTurf ? (GstStateResolver::turfStateCode($activeTurf) === null) : false;

        if ($activeTurfId && in_array((int) $activeTurfId, $manageableTurfIds, true)) {
            $scopedTurfIds = [(int) $activeTurfId];
            $selectedTurf = $turfs->firstWhere('id', (int) $activeTurfId);
            $scopedVenueName = $selectedTurf ? $selectedTurf->name : 'Active Turf';
        } else {
            $scopedTurfIds = $manageableTurfIds;
            $scopedVenueName = $turfsCount > 1 ? 'All Venues (' . $turfsCount . ')' : ($turfs->first()?->name ?? 'All Venues');
        }

        // 4. Period Window & Previous Comparison Window
        $cursor = $this->resolveCursor();
        switch ($this->period) {
            case 'today':
                $startDate = $cursor->copy()->startOfDay();
                $endDate = $cursor->copy()->endOfDay();
                $prevStartDate = $cursor->copy()->subDay()->startOfDay();
                $prevEndDate = $cursor->copy()->subDay()->endOfDay();
                $periodLabel = $cursor->isSameDay($now)
                    ? 'Today · ' . $cursor->format('d M Y')
                    : $cursor->format('D, d M Y');
                $comparisonLabel = 'vs prior day';
                $isCurrentPeriod = $cursor->isSameDay($now);
                break;

            case 'week':
                $startDate = $cursor->copy()->startOfWeek(Carbon::MONDAY);
                $endDate = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
                $prevStartDate = $startDate->copy()->subWeek();
                $prevEndDate = $endDate->copy()->subWeek();
                $periodLabel = $startDate->format('d M') . ' – ' . $endDate->format('d M Y');
                $comparisonLabel = 'vs prior week';
                $isCurrentPeriod = $now->betweenIncluded($startDate, $endDate);
                break;

            case 'year':
                $startDate = $cursor->copy()->startOfYear();
                $endDate = $cursor->copy()->endOfYear();
                $prevStartDate = $startDate->copy()->subYear();
                $prevEndDate = $endDate->copy()->subYear();
                $periodLabel = $cursor->format('Y') . ' Annual View';
                $comparisonLabel = 'vs ' . $prevStartDate->format('Y');
                $isCurrentPeriod = $cursor->year === $now->year;
                break;

            case 'month':
            default:
                $startDate = $cursor->copy()->startOfMonth();
                $endDate = $cursor->copy()->endOfMonth();
                $prevStartDate = $startDate->copy()->subMonthNoOverflow()->startOfMonth();
                $prevEndDate = $startDate->copy()->subMonthNoOverflow()->endOfMonth();
                $periodLabel = $cursor->format('F Y');
                $comparisonLabel = 'vs ' . $prevStartDate->format('M Y');
                $isCurrentPeriod = $cursor->year === $now->year && $cursor->month === $now->month;
                break;
        }

        // 5. Query Period BookingDates
        $periodBookingDates = empty($scopedTurfIds)
            ? collect()
            : BookingDate::whereBetween('booking_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->whereHas('booking', fn ($q) => $q->whereIn('turf_id', $scopedTurfIds))
                ->with(['booking.user', 'booking.turf', 'bookingSlots.slot.category', 'payments'])
                ->get();

        $prevBookingDates = empty($scopedTurfIds)
            ? collect()
            : BookingDate::whereBetween('booking_date', [$prevStartDate->toDateString(), $prevEndDate->toDateString()])
                ->whereHas('booking', fn ($q) => $q->whereIn('turf_id', $scopedTurfIds))
                ->with(['bookingSlots'])
                ->get();

        $confirmedPeriodDates = $periodBookingDates->where('status', 'Confirmed');
        $cancelledPeriodDates = $periodBookingDates->where('status', 'Cancelled');
        $confirmedPrevDates = $prevBookingDates->where('status', 'Confirmed');

        // KPI 1: Revenue & Collections
        $periodGrossRevenue = (float) $confirmedPeriodDates->sum('amount');
        $periodCollectedRevenue = (float) $confirmedPeriodDates->sum('paid_amount');
        $periodPendingBalance = (float) $confirmedPeriodDates->sum('balance_amount');
        $prevGrossRevenue = (float) $confirmedPrevDates->sum('amount');
        $revenueGrowth = $this->calculateGrowth($periodGrossRevenue, $prevGrossRevenue);

        // Online vs Venue Cash split from successful Payments in period
        $onlineCollected = 0.0;
        $venueCashCollected = 0.0;
        foreach ($confirmedPeriodDates as $bd) {
            $successPayments = $bd->payments->where('status', 'Success');
            if ($successPayments->isNotEmpty()) {
                foreach ($successPayments as $pmt) {
                    $netPmt = max(0, (float) $pmt->amount - (float) $pmt->refunded_amount);
                    if (stripos((string) $pmt->payment_method, 'Online') !== false || stripos((string) $pmt->payment_method, 'Razorpay') !== false) {
                        $onlineCollected += $netPmt;
                    } else {
                        $venueCashCollected += $netPmt;
                    }
                }
            } else {
                // Fallback when no Payment row is attached directly to the BookingDate
                $source = strtolower((string) ($bd->booking?->booking_source ?? ''));
                if ($source === 'walkin' || $source === 'offline' || $source === 'manual') {
                    $venueCashCollected += (float) $bd->paid_amount;
                } else {
                    $onlineCollected += (float) $bd->paid_amount;
                }
            }
        }

        // KPI 2: Bookings & Occupancy
        $periodBookingsCount = $confirmedPeriodDates->count();
        $prevBookingsCount = $confirmedPrevDates->count();
        $bookingsGrowth = $this->calculateGrowth($periodBookingsCount, $prevBookingsCount);

        $periodBookedSlotsCount = 0;
        foreach ($confirmedPeriodDates as $bd) {
            $periodBookedSlotsCount += $bd->bookingSlots->count();
        }

        $baseDailySlots = DB::table('slot_turf')
            ->whereIn('turf_id', $scopedTurfIds)
            ->where('is_active', true)
            ->count();

        $daysInPeriod = max(1, $startDate->diffInDays($endDate) + 1);
        $effectiveElapsedDays = $isCurrentPeriod
            ? max(1, $startDate->diffInDays(min($now, $endDate)) + 1)
            : $daysInPeriod;
        $periodAvailableCapacity = max(1, $baseDailySlots * $effectiveElapsedDays);
        $periodOccupancyRate = $baseDailySlots > 0
            ? min(100, (int) round(($periodBookedSlotsCount / $periodAvailableCapacity) * 100))
            : ($periodBookedSlotsCount > 0 ? 100 : 0);

        // KPI 3: Wallet & Payout Status
        $authUser = auth()->user();
        $ownerUser = $activeTurf?->location?->user ?? ($authUser ? app(WalletLedgerService::class)->ownerFor($authUser) : null);
        $walletBalance = $ownerUser ? (float) $ownerUser->commission_wallet_balance : 0.0;
        $pendingClearanceAmount = !empty($scopedTurfIds)
            ? (float) (new WalletService())->pendingCredits($scopedTurfIds)->sum('amount')
            : 0.0;
        $totalPayoutsReceived = $ownerUser
            ? (float) TurfPayout::where('user_id', $ownerUser->id)->where('status', 'completed')->sum('net_amount')
            : 0.0;

        // KPI 4: Commission Plan & Cancellations
        $saas = SaasSetting::first();
        $effectiveCommissionRate = $activeTurf
            ? (float) $activeTurf->commission_percentage
            : ($ownerUser && $ownerUser->onTrial() ? 0.0 : (float) ($saas?->commission_percentage ?? 8.0));
        $hasActiveSub = $turfs->contains(fn ($t) => $t->activeSubscription !== null);
        $onTrial = $ownerUser && $ownerUser->onTrial();
        $trialDaysLeft = $onTrial ? $ownerUser->trialDaysLeft() : 0;

        $cancelledSessionsCount = $cancelledPeriodDates->count();
        $refundedTotalAmount = (float) $cancelledPeriodDates->sum('refund_amount');

        // Net Commission for period using unified CommissionInvoiceService
        $commissionStats = ['net_taxable' => 0.0, 'net_gst' => 0.0, 'grand_total' => 0.0, 'refunded_total' => 0.0];
        if (!empty($scopedTurfIds)) {
            $singleTurfId = count($scopedTurfIds) === 1 ? $scopedTurfIds[0] : null;
            if ($singleTurfId) {
                $calc = app(CommissionInvoiceService::class)->calculatePeriodTransactions($startDate, $endDate, $singleTurfId);
                $commissionStats = [
                    'net_taxable' => (float) ($calc['taxable'] ?? 0.0),
                    'net_gst' => (float) ($calc['gst'] ?? 0.0),
                    'grand_total' => (float) ($calc['total'] ?? 0.0),
                    'refunded_total' => (float) ($calc['reversal_total'] ?? 0.0),
                ];
            } else {
                foreach ($scopedTurfIds as $tid) {
                    $calc = app(CommissionInvoiceService::class)->calculatePeriodTransactions($startDate, $endDate, (int) $tid);
                    $commissionStats['net_taxable'] += (float) ($calc['taxable'] ?? 0.0);
                    $commissionStats['net_gst'] += (float) ($calc['gst'] ?? 0.0);
                    $commissionStats['grand_total'] += (float) ($calc['total'] ?? 0.0);
                    $commissionStats['refunded_total'] += (float) ($calc['reversal_total'] ?? 0.0);
                }
            }
        }

        // 6. Interactive Chart Series (Online Revenue vs Venue Cash Revenue + Bookings Count)
        $chartBuckets = [];
        if ($this->period === 'today') {
            $timeBlocks = [
                '05-08' => ['label' => '5–8 AM', 'full_label' => 'Early Morning (5 AM – 8 AM)', 'start' => 5, 'end' => 7],
                '08-11' => ['label' => '8–11 AM', 'full_label' => 'Morning (8 AM – 11 AM)', 'start' => 8, 'end' => 10],
                '11-14' => ['label' => '11–2 PM', 'full_label' => 'Midday (11 AM – 2 PM)', 'start' => 11, 'end' => 13],
                '14-17' => ['label' => '2–5 PM', 'full_label' => 'Afternoon (2 PM – 5 PM)', 'start' => 14, 'end' => 16],
                '17-20' => ['label' => '5–8 PM', 'full_label' => 'Evening Prime (5 PM – 8 PM)', 'start' => 17, 'end' => 19],
                '20-24' => ['label' => '8–12 AM', 'full_label' => 'Night Floodlight (8 PM – 12 AM)', 'start' => 20, 'end' => 23],
            ];
            foreach ($timeBlocks as $key => $block) {
                $chartBuckets[$key] = [
                    'label' => $block['label'],
                    'full_label' => $block['full_label'],
                    'online' => 0.0,
                    'cash' => 0.0,
                    'revenue' => 0.0,
                    'bookings' => 0,
                ];
            }
            foreach ($confirmedPeriodDates as $bd) {
                $firstSlot = $bd->bookingSlots->first()?->slot;
                $hour = $firstSlot ? (int) substr((string) $firstSlot->from_time, 0, 2) : 18;
                $targetKey = '17-20';
                foreach ($timeBlocks as $key => $block) {
                    if ($hour >= $block['start'] && $hour <= $block['end']) {
                        $targetKey = $key;
                        break;
                    }
                }
                $isWalkin = in_array(strtolower((string) ($bd->booking?->booking_source ?? '')), ['walkin', 'offline', 'manual'], true);
                $amt = (float) $bd->amount;
                $chartBuckets[$targetKey]['revenue'] += $amt;
                $chartBuckets[$targetKey]['online'] += $isWalkin ? 0.0 : $amt;
                $chartBuckets[$targetKey]['cash'] += $isWalkin ? $amt : 0.0;
                $chartBuckets[$targetKey]['bookings'] += 1;
            }
        } elseif ($this->period === 'week') {
            $dayCursor = $startDate->copy();
            while ($dayCursor->lte($endDate)) {
                $dKey = $dayCursor->toDateString();
                $chartBuckets[$dKey] = [
                    'label' => $dayCursor->format('D d'),
                    'full_label' => $dayCursor->format('l, d M Y'),
                    'online' => 0.0,
                    'cash' => 0.0,
                    'revenue' => 0.0,
                    'bookings' => 0,
                ];
                $dayCursor->addDay();
            }
            foreach ($confirmedPeriodDates as $bd) {
                $dKey = $bd->booking_date;
                if (isset($chartBuckets[$dKey])) {
                    $isWalkin = in_array(strtolower((string) ($bd->booking?->booking_source ?? '')), ['walkin', 'offline', 'manual'], true);
                    $amt = (float) $bd->amount;
                    $chartBuckets[$dKey]['revenue'] += $amt;
                    $chartBuckets[$dKey]['online'] += $isWalkin ? 0.0 : $amt;
                    $chartBuckets[$dKey]['cash'] += $isWalkin ? $amt : 0.0;
                    $chartBuckets[$dKey]['bookings'] += 1;
                }
            }
        } elseif ($this->period === 'year') {
            for ($m = 1; $m <= 12; $m++) {
                $mDate = Carbon::createFromDate($startDate->year, $m, 1, 'Asia/Kolkata');
                $mKey = $mDate->format('Y-m');
                $chartBuckets[$mKey] = [
                    'label' => $mDate->format('M'),
                    'full_label' => $mDate->format('F Y'),
                    'online' => 0.0,
                    'cash' => 0.0,
                    'revenue' => 0.0,
                    'bookings' => 0,
                ];
            }
            foreach ($confirmedPeriodDates as $bd) {
                $mKey = substr((string) $bd->booking_date, 0, 7);
                if (isset($chartBuckets[$mKey])) {
                    $isWalkin = in_array(strtolower((string) ($bd->booking?->booking_source ?? '')), ['walkin', 'offline', 'manual'], true);
                    $amt = (float) $bd->amount;
                    $chartBuckets[$mKey]['revenue'] += $amt;
                    $chartBuckets[$mKey]['online'] += $isWalkin ? 0.0 : $amt;
                    $chartBuckets[$mKey]['cash'] += $isWalkin ? $amt : 0.0;
                    $chartBuckets[$mKey]['bookings'] += 1;
                }
            }
        } else {
            // 'month'
            $dayCursor = $startDate->copy();
            while ($dayCursor->lte($endDate)) {
                $dKey = $dayCursor->toDateString();
                $chartBuckets[$dKey] = [
                    'label' => $dayCursor->format('d'),
                    'full_label' => $dayCursor->format('D, d M Y'),
                    'online' => 0.0,
                    'cash' => 0.0,
                    'revenue' => 0.0,
                    'bookings' => 0,
                ];
                $dayCursor->addDay();
            }
            foreach ($confirmedPeriodDates as $bd) {
                $dKey = $bd->booking_date;
                if (isset($chartBuckets[$dKey])) {
                    $isWalkin = in_array(strtolower((string) ($bd->booking?->booking_source ?? '')), ['walkin', 'offline', 'manual'], true);
                    $amt = (float) $bd->amount;
                    $chartBuckets[$dKey]['revenue'] += $amt;
                    $chartBuckets[$dKey]['online'] += $isWalkin ? 0.0 : $amt;
                    $chartBuckets[$dKey]['cash'] += $isWalkin ? $amt : 0.0;
                    $chartBuckets[$dKey]['bookings'] += 1;
                }
            }
        }

        $chartSeries = array_values($chartBuckets);
        $maxChartRevenue = max(1.0, (float) collect($chartSeries)->max('revenue'));
        $maxChartBookings = max(1, (int) collect($chartSeries)->max('bookings'));
        $peakBucket = collect($chartSeries)->sortByDesc($this->chartMetric)->first();
        $avgDailyRevenue = $effectiveElapsedDays > 0 ? round($periodGrossRevenue / $effectiveElapsedDays, 0) : 0;
        $avgBookingValue = $periodBookingsCount > 0 ? round($periodGrossRevenue / $periodBookingsCount, 0) : 0;

        // 7. Business Intelligence: Peak Hours & Time-of-Day Demand
        $timeSlotBands = [
            'morning' => [
                'name' => 'Morning Sessions',
                'time' => '5:00 AM – 12:00 PM',
                'icon' => '🌅',
                'bar_class' => 'from-amber-400 to-orange-500',
                'bg_class' => 'bg-amber-50 text-amber-700 border-amber-200',
                'slots' => 0,
                'revenue' => 0.0,
            ],
            'afternoon' => [
                'name' => 'Afternoon Sessions',
                'time' => '12:00 PM – 4:00 PM',
                'icon' => '☀️',
                'bar_class' => 'from-sky-400 to-blue-500',
                'bg_class' => 'bg-sky-50 text-sky-700 border-sky-200',
                'slots' => 0,
                'revenue' => 0.0,
            ],
            'evening' => [
                'name' => 'Evening Prime',
                'time' => '4:00 PM – 8:00 PM',
                'icon' => '🌇',
                'bar_class' => 'from-emerald-400 to-teal-600',
                'bg_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'slots' => 0,
                'revenue' => 0.0,
            ],
            'night' => [
                'name' => 'Night Floodlight',
                'time' => '8:00 PM – 2:00 AM',
                'icon' => '🌙',
                'bar_class' => 'from-indigo-500 to-violet-600',
                'bg_class' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                'slots' => 0,
                'revenue' => 0.0,
            ],
        ];

        foreach ($confirmedPeriodDates as $bd) {
            $slotCount = max(1, $bd->bookingSlots->count());
            $perSlotRev = ((float) $bd->amount) / $slotCount;
            foreach ($bd->bookingSlots as $bs) {
                $fromHour = $bs->slot ? (int) substr((string) $bs->slot->from_time, 0, 2) : 18;
                if ($fromHour >= 5 && $fromHour < 12) {
                    $bandKey = 'morning';
                } elseif ($fromHour >= 12 && $fromHour < 16) {
                    $bandKey = 'afternoon';
                } elseif ($fromHour >= 16 && $fromHour < 20) {
                    $bandKey = 'evening';
                } else {
                    $bandKey = 'night';
                }
                $timeSlotBands[$bandKey]['slots'] += 1;
                $timeSlotBands[$bandKey]['revenue'] += $perSlotRev;
            }
        }

        $totalBandSlots = max(1, array_sum(array_column($timeSlotBands, 'slots')));
        foreach ($timeSlotBands as $k => $band) {
            $timeSlotBands[$k]['share'] = $periodBookedSlotsCount > 0
                ? (int) round(($band['slots'] / $totalBandSlots) * 100)
                : 0;
        }

        // 8. Booking Source & Top Loyal Customers in Period
        $appBookingsCount = 0;
        $walkinBookingsCount = 0;
        $customerMap = [];

        foreach ($confirmedPeriodDates as $bd) {
            $booking = $bd->booking;
            $source = strtolower((string) ($booking?->booking_source ?? 'app'));
            if (in_array($source, ['walkin', 'offline', 'manual'], true)) {
                $walkinBookingsCount++;
            } else {
                $appBookingsCount++;
            }

            $user = $booking?->user;
            if ($user) {
                $uid = $user->id;
                if (!isset($customerMap[$uid])) {
                    $customerMap[$uid] = [
                        'name' => $user->name ?: 'Customer',
                        'mobile' => $user->mobile ?: ($user->email ?: '—'),
                        'sessions' => 0,
                        'spent' => 0.0,
                    ];
                }
                $customerMap[$uid]['sessions'] += 1;
                $customerMap[$uid]['spent'] += (float) $bd->amount;
            }
        }

        $topCustomers = collect($customerMap)
            ->sortByDesc('spent')
            ->take(4)
            ->values()
            ->all();

        // 9. TODAY AT A GLANCE (LIVE OPERATIONS & HEATMAP)
        $todayDates = empty($scopedTurfIds)
            ? collect()
            : BookingDate::where('booking_date', $today)
                ->whereHas('booking', fn ($q) => $q->whereIn('turf_id', $scopedTurfIds))
                ->with(['booking.user', 'booking.turf', 'bookingSlots.slot.category'])
                ->get();

        $todayConfirmed = $todayDates->where('status', 'Confirmed');
        $todayBookingsCount = $todayConfirmed->count();
        $todayRevenue = (float) $todayConfirmed->sum('paid_amount');
        $todayTotalAmount = (float) $todayConfirmed->sum('amount');
        $todayBalance = (float) $todayConfirmed->sum('balance_amount');

        $todayBookedSlotsCount = 0;
        $bookedSlotIdsToday = [];
        foreach ($todayConfirmed as $bDate) {
            $todayBookedSlotsCount += $bDate->bookingSlots->count();
            foreach ($bDate->bookingSlots as $bs) {
                if ($bs->slot_id) {
                    $bookedSlotIdsToday[$bs->slot_id] = $bDate;
                }
            }
        }

        $lockedSlotIdsToday = empty($scopedTurfIds)
            ? []
            : SlotLock::whereIn('turf_id', $scopedTurfIds)
                ->where('lock_date', $today)
                ->pluck('slot_id')
                ->unique()
                ->all();
        $lockedSlotLookup = array_flip($lockedSlotIdsToday);

        $scheduledSlotRowsToday = empty($scopedTurfIds)
            ? collect()
            : DB::table('slot_turf')
                ->join('slots', 'slots.id', '=', 'slot_turf.slot_id')
                ->whereIn('slot_turf.turf_id', $scopedTurfIds)
                ->where('slot_turf.is_active', true)
                ->where('slot_turf.' . $todayDayOfWeek, true)
                ->select('slots.id', 'slots.from_time', 'slots.to_time')
                ->distinct()
                ->orderBy('slots.from_time')
                ->get();

        if ($scheduledSlotRowsToday->isEmpty() && !empty($scopedTurfIds)) {
            $scheduledSlotRowsToday = DB::table('slot_turf')
                ->join('slots', 'slots.id', '=', 'slot_turf.slot_id')
                ->whereIn('slot_turf.turf_id', $scopedTurfIds)
                ->where('slot_turf.is_active', true)
                ->select('slots.id', 'slots.from_time', 'slots.to_time')
                ->distinct()
                ->orderBy('slots.from_time')
                ->get();
        }

        $totalScheduledSlotsToday = $scheduledSlotRowsToday->count();
        $occupancyRate = ($totalScheduledSlotsToday > 0)
            ? min(100, (int) round(($todayBookedSlotsCount / $totalScheduledSlotsToday) * 100))
            : ($todayBookedSlotsCount > 0 ? 100 : 0);

        // Build Today's Slot Heatmap Pills
        $todaySlotHeatmap = $scheduledSlotRowsToday->map(function ($slotRow) use ($bookedSlotIdsToday, $lockedSlotLookup, $currentTime) {
            $sid = $slotRow->id;
            $from = (string) $slotRow->from_time;
            $to = (string) $slotRow->to_time;
            $isLive = ($from <= $currentTime && $to >= $currentTime);
            $isPast = ($to < $currentTime);

            if (isset($bookedSlotIdsToday[$sid])) {
                $bDate = $bookedSlotIdsToday[$sid];
                $customerName = $bDate->booking?->user?->name ?? 'Booked';
                $state = $isLive ? 'live' : ($isPast ? 'completed' : 'booked');
            } elseif (isset($lockedSlotLookup[$sid])) {
                $customerName = 'Slot Locked';
                $state = 'locked';
            } else {
                $customerName = $isPast ? 'Passed (Unbooked)' : 'Open for Booking';
                $state = $isPast ? 'past_open' : 'open';
            }

            return [
                'id' => $sid,
                'label' => date('g:i A', strtotime($from)),
                'range' => date('g:i A', strtotime($from)) . ' – ' . date('g:i A', strtotime($to)),
                'state' => $state,
                'customer' => $customerName,
            ];
        })->values()->all();

        // Next / In-Progress Match & Today's Queue
        $activeOrUpcomingSlot = null;
        $slotStatus = null;

        $sortedSessions = $todayConfirmed->map(function ($bDate) use ($currentTime) {
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
            $bDate->queue_state = ($earliest && $latest && $earliest <= $currentTime && $latest >= $currentTime)
                ? 'live'
                : (($earliest && $earliest > $currentTime) ? 'upcoming' : 'completed');
            return $bDate;
        })->sortBy('earliest_from')->values();

        $liveSession = $sortedSessions->firstWhere('queue_state', 'live');
        if ($liveSession) {
            $activeOrUpcomingSlot = $liveSession;
            $slotStatus = 'live';
        } else {
            $upcomingSession = $sortedSessions->firstWhere('queue_state', 'upcoming');
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
            'locations' => $locations,
            'missingGstState' => $missingGstState,
            'todayDateFormatted' => $now->format('l, d M Y'),
            'scopedVenueName' => $scopedVenueName,
            // Period Controls & KPI Data
            'periodLabel' => $periodLabel,
            'comparisonLabel' => $comparisonLabel,
            'isCurrentPeriod' => $isCurrentPeriod,
            'periodGrossRevenue' => $periodGrossRevenue,
            'periodCollectedRevenue' => $periodCollectedRevenue,
            'periodPendingBalance' => $periodPendingBalance,
            'onlineCollected' => $onlineCollected,
            'venueCashCollected' => $venueCashCollected,
            'revenueGrowth' => $revenueGrowth,
            'periodBookingsCount' => $periodBookingsCount,
            'periodBookedSlotsCount' => $periodBookedSlotsCount,
            'periodOccupancyRate' => $periodOccupancyRate,
            'bookingsGrowth' => $bookingsGrowth,
            'walletBalance' => $walletBalance,
            'pendingClearanceAmount' => $pendingClearanceAmount,
            'totalPayoutsReceived' => $totalPayoutsReceived,
            'effectiveCommissionRate' => $effectiveCommissionRate,
            'hasActiveSub' => $hasActiveSub,
            'onTrial' => $onTrial,
            'trialDaysLeft' => $trialDaysLeft,
            'cancelledSessionsCount' => $cancelledSessionsCount,
            'refundedTotalAmount' => $refundedTotalAmount,
            'commissionStats' => $commissionStats,
            // Chart & Business Intelligence
            'chartSeries' => $chartSeries,
            'maxChartRevenue' => $maxChartRevenue,
            'maxChartBookings' => $maxChartBookings,
            'peakBucket' => $peakBucket,
            'avgDailyRevenue' => $avgDailyRevenue,
            'avgBookingValue' => $avgBookingValue,
            'timeSlotBands' => $timeSlotBands,
            'appBookingsCount' => $appBookingsCount,
            'walkinBookingsCount' => $walkinBookingsCount,
            'topCustomers' => $topCustomers,
            // Today Live Operations
            'todayBookingsCount' => $todayBookingsCount,
            'todayRevenue' => $todayRevenue,
            'todayTotalAmount' => $todayTotalAmount,
            'todayBalance' => $todayBalance,
            'todayBookedSlotsCount' => $todayBookedSlotsCount,
            'totalScheduledSlotsToday' => $totalScheduledSlotsToday,
            'occupancyRate' => $occupancyRate,
            'activeOrUpcomingSlot' => $activeOrUpcomingSlot,
            'slotStatus' => $slotStatus,
            'todaySlotHeatmap' => $todaySlotHeatmap,
            'todayMatchQueue' => $sortedSessions->take(6),
        ];
    }
}; ?>

<div class="w-full space-y-6">

    {{-- ALERT BANNERS (Preserved for Trial & GST State Compliance) --}}
    @if ($onTrial && $trialDaysLeft <= 7 && !$hasActiveSub)
        <div class="bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-amber-500/15 border border-amber-300/80 text-amber-950 px-5 py-3 rounded-2xl text-xs font-semibold flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-2.5">
                <span class="flex h-2 w-2 relative shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                </span>
                <span>
                    {{ __('Your free trial ends in :count :days. Subscribe to keep 0% commission.', ['count' => $trialDaysLeft, 'days' => \Illuminate\Support\Str::plural('day', $trialDaysLeft)]) }}
                </span>
            </div>
            <a href="{{ route('turf.subscription') }}" wire:navigate class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl font-bold text-xs transition shrink-0 text-center shadow-xs">
                {{ __('Subscribe →') }}
            </a>
        </div>
    @endif

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

    {{-- =====================================================================
         SECTION 1: EXECUTIVE COMMAND BAR & 4 COLORFUL BUSINESS KPI CARDS
         ===================================================================== --}}
    <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center shadow-sm shrink-0">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-lg sm:text-xl font-black text-gray-900 tracking-tight">{{ __('Business & Performance Command Center') }}</h1>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/60">
                            <span>🏟️</span> {{ $scopedVenueName }}
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ __('Real-time revenue analytics, slot utilization, wallet settlements, and live arena operations.') }}
                    </p>
                </div>
            </div>

            {{-- Period Selector & Date Navigation --}}
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="inline-flex p-1 rounded-2xl bg-gray-100 border border-gray-200/60">
                    @foreach (['today' => 'Today', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year'] as $pKey => $pTitle)
                        <button
                            type="button"
                            wire:click="setPeriod('{{ $pKey }}')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer {{ $period === $pKey ? 'bg-white text-gray-900 shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}"
                        >
                            {{ __($pTitle) }}
                        </button>
                    @endforeach
                </div>

                <div class="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-2xl p-1">
                    <button
                        type="button"
                        wire:click="previousPeriod"
                        class="p-1.5 rounded-xl text-gray-600 hover:bg-white hover:text-gray-900 transition cursor-pointer"
                        title="{{ __('Previous Period') }}"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <span class="px-2.5 text-xs font-black text-gray-800 whitespace-nowrap">{{ $periodLabel }}</span>
                    <button
                        type="button"
                        wire:click="nextPeriod"
                        @disabled($isCurrentPeriod)
                        class="p-1.5 rounded-xl transition {{ $isCurrentPeriod ? 'text-gray-300 cursor-not-allowed' : 'text-gray-600 hover:bg-white hover:text-gray-900 cursor-pointer' }}"
                        title="{{ __('Next Period') }}"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                    @if (!$isCurrentPeriod)
                        <button
                            type="button"
                            wire:click="goToCurrentPeriod"
                            class="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold transition cursor-pointer"
                        >
                            {{ __('Current') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        {{-- 4 Colorful Business KPI Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">

            {{-- Card 1: Gross Revenue & Collections (Vibrant Emerald Gradient) --}}
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-600 to-teal-700 text-white p-5 shadow-md">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10 blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-100">{{ __('Period Gross Revenue') }}</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black {{ $revenueGrowth['direction'] === 'down' ? 'bg-rose-500/30 text-rose-100' : 'bg-white/20 text-white' }}">
                        {{ $revenueGrowth['label'] }} {{ $comparisonLabel }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight font-mono">₹{{ number_format($periodGrossRevenue, 0) }}</span>
                </div>
                <div class="mt-4 pt-3 border-t border-white/15 flex items-center justify-between text-xs">
                    <div>
                        <span class="text-emerald-100/90 text-[11px]">{{ __('Collected:') }}</span>
                        <span class="font-bold font-mono ml-1">₹{{ number_format($periodCollectedRevenue, 0) }}</span>
                    </div>
                    @if ($periodPendingBalance > 0)
                        <span class="px-2 py-0.5 rounded-lg bg-amber-400/25 text-amber-100 font-bold text-[11px] font-mono">
                            ₹{{ number_format($periodPendingBalance, 0) }} {{ __('Due') }}
                        </span>
                    @else
                        <span class="text-emerald-100 text-[11px] font-semibold">✓ {{ __('100% Collected') }}</span>
                    @endif
                </div>
            </div>

            {{-- Card 2: Confirmed Bookings & Slot Occupancy (Vibrant Indigo/Blue Gradient) --}}
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-600 to-blue-700 text-white p-5 shadow-md">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10 blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-100">{{ __('Confirmed Bookings') }}</span>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black {{ $bookingsGrowth['direction'] === 'down' ? 'bg-rose-500/30 text-rose-100' : 'bg-white/20 text-white' }}">
                        {{ $bookingsGrowth['label'] }} {{ $comparisonLabel }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <div>
                        <span class="text-3xl font-black tracking-tight font-mono">{{ $periodBookingsCount }}</span>
                        <span class="text-xs text-indigo-100 font-semibold ml-1">{{ $periodBookingsCount === 1 ? __('session') : __('sessions') }}</span>
                    </div>
                    <div class="text-right">
                        <span class="text-lg font-black font-mono text-amber-300">{{ $periodOccupancyRate }}%</span>
                        <span class="block text-[10px] text-indigo-100">{{ __('Occupancy') }}</span>
                    </div>
                </div>
                <div class="mt-3 w-full bg-white/20 rounded-full h-1.5 overflow-hidden">
                    <div class="bg-amber-300 h-1.5 rounded-full transition-all duration-500" style="width: {{ $periodOccupancyRate }}%"></div>
                </div>
                <div class="mt-2.5 flex items-center justify-between text-[11px] text-indigo-100">
                    <span>{{ $periodBookedSlotsCount }} {{ __('slots reserved') }}</span>
                    <span>{{ __('Avg ₹') . number_format($avgBookingValue, 0) . __('/booking') }}</span>
                </div>
            </div>

            {{-- Card 3: Wallet & Payout Status (Vibrant Violet/Purple Gradient) --}}
            <a href="{{ route('turf.business') }}" wire:navigate class="block relative overflow-hidden rounded-3xl {{ $walletBalance < 0 ? 'bg-gradient-to-br from-rose-600 via-red-600 to-orange-700' : 'bg-gradient-to-br from-violet-600 via-purple-600 to-fuchsia-700' }} text-white p-5 shadow-md hover:opacity-95 transition">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10 blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-purple-100">
                        {{ $walletBalance < 0 ? __('Commission Due Balance') : __('Available Wallet Balance') }}
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white">
                        {{ __('Business & Wallet →') }}
                    </span>
                </div>
                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-3xl font-black tracking-tight font-mono">
                        {{ $walletBalance < 0 ? '-₹' : '₹' }}{{ number_format(abs($walletBalance), 2) }}
                    </span>
                </div>
                <div class="mt-4 pt-3 border-t border-white/15 flex items-center justify-between text-[11px]">
                    <div>
                        <span class="text-purple-100">{{ __('In Pipeline:') }}</span>
                        <span class="font-bold font-mono ml-1">₹{{ number_format($pendingClearanceAmount, 0) }}</span>
                    </div>
                    <div>
                        <span class="text-purple-100">{{ __('Payouts:') }}</span>
                        <span class="font-bold font-mono ml-1">₹{{ number_format($totalPayoutsReceived, 0) }}</span>
                    </div>
                </div>
            </a>

            {{-- Card 4: Net Commission & Cancellations (Amber/Slate Surface Card with Accent) --}}
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-950 text-white p-5 shadow-md">
                <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-amber-400/10 blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-300">{{ __('Net Commission & Refunds') }}</span>
                    @if ($onTrial)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-400/30">
                            0% · Trial ({{ $trialDaysLeft }}d)
                        </span>
                    @elseif ($hasActiveSub)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-500/20 text-indigo-300 border border-indigo-400/30">
                            {{ number_format($effectiveCommissionRate, 1) }}% · Plan
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-400/30">
                            {{ number_format($effectiveCommissionRate, 1) }}% Fee
                        </span>
                    @endif
                </div>
                <div class="mt-3 flex items-baseline justify-between gap-2">
                    <div>
                        <span class="text-3xl font-black tracking-tight font-mono text-amber-300">₹{{ number_format($commissionStats['grand_total'], 2) }}</span>
                        <span class="block text-[10px] text-slate-300 mt-0.5">
                            {{ __('Taxable ₹') . number_format($commissionStats['net_taxable'], 2) }} + {{ __('GST ₹') . number_format($commissionStats['net_gst'], 2) }}
                        </span>
                    </div>
                </div>
                <div class="mt-3 pt-2.5 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-300">
                    <span>
                        {{ __('Cancelled:') }}
                        <strong class="text-rose-300 font-mono">{{ $cancelledSessionsCount }}</strong>
                    </span>
                    <span>
                        {{ __('Refunded:') }}
                        <strong class="text-emerald-300 font-mono">₹{{ number_format($refundedTotalAmount, 0) }}</strong>
                    </span>
                </div>
            </div>

        </div>
    </div>

    {{-- =====================================================================
         SECTION 2: INTERACTIVE REVENUE & BOOKINGS TREND CHART
         ===================================================================== --}}
    <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-gray-100">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base">📈</span>
                    <h2 class="text-base font-black text-gray-900 tracking-tight">
                        {{ $chartMetric === 'revenue' ? __('Revenue & Collection Trend') : __('Confirmed Bookings Volume Trend') }}
                    </h2>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                    {{ __('Visualizing performance across') }} <span class="font-bold text-gray-700">{{ $periodLabel }}</span>
                    @if ($peakBucket && ($peakBucket['revenue'] > 0 || $peakBucket['bookings'] > 0))
                        <span class="text-gray-300 mx-1">•</span>
                        <span class="text-emerald-700 font-semibold">
                            🔥 {{ __('Peak:') }} {{ $peakBucket['full_label'] }}
                            (₹{{ number_format($peakBucket['revenue'], 0) }} / {{ $peakBucket['bookings'] }} {{ __('bookings') }})
                        </span>
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                {{-- Legend for Revenue Stacked Bars --}}
                @if ($chartMetric === 'revenue')
                    <div class="hidden md:flex items-center gap-3 text-[11px] font-semibold text-gray-600 mr-2">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-xs bg-indigo-600"></span> {{ __('Online App') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-xs bg-emerald-500"></span> {{ __('Walk-in / Venue') }}
                        </span>
                    </div>
                @endif

                {{-- Metric Switcher --}}
                <div class="inline-flex p-1 rounded-2xl bg-gray-100 border border-gray-200/60">
                    <button
                        type="button"
                        wire:click="setChartMetric('revenue')"
                        class="px-3 py-1 rounded-xl text-xs font-bold transition cursor-pointer {{ $chartMetric === 'revenue' ? 'bg-indigo-600 text-white shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}"
                    >
                        ₹ {{ __('Revenue') }}
                    </button>
                    <button
                        type="button"
                        wire:click="setChartMetric('bookings')"
                        class="px-3 py-1 rounded-xl text-xs font-bold transition cursor-pointer {{ $chartMetric === 'bookings' ? 'bg-indigo-600 text-white shadow-2xs' : 'text-gray-600 hover:text-gray-900' }}"
                    >
                        # {{ __('Bookings') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Interactive Bar Chart Canvas --}}
        <div class="pt-2">
            <div class="h-56 sm:h-64 flex items-end gap-1.5 sm:gap-2.5 pt-8 pb-2 px-2 bg-gradient-to-b from-slate-50/40 to-slate-50/90 rounded-2xl border border-slate-100 overflow-x-auto">
                @foreach ($chartSeries as $point)
                    @php
                        if ($chartMetric === 'revenue') {
                            $totalVal = (float) $point['revenue'];
                            $heightPct = $totalVal > 0 ? max(8, (int) round(($totalVal / $maxChartRevenue) * 100)) : 3;
                            $onlineSharePct = $totalVal > 0 ? (int) round(($point['online'] / $totalVal) * 100) : 0;
                            $cashSharePct = max(0, 100 - $onlineSharePct);
                        } else {
                            $totalVal = (int) $point['bookings'];
                            $heightPct = $totalVal > 0 ? max(8, (int) round(($totalVal / $maxChartBookings) * 100)) : 3;
                        }
                    @endphp
                    <div class="flex-1 min-w-[22px] h-full flex flex-col justify-end items-center group relative">
                        {{-- Hover Tooltip --}}
                        <div class="opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-150 absolute -top-2 z-20 -translate-y-full bg-gray-900 text-white text-[10px] rounded-xl px-2.5 py-1.5 shadow-lg whitespace-nowrap">
                            <div class="font-bold text-gray-200">{{ $point['full_label'] }}</div>
                            <div class="font-mono font-black text-emerald-300">₹{{ number_format($point['revenue'], 0) }} ({{ $point['bookings'] }} {{ __('sessions') }})</div>
                            @if ($point['revenue'] > 0)
                                <div class="text-[9px] text-gray-300">
                                    App: ₹{{ number_format($point['online'], 0) }} • Venue: ₹{{ number_format($point['cash'], 0) }}
                                </div>
                            @endif
                        </div>

                        {{-- Value Label Above Bar on Desktop for non-zero bars --}}
                        @if ($totalVal > 0 && count($chartSeries) <= 14)
                            <span class="text-[9px] font-bold font-mono text-gray-500 mb-1 hidden sm:block">
                                {{ $chartMetric === 'revenue' ? '₹' . number_format($totalVal, 0) : $totalVal }}
                            </span>
                        @endif

                        {{-- Stacked Bar --}}
                        <div class="w-full max-w-[38px] rounded-t-xl overflow-hidden flex flex-col justify-end transition-all duration-300 group-hover:scale-x-105" style="height: {{ $heightPct }}%">
                            @if ($totalVal <= 0)
                                <div class="w-full h-full bg-gray-200/70 rounded-t-xl"></div>
                            @elseif ($chartMetric === 'revenue')
                                @if ($cashSharePct > 0)
                                    <div class="w-full bg-emerald-500 transition-all" style="height: {{ $cashSharePct }}%"></div>
                                @endif
                                @if ($onlineSharePct > 0)
                                    <div class="w-full bg-indigo-600 transition-all" style="height: {{ $onlineSharePct }}%"></div>
                                @endif
                            @else
                                <div class="w-full h-full bg-gradient-to-t from-indigo-600 to-violet-500 rounded-t-xl"></div>
                            @endif
                        </div>

                        {{-- X-Axis Label --}}
                        <span class="mt-2 text-[10px] font-bold text-gray-500 group-hover:text-indigo-600 truncate max-w-full">
                            {{ $point['label'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Summary Strip Below Chart --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-1">
            <div class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Avg Daily Revenue') }}</span>
                <div class="text-base font-black text-gray-900 font-mono mt-0.5">₹{{ number_format($avgDailyRevenue, 0) }}</div>
            </div>
            <div class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Avg Booking Value') }}</span>
                <div class="text-base font-black text-gray-900 font-mono mt-0.5">₹{{ number_format($avgBookingValue, 0) }}</div>
            </div>
            <div class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Online Collections') }}</span>
                <div class="text-base font-black text-indigo-600 font-mono mt-0.5">₹{{ number_format($onlineCollected, 0) }}</div>
            </div>
            <div class="bg-slate-50/80 border border-slate-100 rounded-2xl px-4 py-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ __('Venue Cash / UPI') }}</span>
                <div class="text-base font-black text-emerald-600 font-mono mt-0.5">₹{{ number_format($venueCashCollected, 0) }}</div>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         SECTION 3: 3-COLUMN BUSINESS INTELLIGENCE VISUAL GRID
         ===================================================================== --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Widget A: Payment & Collection Mode Split (SVG Donut + Breakdown) --}}
        @php
            $totalSplitBase = max(1.0, $onlineCollected + $venueCashCollected + $periodPendingBalance);
            $onlinePct = $periodGrossRevenue > 0 ? (int) round(($onlineCollected / $totalSplitBase) * 100) : 0;
            $cashPct = $periodGrossRevenue > 0 ? (int) round(($venueCashCollected / $totalSplitBase) * 100) : 0;
            $duePct = $periodGrossRevenue > 0 ? max(0, 100 - $onlinePct - $cashPct) : 0;
        @endphp
        <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-5">
            <div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base">💳</span>
                        <div>
                            <h3 class="text-sm font-black text-gray-900">{{ __('Payment & Collection Split') }}</h3>
                            <p class="text-[11px] text-gray-400">{{ __('Online vs Venue Cash vs Pending Dues') }}</p>
                        </div>
                    </div>
                </div>

                {{-- SVG Donut Ring + Center Metric --}}
                <div class="my-5 flex items-center justify-center gap-6">
                    <div class="relative w-32 h-32 shrink-0">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                            <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#f1f5f9" stroke-width="3.8"></circle>
                            @if ($onlinePct > 0)
                                <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#4f46e5" stroke-width="3.8"
                                    stroke-dasharray="{{ $onlinePct }} {{ 100 - $onlinePct }}" stroke-dashoffset="0"></circle>
                            @endif
                            @if ($cashPct > 0)
                                <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#10b981" stroke-width="3.8"
                                    stroke-dasharray="{{ $cashPct }} {{ 100 - $cashPct }}" stroke-dashoffset="-{{ $onlinePct }}"></circle>
                            @endif
                            @if ($duePct > 0)
                                <circle cx="18" cy="18" r="15.915" fill="transparent" stroke="#f59e0b" stroke-width="3.8"
                                    stroke-dasharray="{{ $duePct }} {{ 100 - $duePct }}" stroke-dashoffset="-{{ $onlinePct + $cashPct }}"></circle>
                            @endif
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                            <span class="text-[10px] font-bold uppercase text-gray-400">{{ __('Collected') }}</span>
                            <span class="text-sm font-black text-gray-900 font-mono">
                                {{ $periodGrossRevenue > 0 ? min(100, $onlinePct + $cashPct) : 0 }}%
                            </span>
                        </div>
                    </div>

                    <div class="space-y-2.5 flex-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-gray-700">
                                <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span> {{ __('Online App') }}
                            </span>
                            <span class="font-bold font-mono text-gray-900">₹{{ number_format($onlineCollected, 0) }} ({{ $onlinePct }}%)</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-gray-700">
                                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> {{ __('Venue Cash') }}
                            </span>
                            <span class="font-bold font-mono text-gray-900">₹{{ number_format($venueCashCollected, 0) }} ({{ $cashPct }}%)</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="inline-flex items-center gap-1.5 font-semibold text-gray-700">
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> {{ __('Balance Due') }}
                            </span>
                            <span class="font-bold font-mono text-amber-600">₹{{ number_format($periodPendingBalance, 0) }} ({{ $duePct }}%)</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-xs">
                <span class="text-gray-500 font-medium">{{ __('Total Period Value') }}</span>
                <span class="font-black font-mono text-gray-900">₹{{ number_format($periodGrossRevenue, 2) }}</span>
            </div>
        </div>

        {{-- Widget B: Peak Hours & Time-of-Day Slot Demand --}}
        <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-base">🔥</span>
                    <div>
                        <h3 class="text-sm font-black text-gray-900">{{ __('Peak Hours & Slot Demand') }}</h3>
                        <p class="text-[11px] text-gray-400">{{ __('Utilization & revenue by time of day') }}</p>
                    </div>
                </div>
                <span class="text-[11px] font-bold text-indigo-600 bg-indigo-50 px-2.5 py-1 rounded-full">
                    {{ $periodBookedSlotsCount }} {{ __('Slots') }}
                </span>
            </div>

            <div class="space-y-3.5 my-auto">
                @foreach ($timeSlotBands as $band)
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-1.5">
                                <span>{{ $band['icon'] }}</span>
                                <span class="font-bold text-gray-800">{{ __($band['name']) }}</span>
                                <span class="text-[10px] text-gray-400 hidden sm:inline">({{ $band['time'] }})</span>
                            </div>
                            <div class="flex items-center gap-2 font-mono">
                                <span class="text-[11px] font-bold text-gray-600">{{ $band['slots'] }} {{ __('slots') }}</span>
                                <span class="text-xs font-black text-gray-900">₹{{ number_format($band['revenue'], 0) }}</span>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border {{ $band['bg_class'] }}">{{ $band['share'] }}%</span>
                            </div>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                            <div class="bg-gradient-to-r {{ $band['bar_class'] }} h-2 rounded-full transition-all duration-500" style="width: {{ max($band['slots'] > 0 ? 6 : 0, $band['share']) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-[11px] text-gray-500">
                <span>{{ __('Tip: Adjust dynamic slot pricing for high-demand windows') }}</span>
                <a href="{{ route('turf.pricing') }}" wire:navigate class="font-bold text-indigo-600 hover:text-indigo-700 shrink-0 ml-2">
                    {{ __('Pricing →') }}
                </a>
            </div>
        </div>

        {{-- Widget C: Booking Channel & Top Loyal Customers --}}
        @php
            $totalChannelBookings = max(1, $appBookingsCount + $walkinBookingsCount);
            $appChannelPct = $periodBookingsCount > 0 ? (int) round(($appBookingsCount / $totalChannelBookings) * 100) : 0;
            $walkinChannelPct = $periodBookingsCount > 0 ? max(0, 100 - $appChannelPct) : 0;
        @endphp
        <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <span class="w-8 h-8 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-base">🏆</span>
                        <div>
                            <h3 class="text-sm font-black text-gray-900">{{ __('Channels & Top Players') }}</h3>
                            <p class="text-[11px] text-gray-400">{{ __('App vs Walk-in & most valuable customers') }}</p>
                        </div>
                    </div>
                </div>

                {{-- Channel Ratio Bar --}}
                <div class="mt-4 p-3 rounded-2xl bg-slate-50 border border-slate-100 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-bold">
                        <span class="text-indigo-700">📱 {{ __('App Bookings:') }} {{ $appBookingsCount }} ({{ $appChannelPct }}%)</span>
                        <span class="text-emerald-700">🚶 {{ __('Walk-in:') }} {{ $walkinBookingsCount }} ({{ $walkinChannelPct }}%)</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden flex">
                        <div class="bg-indigo-600 h-full" style="width: {{ $appChannelPct }}%"></div>
                        <div class="bg-emerald-500 h-full" style="width: {{ $walkinChannelPct }}%"></div>
                    </div>
                </div>

                {{-- Top Customers List --}}
                <div class="mt-4 space-y-2.5">
                    @forelse ($topCustomers as $idx => $cust)
                        <div class="flex items-center justify-between gap-3 p-2.5 rounded-2xl hover:bg-gray-50 transition border border-transparent hover:border-gray-100">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                    {{ strtoupper(substr($cust['name'], 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-gray-900 truncate">{{ $cust['name'] }}</div>
                                    <div class="text-[10px] text-gray-400 font-medium truncate">{{ $cust['mobile'] }}</div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-xs font-black font-mono text-gray-900">₹{{ number_format($cust['spent'], 0) }}</div>
                                <div class="text-[10px] font-semibold text-indigo-600">{{ $cust['sessions'] }} {{ $cust['sessions'] === 1 ? __('visit') : __('visits') }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center text-xs text-gray-400">
                            {{ __('No customer bookings recorded in this period yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="pt-3 border-t border-gray-100 flex items-center justify-between text-[11px]">
                <span class="text-gray-400 font-medium">{{ __('Customer Retention') }}</span>
                <a href="{{ route('turf.client-bookings') }}" wire:navigate class="font-bold text-indigo-600 hover:text-indigo-700">
                    {{ __('View All Bookings →') }}
                </a>
            </div>
        </div>

    </div>

    {{-- =====================================================================
         SECTION 4: TODAY AT A GLANCE (LIVE GROUND OPERATIONS, SLOT HEATMAP & MATCH QUEUE)
         ===================================================================== --}}
    <div class="bg-white border border-gray-100 rounded-3xl p-5 sm:p-6 shadow-xs space-y-5">
        {{-- Header Row --}}
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

            {{-- Quick Action Buttons --}}
            <div class="flex items-center gap-2 self-start sm:self-auto">
                <a href="{{ route('turf.bookings') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs transition duration-150">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span>{{ __('Show Bookings') }}</span>
                </a>
                <a href="{{ route('turf.slot-locks') }}" wire:navigate class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-semibold transition duration-150">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <span>{{ __('Lock Slot') }}</span>
                </a>
            </div>
        </div>

        {{-- 4 Operational Metrics Mini-Cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('turf.bookings') }}" wire:navigate class="block bg-slate-50/70 hover:bg-slate-50 border border-slate-100/90 rounded-2xl p-4 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __("Today's Bookings") }}</span>
                    <span class="w-7 h-7 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm group-hover:scale-110 transition">📅</span>
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

            <a href="{{ route('turf.business') }}" wire:navigate class="block bg-slate-50/70 hover:bg-slate-50 border border-slate-100/90 rounded-2xl p-4 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __("Today's Revenue") }}</span>
                    <span class="w-7 h-7 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm group-hover:scale-110 transition">💰</span>
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

            <a href="{{ route('turf.slots') }}" wire:navigate class="block bg-slate-50/70 hover:bg-slate-50 border border-slate-100/90 rounded-2xl p-4 transition group">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ __('Slot Occupancy') }}</span>
                    <span class="w-7 h-7 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-sm group-hover:scale-110 transition">⏱️</span>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-gray-900 font-mono">{{ $occupancyRate }}%</span>
                    <span class="text-[11px] text-gray-500 font-medium">
                        {{ $todayBookedSlotsCount }}/{{ $totalScheduledSlotsToday }} {{ __('slots') }}
                    </span>
                </div>
                <div class="w-full bg-gray-200/80 rounded-full h-1.5 mt-2.5 overflow-hidden">
                    <div class="bg-violet-600 h-1.5 rounded-full transition-all duration-500" style="width: {{ $occupancyRate }}%"></div>
                </div>
            </a>

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
                    <span class="w-7 h-7 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">⚡</span>
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

        {{-- Today's Live Slot Heatmap Strip & Match Queue --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 pt-2">
            {{-- Slot Heatmap Strip (7 cols) --}}
            <div class="lg:col-span-7 bg-slate-50/60 border border-slate-100 rounded-2xl p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-700">{{ __("Today's Slot Availability Matrix") }}</h3>
                    <div class="flex flex-wrap items-center gap-2.5 text-[10px] font-bold">
                        <span class="inline-flex items-center gap-1 text-rose-600"><span class="w-2 h-2 rounded-full bg-rose-500"></span> {{ __('Live') }}</span>
                        <span class="inline-flex items-center gap-1 text-emerald-700"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> {{ __('Booked') }}</span>
                        <span class="inline-flex items-center gap-1 text-amber-700"><span class="w-2 h-2 rounded-full bg-amber-500"></span> {{ __('Locked') }}</span>
                        <span class="inline-flex items-center gap-1 text-gray-500"><span class="w-2 h-2 rounded-full bg-white border border-gray-300"></span> {{ __('Open') }}</span>
                    </div>
                </div>

                @if (!empty($todaySlotHeatmap))
                    <div class="grid grid-cols-4 sm:grid-cols-6 md:grid-cols-8 gap-2 max-h-48 overflow-y-auto pr-1">
                        @foreach ($todaySlotHeatmap as $slotPill)
                            @php
                                $pillClasses = match ($slotPill['state']) {
                                    'live' => 'bg-rose-600 text-white border-rose-600 shadow-xs animate-pulse',
                                    'booked' => 'bg-emerald-600 text-white border-emerald-600 shadow-2xs',
                                    'completed' => 'bg-emerald-100 text-emerald-900 border-emerald-200',
                                    'locked' => 'bg-amber-100 text-amber-900 border-amber-300',
                                    'past_open' => 'bg-gray-100 text-gray-400 border-gray-200/70',
                                    default => 'bg-white text-gray-700 border-gray-200 hover:border-indigo-400',
                                };
                            @endphp
                            <div
                                class="px-2 py-1.5 rounded-xl border text-center transition {{ $pillClasses }}"
                                title="{{ $slotPill['range'] }} • {{ $slotPill['customer'] }}"
                            >
                                <div class="text-[10px] font-black font-mono leading-tight">{{ $slotPill['label'] }}</div>
                                <div class="text-[8px] font-semibold truncate opacity-85 mt-0.5">
                                    {{ Str::limit($slotPill['customer'], 8) }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-8 text-center text-xs text-gray-400">
                        {{ __('No active slots configured for today.') }}
                    </div>
                @endif
            </div>

            {{-- Today's Match Queue & Venue Collections (5 cols) --}}
            <div class="lg:col-span-5 bg-slate-50/60 border border-slate-100 rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-black uppercase tracking-wider text-gray-700">{{ __("Today's Match Queue") }}</h3>
                    <a href="{{ route('turf.client-bookings') }}" wire:navigate class="text-[11px] font-bold text-indigo-600 hover:text-indigo-700">
                        {{ __('Manage All →') }}
                    </a>
                </div>

                <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                    @forelse ($todayMatchQueue as $qSession)
                        <div class="bg-white border border-gray-100 rounded-xl p-2.5 flex items-center justify-between gap-2 shadow-2xs">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5">
                                    @if ($qSession->queue_state === 'live')
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-rose-100 text-rose-700">{{ __('Live') }}</span>
                                    @elseif ($qSession->queue_state === 'upcoming')
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-100 text-emerald-700">{{ __('Next') }}</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-gray-100 text-gray-500">{{ __('Done') }}</span>
                                    @endif
                                    <span class="text-xs font-bold text-gray-900 truncate">
                                        {{ $qSession->booking?->user?->name ?? __('Walk-in Player') }}
                                    </span>
                                </div>
                                <div class="text-[10px] text-gray-500 font-semibold mt-0.5">
                                    @if ($qSession->earliest_from && $qSession->latest_to)
                                        {{ date('g:i A', strtotime($qSession->earliest_from)) }} – {{ date('g:i A', strtotime($qSession->latest_to)) }}
                                    @endif
                                    @if ($qSession->booking?->user?->mobile)
                                        • {{ $qSession->booking->user->mobile }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                @if ((float) $qSession->balance_amount > 0)
                                    <span class="inline-flex items-center px-2 py-1 rounded-lg text-[10px] font-black bg-amber-100 text-amber-800 border border-amber-300 font-mono">
                                        {{ __('Collect ₹') . number_format((float) $qSession->balance_amount, 0) }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono">
                                        ✓ ₹{{ number_format((float) $qSession->paid_amount, 0) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-gray-400">
                            {{ __('No confirmed sessions scheduled for today yet.') }}
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- =====================================================================
         SECTION 5: COMPACT SETUP INVENTORY & YOUR LOCATIONS / ARENAS
         ===================================================================== --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
        <a href="{{ route('turf.locations') }}" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs hover:shadow-md hover:border-indigo-200 transition group">
            <div class="flex items-center justify-between">
                <span class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base group-hover:scale-110 transition">📍</span>
                <span class="text-xl font-black text-gray-900 font-mono">{{ $locationsCount }}</span>
            </div>
            <h3 class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-3">{{ __('Locations') }}</h3>
        </a>

        <a href="{{ route('turf.turfs') }}" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs hover:shadow-md hover:border-emerald-200 transition group">
            <div class="flex items-center justify-between">
                <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base group-hover:scale-110 transition">🏟️</span>
                <span class="text-xl font-black text-gray-900 font-mono">{{ $turfsCount }}</span>
            </div>
            <h3 class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-3">{{ __('Active Turfs') }}</h3>
        </a>

        <a href="{{ route('turf.slots') }}" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs hover:shadow-md hover:border-amber-200 transition group">
            <div class="flex items-center justify-between">
                <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-base group-hover:scale-110 transition">⏰</span>
                <span class="text-xl font-black text-gray-900 font-mono">{{ $activeSlotsCount }}</span>
            </div>
            <h3 class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-3">{{ __('Scheduled Slots') }}</h3>
        </a>

        <a href="{{ route('turf.sports') }}" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs hover:shadow-md hover:border-violet-200 transition group">
            <div class="flex items-center justify-between">
                <span class="w-9 h-9 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center text-base group-hover:scale-110 transition">⚽</span>
                <span class="text-xl font-black text-gray-900 font-mono">{{ $sportsCount }}</span>
            </div>
            <h3 class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-3">{{ __('Sports') }}</h3>
        </a>

        <a href="{{ route('turf.facilities') }}" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs hover:shadow-md hover:border-cyan-200 transition group">
            <div class="flex items-center justify-between">
                <span class="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center text-base group-hover:scale-110 transition">⚡</span>
                <span class="text-xl font-black text-gray-900 font-mono">{{ $facilitiesCount }}</span>
            </div>
            <h3 class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-3">{{ __('Facilities') }}</h3>
        </a>

        <a href="{{ route('turf.equipments') }}" class="bg-white p-4 rounded-2xl border border-gray-100 shadow-2xs hover:shadow-md hover:border-orange-200 transition group">
            <div class="flex items-center justify-between">
                <span class="w-9 h-9 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center text-base group-hover:scale-110 transition">🎒</span>
                <span class="text-xl font-black text-gray-900 font-mono">{{ $equipmentsCount }}</span>
            </div>
            <h3 class="text-[11px] font-bold text-gray-600 uppercase tracking-wider mt-3">{{ __('Equipments') }}</h3>
        </a>
    </div>

    {{-- Locations & Turfs Overview List --}}
    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm space-y-6">
        <div class="pb-4 border-b border-gray-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">{{ __('Your Locations & Arenas') }}</h3>
                <p class="text-[10px] text-gray-400 font-semibold mt-0.5">{{ __('Detailed view of properties and turfs registered under your account') }}</p>
            </div>
            <a href="{{ route('turf.locations') }}" wire:navigate class="text-xs font-bold text-indigo-600 hover:text-indigo-700">
                {{ __('Manage Locations →') }}
            </a>
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
