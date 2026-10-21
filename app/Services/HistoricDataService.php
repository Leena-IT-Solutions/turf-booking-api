<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregates for Reports → Historic Data.
 *
 * - One aggregate query per selected metric (plus one for distinct bookings), whatever the view
 *   or the amount of data. Day view needs fewer queries than that (shared row queries).
 * - Cross-database SQL only (DATE, SUM, COUNT, CASE). Hours and months are bucketed in PHP.
 * - Never writes anything.
 */
class HistoricDataService
{
    public const TZ = 'Asia/Kolkata';

    public const METRICS = [
        'bookings_received' => ['label' => 'Bookings Received',    'unit' => 'count'],
        'booking_amount'    => ['label' => 'Booking Amount',       'unit' => 'inr'],
        'received_app'      => ['label' => 'Received by App',      'unit' => 'inr'],
        'received_location' => ['label' => 'Received at Location', 'unit' => 'inr'],
        'slots_booked'      => ['label' => 'Slots Booked',         'unit' => 'count'],
        'cancellations'     => ['label' => 'Cancellations',        'unit' => 'count'],
    ];

    public const VIEWS = ['day', 'week', 'month', 'year'];

    public const DATE_BASES = ['play', 'booked'];

    /** Metrics whose date follows the Date basis toggle. */
    public const BOOKING_METRICS = ['bookings_received', 'booking_amount', 'slots_booked'];

    public const LOCATION_METHODS = ['Cash', 'UPI', 'Other'];

    public function parseCursor(?string $cursor): Carbon
    {
        if ($cursor && preg_match('/^\d{4}-\d{2}-\d{2}$/', $cursor)) {
            try {
                return Carbon::createFromFormat('Y-m-d', $cursor, self::TZ)->startOfDay();
            } catch (\Throwable $e) {
                // fall through to today
            }
        }

        return Carbon::now(self::TZ)->startOfDay();
    }

