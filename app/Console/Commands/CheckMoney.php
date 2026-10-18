<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Turf;
use App\Models\User;
use App\Services\MoneyAuditService;
use Illuminate\Console\Command;

class CheckMoney extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'money:check
        {--booking=* : Booking id(s) to check}
        {--owner= : Turf owner user id: checks all their bookings plus the wallet balance rule}
        {--no-gateway : Skip the live Razorpay fee/refund lookups}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit money flow, wallet balances, and GST rules for bookings and turf owners';

    /**
     * Execute the console command.
     */
    public function handle(MoneyAuditService $service): int
    {
        $bookingOptions = $this->option('booking');
        $ownerOption = $this->option('owner');
        $noGateway = (bool) $this->option('no-gateway');
        $useGateway = !$noGateway;

        if (empty($bookingOptions) && empty($ownerOption)) {
            $this->error('Usage: php artisan money:check {--booking=ID} {--owner=USER_ID} [--no-gateway]');
            $this->line('At least one of --booking or --owner must be specified.');
            return 1;
        }

        $totalPassed = 0;
        $totalFailed = 0;
        $totalSkipped = 0;

        $bookingsToCheck = collect();

        if (!empty($ownerOption)) {
            $owner = User::find($ownerOption);
            if (!$owner) {
                $this->error("Owner with ID {$ownerOption} not found.");
                return 1;
            }

            $turfIds = Turf::whereHas('location', fn ($q) => $q->where('user_id', $owner->id))->pluck('id');
            $ownerBookings = Booking::whereIn('turf_id', $turfIds)->orderBy('id')->get();
            $bookingsToCheck = $bookingsToCheck->concat($ownerBookings);
        }

        if (!empty($bookingOptions)) {
            $explicitBookings = Booking::whereIn('id', (array) $bookingOptions)->orderBy('id')->get();
            $missingIds = array_diff((array) $bookingOptions, $explicitBookings->pluck('id')->map(fn ($id) => (string) $id)->all());
            if (!empty($missingIds)) {
                $this->error('Booking(s) not found: ' . implode(', ', $missingIds));
                return 1;
            }
            $bookingsToCheck = $bookingsToCheck->concat($explicitBookings);
        }

        // Deduplicate bookings by ID
        $bookingsToCheck = $bookingsToCheck->unique('id')->values();

        // 1. Audit Bookings
        foreach ($bookingsToCheck as $booking) {
            $turfName = $booking->turf->name ?? 'Unknown Turf';
            $ref = $booking->booking_reference ?? ('#' . $booking->id);
            $this->info("Booking {$ref} (id {$booking->id}), turf {$turfName}");

            $rows = $service->checkBooking($booking, $useGateway);
            $this->renderTable($rows);

            foreach ($rows as $row) {
                if ($row['result'] === 'PASS') {
                    $totalPassed++;
                } elseif ($row['result'] === 'FAIL') {
                    $totalFailed++;
                } elseif ($row['result'] === 'SKIP') {
                    $totalSkipped++;
                }
            }
            $this->newLine();
        }

        // 2. Audit Owner if specified
        if (!empty($ownerOption) && isset($owner)) {
            $this->info("Owner {$owner->name} (id {$owner->id}) Wallet & Payout Checks");
            $ownerRows = $service->checkOwner($owner, $useGateway);
            $this->renderTable($ownerRows);

            foreach ($ownerRows as $row) {
                if ($row['result'] === 'PASS') {
                    $totalPassed++;
                } elseif ($row['result'] === 'FAIL') {
                    $totalFailed++;
                } elseif ($row['result'] === 'SKIP') {
                    $totalSkipped++;
                }
            }
            $this->newLine();
        }

        $this->line("{$totalPassed} passed, {$totalFailed} failed, {$totalSkipped} skipped");

        return $totalFailed === 0 ? 0 : 1;
    }

    /**
     * Render the check results table.
     *
     * @param array<int, array{check: string, expected: ?string, actual: ?string, result: string}> $rows
     */
    protected function renderTable(array $rows): void
    {
        $tableRows = array_map(function ($r) {
            $resultFormatted = match ($r['result']) {
                'PASS' => '<info>PASS</info>',
                'FAIL' => '<error>FAIL</error>',
                'SKIP' => '<comment>SKIP</comment>',
                default => $r['result'],
            };

            return [
                $r['check'],
                $r['expected'] ?? '—',
                $r['actual'] ?? '—',
                $resultFormatted,
            ];
        }, $rows);

        $this->table(['Check', 'Expected', 'Actual', 'Result'], $tableRows);
    }
}
