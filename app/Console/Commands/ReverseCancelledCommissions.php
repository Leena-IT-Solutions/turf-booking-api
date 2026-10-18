<?php

namespace App\Console\Commands;

use App\Models\BookingCancellation;
use App\Models\CommissionWalletTransaction;
use App\Services\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReverseCancelledCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:reverse-cancelled-commissions {--dry-run : Simulate the reversal of deducted commission and gateway fees without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reverse deducted commission and gateway charges on past cancellations back to turf owners.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Running in DRY-RUN mode. No database changes will be committed.');
        }

        $cancellations = BookingCancellation::with(['booking.turf.location.user', 'bookingDate'])->get();

        $eligible = $cancellations->filter(function ($c) {
            return !CommissionWalletTransaction::where('reference_type', BookingCancellation::class)
                ->where('reference_id', $c->id)
                ->whereIn('type', ['commission_reversal', 'gateway_charge_reversal'])
                ->exists();
        });

        if ($eligible->isEmpty()) {
            $this->info('No unreversed cancellations found. All cancellations are up to date.');
            return Command::SUCCESS;
        }

        $walletService = new WalletService();
        $tableRows = [];
        $totalCommissionBack = 0.0;
        $totalGatewayBack = 0.0;
        $processedCount = 0;

        if ($dryRun) {
            DB::beginTransaction();
            try {
                foreach ($eligible as $cancellation) {
                    $walletService->reverseDeductionsForCancellation($cancellation);
                    $freshC = $cancellation->fresh();

                    $commBack = (float) $freshC->commission_reversed_amount;
                    $gwBack = (float) $freshC->gateway_fee_reversed_amount;

                    $tableRows[] = [
                        'Cancellation ID' => $cancellation->id,
                        'Booking Ref' => $cancellation->booking?->booking_reference ?? ('#' . $cancellation->booking_id),
                        'Owner' => $cancellation->booking?->turf?->location?->user?->name ?? 'Unknown',
                        'Commission Back' => '₹' . number_format($commBack, 2),
                        'Razorpay Fee Back' => '₹' . number_format($gwBack, 2),
                    ];

                    $totalCommissionBack += $commBack;
                    $totalGatewayBack += $gwBack;
                    $processedCount++;
                }
            } finally {
                DB::rollBack();
            }
        } else {
            foreach ($eligible as $cancellation) {
                DB::transaction(function () use ($walletService, $cancellation, &$tableRows, &$totalCommissionBack, &$totalGatewayBack, &$processedCount) {
                    $walletService->reverseDeductionsForCancellation($cancellation);
                    $freshC = $cancellation->fresh();

                    $commBack = (float) $freshC->commission_reversed_amount;
                    $gwBack = (float) $freshC->gateway_fee_reversed_amount;

                    $tableRows[] = [
                        'Cancellation ID' => $cancellation->id,
                        'Booking Ref' => $cancellation->booking?->booking_reference ?? ('#' . $cancellation->booking_id),
                        'Owner' => $cancellation->booking?->turf?->location?->user?->name ?? 'Unknown',
                        'Commission Back' => '₹' . number_format($commBack, 2),
                        'Razorpay Fee Back' => '₹' . number_format($gwBack, 2),
                    ];

                    $totalCommissionBack += $commBack;
                    $totalGatewayBack += $gwBack;
                    $processedCount++;
                });
            }
        }

        $this->table(
            ['Cancellation ID', 'Booking Ref', 'Owner', 'Commission Back', 'Razorpay Fee Back'],
            $tableRows
        );

        $this->info("Total cancellations processed: {$processedCount}");
        $this->info("Total commission returned: ₹" . number_format($totalCommissionBack, 2));
        $this->info("Total Razorpay fees returned: ₹" . number_format($totalGatewayBack, 2));

        if ($dryRun) {
            $this->warn('DRY RUN COMPLETE: No changes were written to the database.');
        } else {
            $this->info('Completed successfully.');
        }

        return Command::SUCCESS;
    }
}