    /**
     * @return array{start: Carbon, end: Carbon, label: string, buckets: array<string,string>}
     */
    public function range(string $view, string $cursor): array
    {
        $view = in_array($view, self::VIEWS, true) ? $view : 'month';
        $date = $this->parseCursor($cursor);
        $buckets = [];

        switch ($view) {
            case 'day':
                $start = $date->copy()->startOfDay();
                $end = $date->copy()->endOfDay();
                for ($h = 0; $h < 24; $h++) {
                    $buckets[str_pad((string) $h, 2, '0', STR_PAD_LEFT)] = $start->copy()->setTime($h, 0)->format('g A');
                }
                $label = $start->format('D, j M Y');
                break;

            case 'week':
                $start = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
                $end = $start->copy()->addDays(6)->endOfDay();
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $buckets[$d->format('Y-m-d')] = $d->format('D j');
                }
                if ($start->year !== $end->year) {
                    $label = $start->format('j M Y') . ' – ' . $end->format('j M Y');
                } elseif ($start->month !== $end->month) {
                    $label = $start->format('j M') . ' – ' . $end->format('j M Y');
                } else {
                    $label = $start->format('j') . ' – ' . $end->format('j M Y');
                }
                break;

            case 'year':
                $start = $date->copy()->startOfYear();
                $end = $date->copy()->endOfYear();
                for ($m = $start->copy(); $m->lte($end); $m->addMonth()) {
                    $buckets[$m->format('Y-m')] = $m->format('M');
                }
                $label = $start->format('Y');
                break;

            case 'month':
            default:
                $start = $date->copy()->startOfMonth();
                $end = $date->copy()->endOfMonth();
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $buckets[$d->format('Y-m-d')] = $d->format('j');
                }
                $label = $start->format('F Y');
                break;
        }

        return ['view' => $view, 'start' => $start, 'end' => $end, 'label' => $label, 'buckets' => $buckets];
    }

    /**
     * @param  int[]     $turfIds
     * @param  string[]  $metrics
     */
    public function series(array $turfIds, string $view, string $cursor, array $metrics, string $dateBasis = 'play'): array
    {
        $range = $this->range($view, $cursor);
        $view = $range['view'];
        $dateBasis = in_array($dateBasis, self::DATE_BASES, true) ? $dateBasis : 'play';
        $metrics = array_values(array_intersect(array_keys(self::METRICS), $metrics));
        $turfIds = array_values(array_map('intval', $turfIds));

        $zero = array_fill_keys(array_keys($range['buckets']), 0);
        $series = [];
        foreach ($metrics as $m) {
            $series[$m] = $zero;
        }
        $cancellationValues = in_array('cancellations', $metrics, true) ? $zero : null;
        $distinctBookings = null;

        if ($view === 'day') {
            $this->fillDay($series, $cancellationValues, $distinctBookings, $turfIds, $range, $metrics, $dateBasis);
        } else {
            $this->fillDaily($series, $cancellationValues, $distinctBookings, $turfIds, $range, $metrics, $dateBasis);
        }

        // Normalise number types
        foreach ($series as $m => $values) {
            foreach ($values as $k => $v) {
                $series[$m][$k] = self::METRICS[$m]['unit'] === 'inr' ? round((float) $v, 2) : (int) $v;
            }
        }
        if ($cancellationValues !== null) {
            foreach ($cancellationValues as $k => $v) {
                $cancellationValues[$k] = round((float) $v, 2);
            }
        }

        $totals = [];
        foreach ($series as $m => $values) {
            $sum = array_sum($values);
            $totals[$m] = self::METRICS[$m]['unit'] === 'inr' ? round($sum, 2) : (int) $sum;
        }

        $result = [
            'range' => $range,
            'date_basis' => $dateBasis,
            'series' => $series,
            'totals' => $totals,
        ];

        if (in_array('bookings_received', $metrics, true)) {
            $result['distinct_bookings'] = (int) $distinctBookings;
        }
        if ($cancellationValues !== null) {
            $result['cancellations_value_series'] = $cancellationValues;
            $result['totals']['cancellations_value'] = round(array_sum($cancellationValues), 2);
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Week / Month / Year: GROUP BY DATE(col), roll up to months in PHP
    // ------------------------------------------------------------------

    protected function fillDaily(array &$series, ?array &$cancellationValues, ?int &$distinctBookings, array $turfIds, array $range, array $metrics, string $dateBasis): void
    {
        $start = $range['start']->toDateTimeString();
        $end = $range['end']->toDateTimeString();
        $bucketOf = $range['view'] === 'year'
            ? fn (string $d) => substr($d, 0, 7)
            : fn (string $d) => substr($d, 0, 10);

        $bookingCol = $dateBasis === 'booked' ? 'bookings.created_at' : 'booking_dates.booking_date';

        $add = function (string $metric, $rows, string $field) use (&$series, $bucketOf) {
            foreach ($rows as $row) {
                $key = $bucketOf((string) $row->d);
                if (array_key_exists($key, $series[$metric])) {
                    $series[$metric][$key] += (float) $row->{$field};
                }
            }
        };

        if (in_array('bookings_received', $metrics, true)) {
            $rows = $this->activeSessions($turfIds, $bookingCol, $start, $end)
                ->selectRaw("DATE({$bookingCol}) as d, COUNT(*) as v")
                ->groupBy(DB::raw("DATE({$bookingCol})"))
                ->get();
            $add('bookings_received', $rows, 'v');

            $distinctBookings = (int) $this->activeSessions($turfIds, $bookingCol, $start, $end)
                ->selectRaw('COUNT(DISTINCT booking_dates.booking_id) as c')
                ->value('c');
        }

        if (in_array('booking_amount', $metrics, true)) {
            $rows = $this->activeSessions($turfIds, $bookingCol, $start, $end)
                ->selectRaw("DATE({$bookingCol}) as d, SUM(booking_dates.amount) as v")
                ->groupBy(DB::raw("DATE({$bookingCol})"))
                ->get();
            $add('booking_amount', $rows, 'v');
        }

        if (in_array('slots_booked', $metrics, true)) {
            $rows = $this->activeSessions($turfIds, $bookingCol, $start, $end)
                ->join('booking_slots', 'booking_slots.booking_date_id', '=', 'booking_dates.id')
                ->where('booking_slots.status', '!=', 'cancelled')
                ->selectRaw("DATE({$bookingCol}) as d, COUNT(*) as v")
                ->groupBy(DB::raw("DATE({$bookingCol})"))
                ->get();
            $add('slots_booked', $rows, 'v');
        }

        foreach (['received_app' => ['App'], 'received_location' => self::LOCATION_METHODS] as $metric => $methods) {
            if (!in_array($metric, $metrics, true)) {
                continue;
            }
            $rows = $this->successfulPayments($turfIds, $start, $end)
                ->whereIn('payments.payment_method', $methods)
                ->selectRaw('DATE(payments.paid_at) as d, SUM(payments.amount) as v')
                ->groupBy(DB::raw('DATE(payments.paid_at)'))
                ->get();
            $add($metric, $rows, 'v');
        }

        if (in_array('cancellations', $metrics, true)) {
            $rows = $this->cancellations($turfIds, $start, $end)
                ->selectRaw('DATE(booking_cancellations.created_at) as d, COUNT(*) as v, SUM(COALESCE(booking_cancellations.gross_cancelled_amount, 0)) as amt')
                ->groupBy(DB::raw('DATE(booking_cancellations.created_at)'))
                ->get();
            $add('cancellations', $rows, 'v');
            foreach ($rows as $row) {
                $key = $bucketOf((string) $row->d);
                if (array_key_exists($key, $cancellationValues)) {
                    $cancellationValues[$key] += (float) $row->amt;
                }
            }
        }
    }

    // ------------------------------------------------------------------
    // Day: select timestamps for the one day, bucket by hour in PHP
    // ------------------------------------------------------------------

    protected function fillDay(array &$series, ?array &$cancellationValues, ?int &$distinctBookings, array $turfIds, array $range, array $metrics, string $dateBasis): void
    {
        $start = $range['start']->toDateTimeString();
        $end = $range['end']->toDateTimeString();
        $hourOf = fn ($ts) => Carbon::parse($ts, self::TZ)->format('H');

        if (array_intersect(self::BOOKING_METRICS, $metrics)) {
            $bookingCol = $dateBasis === 'booked' ? 'bookings.created_at' : 'booking_dates.booking_date';

            // One row per active slot (or one row with null slot for a session with no active slot).
            $rows = $this->activeSessions($turfIds, $bookingCol, $start, $end)
                ->leftJoin('booking_slots', function ($j) {
                    $j->on('booking_slots.booking_date_id', '=', 'booking_dates.id')
                        ->where('booking_slots.status', '!=', 'cancelled');
                })
                ->leftJoin('slots', 'slots.id', '=', 'booking_slots.slot_id')
                ->select([
                    'booking_dates.id as session_id',
                    'booking_dates.booking_id',
                    'booking_dates.amount',
                    'bookings.created_at as booked_at',
                    'booking_slots.id as booking_slot_id',
                    'slots.from_time',
                ])
                ->orderBy('booking_dates.id')
                ->orderBy('slots.from_time')
                ->orderBy('booking_slots.id')
                ->get();

            $sessions = [];
            foreach ($rows as $row) {
                $sid = $row->session_id;
                if (!isset($sessions[$sid])) {
                    $sessions[$sid] = [
                        'booking_id' => $row->booking_id,
                        'amount' => (float) $row->amount,
                        'booked_hour' => $hourOf($row->booked_at),
                        'slot_hours' => [],
                    ];
                }
                if ($row->booking_slot_id !== null) {
                    $sessions[$sid]['slot_hours'][] = $row->from_time !== null
                        ? str_pad(substr((string) $row->from_time, 0, 2), 2, '0', STR_PAD_LEFT)
                        : '00';
                }
            }

            $distinctBookings = count(array_unique(array_column($sessions, 'booking_id')));

            foreach ($sessions as $s) {
                $slotHours = $s['slot_hours'];

                if ($dateBasis === 'booked') {
                    $h = $s['booked_hour'];
                    if (isset($series['bookings_received'])) {
                        $series['bookings_received'][$h] += 1;
                    }
                    if (isset($series['booking_amount'])) {
                        $series['booking_amount'][$h] += $s['amount'];
                    }
                    if (isset($series['slots_booked'])) {
                        $series['slots_booked'][$h] += count($slotHours);
                    }
                    continue;
                }

                // Day of play: sessions have no time of their own → use the slots' from_time hours.
                $firstHour = $slotHours ? min($slotHours) : '00';
                if (isset($series['bookings_received'])) {
                    $series['bookings_received'][$firstHour] += 1;
                }
                if (isset($series['slots_booked'])) {
                    foreach ($slotHours as $h) {
                        $series['slots_booked'][$h] += 1;
                    }
                }
                if (isset($series['booking_amount'])) {
                    if (!$slotHours) {
                        $series['booking_amount'][$firstHour] += $s['amount'];
                    } else {
                        // Split evenly in paise; rounding remainder goes on the last slot.
                        $n = count($slotHours);
                        $cents = (int) round($s['amount'] * 100);
                        $share = (int) round($cents / $n);
                        foreach ($slotHours as $i => $h) {
                            $part = $i === $n - 1 ? $cents - $share * ($n - 1) : $share;
                            $series['booking_amount'][$h] += $part / 100;
                        }
                    }
                }
            }
        }

        if (isset($series['received_app']) || isset($series['received_location'])) {
            $methods = [];
            if (isset($series['received_app'])) {
                $methods[] = 'App';
            }
            if (isset($series['received_location'])) {
                $methods = array_merge($methods, self::LOCATION_METHODS);
            }
            $rows = $this->successfulPayments($turfIds, $start, $end)
                ->whereIn('payments.payment_method', $methods)
                ->select(['payments.paid_at', 'payments.amount', 'payments.payment_method'])
                ->get();
            foreach ($rows as $row) {
                $metric = $row->payment_method === 'App' ? 'received_app' : 'received_location';
                if (isset($series[$metric])) {
                    $series[$metric][$hourOf($row->paid_at)] += (float) $row->amount;
                }
            }
        }

        if (isset($series['cancellations'])) {
            $rows = $this->cancellations($turfIds, $start, $end)
                ->select(['booking_cancellations.created_at', 'booking_cancellations.gross_cancelled_amount'])
                ->get();
            foreach ($rows as $row) {
                $h = $hourOf($row->created_at);
                $series['cancellations'][$h] += 1;
                $cancellationValues[$h] += (float) $row->gross_cancelled_amount;
            }
        }
    }

    // ------------------------------------------------------------------
    // Base queries
    // ------------------------------------------------------------------

    protected function activeSessions(array $turfIds, string $col, string $start, string $end)
    {
        if ($col === 'booking_dates.booking_date') {
            // DATE column: compare with plain Y-m-d bounds (works on MySQL and SQLite).
            $start = substr($start, 0, 10);
            $end = substr($end, 0, 10);
        }

        return DB::table('booking_dates')
            ->join('bookings', 'bookings.id', '=', 'booking_dates.booking_id')
            ->whereIn('bookings.turf_id', $turfIds)
            ->where('booking_dates.status', '!=', 'Cancelled')
            ->where($col, '>=', $start)
            ->where($col, '<=', $end);
    }

    protected function successfulPayments(array $turfIds, string $start, string $end)
    {
        return DB::table('payments')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->whereIn('bookings.turf_id', $turfIds)
            ->where('payments.status', 'Success')
            ->where('payments.paid_at', '>=', $start)
            ->where('payments.paid_at', '<=', $end);
    }

    protected function cancellations(array $turfIds, string $start, string $end)
    {
        return DB::table('booking_cancellations')
            ->join('bookings', 'bookings.id', '=', 'booking_cancellations.booking_id')
            ->whereIn('bookings.turf_id', $turfIds)
            ->where('booking_cancellations.created_at', '>=', $start)
            ->where('booking_cancellations.created_at', '<=', $end);
    }
}
