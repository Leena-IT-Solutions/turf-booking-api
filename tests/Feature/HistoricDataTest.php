<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingDate;
use App\Models\BookingSlot;
use App\Models\Location;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Slot;
use App\Models\SlotCategory;
use App\Models\StaffMember;
use App\Models\Turf;
use App\Models\User;
use App\Services\HistoricDataService;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HistoricDataTest extends TestCase
{
    use RefreshDatabase;

    protected const ALL_METRICS = [
        'bookings_received', 'booking_amount', 'received_app', 'received_location', 'slots_booked', 'cancellations',
    ];

    protected User $owner;
    protected User $otherOwner;
    protected User $manager;
    protected User $customer;
    protected Turf $turfAlpha;
    protected Turf $turfBeta;
    protected Turf $turfGamma;
    protected Slot $slot6am;
    protected Slot $slot6pm;
    protected Slot $slot7pm;
    protected Slot $slot8pm;
    protected HistoricDataService $service;
    protected int $ref = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        SaasSetting::create([
            'commission_percentage' => 10.00,
            'platform_fee' => 5.00,
            'platform_fee_type' => 'fixed',
            'platform_fee_gst_percentage' => 0.00,
            'min_slots_booking' => 1,
        ]);

        $this->owner = User::factory()->create(['name' => 'Owner One', 'email' => 'owner1@example.com', 'mobile' => '9777700001']);
        $this->owner->assignRole('turf-admin');

        $this->otherOwner = User::factory()->create(['name' => 'Owner Two', 'email' => 'owner2@example.com', 'mobile' => '9777700002']);
        $this->otherOwner->assignRole('turf-admin');

        $this->manager = User::factory()->create(['name' => 'Manager M', 'email' => 'manager@example.com', 'mobile' => '9777700003']);
        $this->manager->assignRole('manager');
        StaffMember::create(['turf_admin_id' => $this->owner->id, 'user_id' => $this->manager->id, 'role' => 'manager']);

        $this->customer = User::factory()->create(['name' => 'Player P', 'email' => 'player@example.com', 'mobile' => '9777700004']);
        $this->customer->assignRole('customer');

        $loc = Location::create(['user_id' => $this->owner->id, 'name' => 'Arena One', 'address' => 'Andheri', 'city' => 'Mumbai']);
        $loc2 = Location::create(['user_id' => $this->otherOwner->id, 'name' => 'Arena Two', 'address' => 'Bandra', 'city' => 'Mumbai']);

        $this->turfAlpha = Turf::create(['location_id' => $loc->id, 'name' => 'Turf Alpha', 'type' => 'Football', 'is_active' => true]);
        $this->turfBeta = Turf::create(['location_id' => $loc->id, 'name' => 'Turf Beta', 'type' => 'Cricket', 'is_active' => true]);
        $this->turfGamma = Turf::create(['location_id' => $loc2->id, 'name' => 'Turf Gamma', 'type' => 'Football', 'is_active' => true]);

        $cat = SlotCategory::create(['name' => 'Regular']);
        $mk = fn ($name, $from, $to) => Slot::create([
            'slot_category_id' => $cat->id, 'name' => $name, 'from_time' => $from, 'to_time' => $to, 'duration' => 60,
        ]);
        $this->slot6am = $mk('6-7 AM', '06:00:00', '07:00:00');
        $this->slot6pm = $mk('6-7 PM', '18:00:00', '19:00:00');
        $this->slot7pm = $mk('7-8 PM', '19:00:00', '20:00:00');
        $this->slot8pm = $mk('8-9 PM', '20:00:00', '21:00:00');

        $this->service = app(HistoricDataService::class);

        Carbon::setTestNow(Carbon::parse('2026-10-21 12:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @param  array<int, array{0: string, 1: float, 2: Slot[]}>  $sessions  [date, amount, slots]
     * @return array{0: Booking, 1: BookingDate[]}
     */
    protected function book(Turf $turf, string $bookedAt, array $sessions): array
    {
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::parse($bookedAt, 'Asia/Kolkata'));

        $n = $this->ref++;
        $booking = Booking::create([
            'turf_id' => $turf->id,
            'user_id' => $this->customer->id,
            'booking_number' => 'BK-HIST-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'booking_reference' => 'TB-HIST-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT),
            'date_of_booking' => $sessions[0][0],
            'total_amount' => array_sum(array_column($sessions, 1)),
            'status' => 'Confirmed',
            'payment_status' => 'Paid',
            'booking_source' => 'app',
        ]);

        $dates = [];
        foreach ($sessions as [$date, $amount, $slots]) {
            $bd = BookingDate::create([
                'booking_id' => $booking->id,
                'booking_date' => $date,
                'amount' => $amount,
                'status' => 'Confirmed',
            ]);
            foreach ($slots as $slot) {
                BookingSlot::create(['booking_date_id' => $bd->id, 'slot_id' => $slot->id, 'status' => 'active']);
            }
            $dates[] = $bd;
        }

        Carbon::setTestNow($now);

        return [$booking, $dates];
    }

    protected function pay(Booking $booking, BookingDate $bd, string $method, float $amount, ?string $paidAt, string $status = 'Success'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bd->id,
            'payment_method' => $method,
            'amount' => $amount,
            'status' => $status,
            'paid_at' => $paidAt ? Carbon::parse($paidAt, 'Asia/Kolkata') : null,
        ]);
    }

    protected function cancelSession(Booking $booking, BookingDate $bd, string $at, float $gross): BookingCancellation
    {
        $now = Carbon::now();
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Kolkata'));

        $bd->update(['status' => 'Cancelled']);
        BookingSlot::where('booking_date_id', $bd->id)->update(['status' => 'cancelled']);
        $c = BookingCancellation::create([
            'booking_id' => $booking->id,
            'booking_date_id' => $bd->id,
            'cancelled_by_user_id' => $this->customer->id,
            'canceller_role' => 'customer',
            'gross_cancelled_amount' => $gross,
            'refund_status' => 'Refunded',
            'refund_amount' => $gross,
        ]);

        Carbon::setTestNow($now);

        return $c;
    }

    /**
     * Test 2 data set:
     * - Booking A made 15 Oct: session 18 Oct (₹1,000, 1 slot), session 20 Oct (₹1,000, 2 slots)
     * - App payment paid 15 Oct, Cash payment paid 18 Oct
     * - Another booking B cancelled on 16 Oct
     */
    protected function seedScenario(): array
    {
        [$a, [$a18, $a20]] = $this->book($this->turfAlpha, '2026-10-15 10:00:00', [
            ['2026-10-18', 1000.00, [$this->slot6pm]],
            ['2026-10-20', 1000.00, [$this->slot6pm, $this->slot7pm]],
        ]);
        $this->pay($a, $a18, 'App', 1000.00, '2026-10-15 10:05:00');
        $this->pay($a, $a20, 'Cash', 1000.00, '2026-10-18 18:30:00');

        [$b, [$b25]] = $this->book($this->turfAlpha, '2026-10-01 09:00:00', [
            ['2026-10-25', 500.00, [$this->slot6am]],
        ]);
        $this->cancelSession($b, $b25, '2026-10-16 11:00:00', 500.00);

        return compact('a', 'a18', 'a20', 'b', 'b25');
    }

    protected function alphaBeta(): array
    {
        return [$this->turfAlpha->id, $this->turfBeta->id];
    }

    // ------------------------------------------------------------------
    // 1. Ranges
    // ------------------------------------------------------------------

    public function test_1_ranges(): void
    {
        $day = $this->service->range('day', '2026-10-21');
        $this->assertCount(24, $day['buckets']);
        // PHP turns numeric string keys '10'..'23' into ints; '00'..'09' stay strings.
        $this->assertEquals('00', array_key_first($day['buckets']));
        $this->assertEquals('23', array_key_last($day['buckets']));
        $this->assertSame('6 AM', $day['buckets']['06']);
        $this->assertSame('Wed, 21 Oct 2026', $day['label']);

        $week = $this->service->range('week', '2026-10-21');
        $this->assertCount(7, $week['buckets']);
        $this->assertSame('2026-10-19', $week['start']->format('Y-m-d'));
        $this->assertSame('2026-10-25', $week['end']->format('Y-m-d'));
        $this->assertSame('Mon 19', $week['buckets']['2026-10-19']);
        $this->assertSame('19 – 25 Oct 2026', $week['label']);

        $feb = $this->service->range('month', '2028-02-10');
        $this->assertCount(29, $feb['buckets']);
        $this->assertSame('February 2028', $feb['label']);

        $year = $this->service->range('year', '2026-10-21');
        $this->assertCount(12, $year['buckets']);
        $this->assertSame('Jan', $year['buckets']['2026-01']);
        $this->assertSame('2026', $year['label']);

        // Every bucket present even when empty
        $s = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS);
        foreach (self::ALL_METRICS as $m) {
            $this->assertCount(31, $s['series'][$m]);
            $this->assertSame(0.0, (float) array_sum($s['series'][$m]));
        }
    }

    // ------------------------------------------------------------------
    // 2. Each metric in the right bucket (Day of play)
    // ------------------------------------------------------------------

    public function test_2_each_metric_in_the_right_bucket_day_of_play(): void
    {
        $this->seedScenario();

        $s = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS, 'play');
        $x = $s['series'];

        $this->assertSame(1, $x['bookings_received']['2026-10-18']);
        $this->assertSame(1, $x['bookings_received']['2026-10-20']);
        $this->assertSame(0, $x['bookings_received']['2026-10-15']);
        $this->assertSame(2, $s['totals']['bookings_received']);
        $this->assertSame(1, $s['distinct_bookings']);

        $this->assertEquals(1000.00, $x['booking_amount']['2026-10-18']);
        $this->assertEquals(1000.00, $x['booking_amount']['2026-10-20']);
        $this->assertEquals(0.00, $x['booking_amount']['2026-10-15']);
        $this->assertEquals(2000.00, $s['totals']['booking_amount']);

        $this->assertSame(1, $x['slots_booked']['2026-10-18']);
        $this->assertSame(2, $x['slots_booked']['2026-10-20']);

        $this->assertEquals(1000.00, $x['received_app']['2026-10-15']);
        $this->assertEquals(1000.00, $s['totals']['received_app']);
        $this->assertEquals(1000.00, $x['received_location']['2026-10-18']);
        $this->assertEquals(1000.00, $s['totals']['received_location']);

        $this->assertSame(1, $x['cancellations']['2026-10-16']);
        $this->assertSame(1, $s['totals']['cancellations']);
        $this->assertEquals(500.00, $s['totals']['cancellations_value']);

        // Totals card text
        $this->actingAs($this->owner);
        Volt::test('turf.historic-data')
            ->set('cursor', '2026-10-21')
            ->assertSee('2 sessions · 1 booking');
    }

    // ------------------------------------------------------------------
    // 3. Date basis moves only the booking metrics
    // ------------------------------------------------------------------

    public function test_3_date_basis_moves_only_booking_metrics(): void
    {
        $this->seedScenario();

        $play = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS, 'play')['series'];
        $s = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS, 'booked');
        $x = $s['series'];

        $this->assertSame(2, $x['bookings_received']['2026-10-15']);
        $this->assertEquals(2000.00, $x['booking_amount']['2026-10-15']);
        $this->assertSame(3, $x['slots_booked']['2026-10-15']);
        foreach (['2026-10-18', '2026-10-20'] as $d) {
            $this->assertSame(0, $x['bookings_received'][$d]);
            $this->assertEquals(0.00, $x['booking_amount'][$d]);
            $this->assertSame(0, $x['slots_booked'][$d]);
        }
        $this->assertSame(1, $s['distinct_bookings']);

        // Payments and cancellations stay exactly where they were
        $this->assertSame($play['received_app'], $x['received_app']);
        $this->assertSame($play['received_location'], $x['received_location']);
        $this->assertSame($play['cancellations'], $x['cancellations']);
        $this->assertEquals(1000.00, $x['received_app']['2026-10-15']);
        $this->assertEquals(1000.00, $x['received_location']['2026-10-18']);

        // Persists in the URL; unknown value falls back to play
        $this->actingAs($this->owner);
        Livewire::withQueryParams(['dateBasis' => 'booked'])->test('turf.historic-data')
            ->assertSet('dateBasis', 'booked');
        Livewire::withQueryParams(['dateBasis' => 'bogus'])->test('turf.historic-data')
            ->assertSet('dateBasis', 'play');
        Volt::test('turf.historic-data')
            ->call('setDateBasis', 'booked')->assertSet('dateBasis', 'booked')
            ->set('dateBasis', 'nonsense')->assertSet('dateBasis', 'play');
    }

    // ------------------------------------------------------------------
    // 4. Received at Location ≠ booking amount − app
    // ------------------------------------------------------------------

    public function test_4_received_at_location_is_real_money_only(): void
    {
        // Pay-at-venue booking, nothing collected yet (offline payment still Pending)
        [$b, [$bd]] = $this->book($this->turfAlpha, '2026-10-10 10:00:00', [
            ['2026-10-19', 1500.00, [$this->slot6pm]],
        ]);
        $this->pay($b, $bd, 'offline', 1500.00, null, 'Pending');

        $s = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS);

        $this->assertEquals(1500.00, $s['totals']['booking_amount']);
        $this->assertEquals(0.00, $s['totals']['received_app']);
        $this->assertEquals(0.00, $s['totals']['received_location']);
    }

    // ------------------------------------------------------------------
    // 5. Cancelled sessions excluded from booking metrics, both modes
    // ------------------------------------------------------------------

    public function test_5_cancelled_sessions_are_excluded_in_both_modes(): void
    {
        $d = $this->seedScenario();
        $this->cancelSession($d['a'], $d['a20'], '2026-10-17 15:00:00', 1000.00);

        $play = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS, 'play');
        $x = $play['series'];
        $this->assertSame(0, $x['bookings_received']['2026-10-20']);
        $this->assertEquals(0.00, $x['booking_amount']['2026-10-20']);
        $this->assertSame(0, $x['slots_booked']['2026-10-20']);
        $this->assertSame(1, $x['cancellations']['2026-10-17']);
        $this->assertSame(2, $play['totals']['cancellations']);
        $this->assertEquals(1500.00, $play['totals']['cancellations_value']);

        $booked = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS, 'booked')['series'];
        $this->assertSame(1, $booked['bookings_received']['2026-10-15']);
        $this->assertEquals(1000.00, $booked['booking_amount']['2026-10-15']);
        $this->assertSame(1, $booked['slots_booked']['2026-10-15']);
    }

    // ------------------------------------------------------------------
    // 6. Year view rolls up into months
    // ------------------------------------------------------------------

    public function test_6_year_view_rolls_up_into_months(): void
    {
        $this->seedScenario();
        [$m, [$mbd]] = $this->book($this->turfBeta, '2026-03-02 08:00:00', [
            ['2026-03-05', 700.00, [$this->slot6am, $this->slot6pm]],
        ]);
        $this->pay($m, $mbd, 'UPI', 700.00, '2026-03-05 06:10:00');

        foreach (['play', 'booked'] as $basis) {
            $year = $this->service->series($this->alphaBeta(), 'year', '2026-10-21', self::ALL_METRICS, $basis);
            $month = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', self::ALL_METRICS, $basis);

            $this->assertCount(12, $year['series']['booking_amount']);
            foreach (self::ALL_METRICS as $metric) {
                // totals equal the sum of the buckets
                $this->assertEquals(array_sum($year['series'][$metric]), $year['totals'][$metric], "{$basis}/{$metric}");
                // the October bucket equals the October month total
                $this->assertEquals($month['totals'][$metric], $year['series'][$metric]['2026-10'], "{$basis}/{$metric} Oct");
            }
            $this->assertEquals(700.00, $year['series']['booking_amount']['2026-03']);
            $this->assertSame(2, $year['series']['slots_booked']['2026-03']);
            $this->assertEquals(700.00, $year['series']['received_location']['2026-03']);
            $this->assertEquals(2700.00, $year['totals']['booking_amount']);
            $this->assertSame(2, $year['distinct_bookings']);
        }
    }

    // ------------------------------------------------------------------
    // 7. Day view by hour, all 24 hours
    // ------------------------------------------------------------------

    public function test_7_day_view_by_hour(): void
    {
        [$c, [$cbd]] = $this->book($this->turfAlpha, '2026-10-19 09:30:00', [
            ['2026-10-20', 1000.00, [$this->slot8pm, $this->slot6pm, $this->slot7pm]],
        ]);
        $this->pay($c, $cbd, 'App', 1000.00, '2026-10-20 14:15:00');

        // Day of play
        $s = $this->service->series($this->alphaBeta(), 'day', '2026-10-20', self::ALL_METRICS, 'play');
        $x = $s['series'];
        foreach (self::ALL_METRICS as $m) {
            $this->assertCount(24, $x[$m]);
        }
        $this->assertSame(1, $x['slots_booked']['18']);
        $this->assertSame(1, $x['slots_booked']['19']);
        $this->assertSame(1, $x['slots_booked']['20']);
        $this->assertSame(1, $x['bookings_received']['18']);
        $this->assertSame(1, $s['totals']['bookings_received']);
        $this->assertEquals(333.33, $x['booking_amount']['18']);
        $this->assertEquals(333.33, $x['booking_amount']['19']);
        $this->assertEquals(333.34, $x['booking_amount']['20']);
        $this->assertSame(1000.00, $s['totals']['booking_amount']);
        $this->assertEquals(1000.00, $x['received_app']['14']);

        // Booked on: booking metrics fall into the hour of bookings.created_at
        $b = $this->service->series($this->alphaBeta(), 'day', '2026-10-19', self::ALL_METRICS, 'booked');
        $this->assertSame(1, $b['series']['bookings_received']['09']);
        $this->assertEquals(1000.00, $b['series']['booking_amount']['09']);
        $this->assertSame(3, $b['series']['slots_booked']['09']);
        $this->assertEquals(0.00, $b['totals']['received_app']); // payment is on the 20th

        // Payments use paid_at hour in both modes
        $b20 = $this->service->series($this->alphaBeta(), 'day', '2026-10-20', self::ALL_METRICS, 'booked');
        $this->assertEquals(1000.00, $b20['series']['received_app']['14']);
        $this->assertSame(0, $b20['totals']['bookings_received']);
    }

    // ------------------------------------------------------------------
    // 8. Previous / Next / Today
    // ------------------------------------------------------------------

    public function test_8_previous_next_today(): void
    {
        $this->actingAs($this->owner);

        $c = Volt::test('turf.historic-data')
            ->assertSet('view', 'month')
            ->assertSet('cursor', '2026-10-21')
            ->assertSee('October 2026');

        // Next is disabled for the current period (and does nothing)
        $this->assertTrue($c->instance()->isCurrentPeriod());
        $c->call('next')->assertSet('cursor', '2026-10-21');

        $c->call('previous')
            ->assertSee('September 2026')
            ->assertSet('cursor', '2026-09-01');
        $this->assertFalse($c->instance()->isCurrentPeriod());

        $c->call('next')->assertSee('October 2026');

        // Week: previous goes back 7 days
        $c->call('today')->call('setView', 'week')->assertSee('19 – 25 Oct 2026');
        $c->call('previous')->assertSee('12 – 18 Oct 2026')->assertSet('cursor', '2026-10-12');

        // Today resets the cursor
        $c->call('previous')->call('today')->assertSet('cursor', '2026-10-21');
    }

    // ------------------------------------------------------------------
    // 9. Pills
    // ------------------------------------------------------------------

    public function test_9_pills(): void
    {
        $this->actingAs($this->owner);

        $c = Volt::test('turf.historic-data')
            ->assertSet('metrics', ['bookings_received', 'booking_amount'])
            ->assertSeeHtml('historic-total-bookings_received')
            ->assertDontSeeHtml('historic-total-slots_booked');

        $c->call('toggleMetric', 'slots_booked')
            ->assertSet('metrics', ['bookings_received', 'booking_amount', 'slots_booked'])
            ->assertSeeHtml('historic-total-slots_booked');

        $c->call('toggleMetric', 'bookings_received')
            ->call('toggleMetric', 'booking_amount')
            ->assertSet('metrics', ['slots_booked']);

        // Last metric can't be removed
        $c->call('toggleMetric', 'slots_booked')->assertSet('metrics', ['slots_booked']);
        // Unknown metric is ignored
        $c->call('toggleMetric', 'bogus')->assertSet('metrics', ['slots_booked']);

        // Only the selected metrics are computed
        $s = $this->service->series($this->alphaBeta(), 'month', '2026-10-21', ['received_app']);
        $this->assertSame(['received_app'], array_keys($s['series']));
        $this->assertSame(['received_app'], array_keys($s['totals']));
        $this->assertArrayNotHasKey('distinct_bookings', $s);
    }

    // ------------------------------------------------------------------
    // 10. Scope
    // ------------------------------------------------------------------

    public function test_10_scope(): void
    {
        $this->book($this->turfAlpha, '2026-10-05 10:00:00', [['2026-10-06', 100.00, [$this->slot6am]]]);
        $this->book($this->turfBeta, '2026-10-05 10:00:00', [['2026-10-07', 200.00, [$this->slot6am]]]);
        $this->book($this->turfGamma, '2026-10-05 10:00:00', [['2026-10-08', 400.00, [$this->slot6am]]]);

        $amount = fn ($c) => $c->viewData('data')['totals']['booking_amount'];

        // Owner, all turfs: alpha + beta, never gamma
        $this->actingAs($this->owner);
        $c = Volt::test('turf.historic-data')->assertSee('All turfs');
        $this->assertEquals(300.00, $amount($c));

        // Active turf limits the data
        session(['active_turf_id' => $this->turfAlpha->id]);
        $c = Volt::test('turf.historic-data')->assertSee('Turf Alpha');
        $this->assertEquals(100.00, $amount($c));

        // Active turf of another owner is ignored
        session(['active_turf_id' => $this->turfGamma->id]);
        $c = Volt::test('turf.historic-data');
        $this->assertEquals(300.00, $amount($c));
        session()->forget('active_turf_id');

        // Manager sees the turfs assigned to them
        $this->manager->assignedTurfs()->attach($this->turfBeta->id, ['turf_admin_id' => $this->owner->id]);
        $this->actingAs($this->manager);
        $c = Volt::test('turf.historic-data');
        $this->assertEquals(200.00, $amount($c));

        // Other owner sees only gamma
        $this->actingAs($this->otherOwner);
        $c = Volt::test('turf.historic-data');
        $this->assertEquals(400.00, $amount($c));
    }

    // ------------------------------------------------------------------
    // 11. No query growth
    // ------------------------------------------------------------------

    protected function seedBulk(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $month = str_pad((string) (($i % 9) + 1), 2, '0', STR_PAD_LEFT);
            $day = str_pad((string) (($i % 27) + 1), 2, '0', STR_PAD_LEFT);
            [$b, [$bd]] = $this->book($this->turfAlpha, "2026-{$month}-{$day} 10:00:00", [
                ["2026-{$month}-{$day}", 500.00, [$this->slot6pm, $this->slot7pm]],
            ]);
            $this->pay($b, $bd, $i % 2 ? 'App' : 'Cash', 500.00, "2026-{$month}-{$day} 10:01:00");
            if ($i % 10 === 0) {
                BookingCancellation::create([
                    'booking_id' => $b->id,
                    'cancelled_by_user_id' => $this->customer->id,
                    'canceller_role' => 'customer',
                    'gross_cancelled_amount' => 100,
                    'refund_status' => 'Refunded',
                ]);
            }
        }
    }

    protected function countQueries($component, callable $action): int
    {
        DB::flushQueryLog();
        $action($component);

        return count(DB::getQueryLog());
    }

    public function test_11_no_query_growth(): void
    {
        $this->actingAs($this->owner);
        DB::enableQueryLog();

        $this->seedBulk(10);
        $c = Volt::test('turf.historic-data')->set('metrics', self::ALL_METRICS)->set('view', 'year');
        $small = [
            'play' => $this->countQueries($c, fn ($c) => $c->call('setDateBasis', 'play')),
            'booked' => $this->countQueries($c, fn ($c) => $c->call('setDateBasis', 'booked')),
        ];

        $this->seedBulk(190);
        $c = Volt::test('turf.historic-data')->set('metrics', self::ALL_METRICS)->set('view', 'year');
        $large = [
            'play' => $this->countQueries($c, fn ($c) => $c->call('setDateBasis', 'play')),
            'booked' => $this->countQueries($c, fn ($c) => $c->call('setDateBasis', 'booked')),
        ];

        $this->assertSame($small, $large, 'Year view query count must not grow with data');
        $this->assertSame(200, Booking::count());

        // Day view uses no more queries than Month view for the same metrics
        $monthCount = $this->countQueries($c, fn ($c) => $c->call('setView', 'month'));
        $dayCount = $this->countQueries($c, fn ($c) => $c->call('setView', 'day'));
        $this->assertLessThanOrEqual($monthCount, $dayCount, "Day {$dayCount} vs Month {$monthCount}");
    }

    // ------------------------------------------------------------------
    // 12. Render
    // ------------------------------------------------------------------

    public function test_12_render_and_payload(): void
    {
        $this->seedScenario();
        $this->actingAs($this->owner);

        $this->get(route('turf.reports.historic'))->assertOk()->assertSee('Historic Data');

        Volt::test('turf.historic-data')
            ->call('toggleMetric', 'cancellations')
            ->assertSee('October 2026')
            ->assertSee('Day of play')
            ->assertSee('Booked on')
            ->assertSee('Bookings Received')
            ->assertSee('Received at Location')
            ->assertSee('₹2,000.00')
            ->assertSee('₹500.00 cancelled')
            ->assertSeeHtml('historic-chart')
            ->assertSeeHtml('historic-pill-received_app')
            ->assertSeeHtml('historic-total-booking_amount')
            ->assertDispatched('historic-chart-updated', function ($event, $params) {
                $p = $params['payload'] ?? [];

                return count($p['categories']) === 31
                    && $p['categories'][0] === '1'
                    && array_column($p['series'], 'name') === ['Bookings Received', 'Booking Amount', 'Cancellations']
                    && array_column($p['series'], 'type') === ['column', 'line', 'column']
                    && $p['series'][0]['data'][17] === 1   // 18 Oct
                    && $p['cancellationValues'][15] == 500.0; // 16 Oct
            });

        // Landing card on the Reports overview
        Volt::test('turf.reports-manager')->call('switchTab', 'overview')
            ->assertSeeHtml(route('turf.reports.historic'));
    }
}
