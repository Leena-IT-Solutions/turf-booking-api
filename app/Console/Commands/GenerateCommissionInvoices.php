<?php

namespace App\Console\Commands;

use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Services\CommissionInvoiceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;

class GenerateCommissionInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commission:generate-invoices {--month= : The month to generate invoices for (YYYY-MM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly commission tax invoices for all eligible turfs';

    /**
     * Execute the console command.
     */
    public function handle(CommissionInvoiceService $service): int
    {
        $monthOption = $this->option('month');

        if ($monthOption) {
            try {
                // "!" pins the unparsed day to the 1st; without it PHP borrows today's day, so
                // "2026-09" parsed on the 31st overflows to 1 October.
                $month = Carbon::createFromFormat('!Y-m', trim($monthOption))->startOfMonth();
            } catch (Exception $e) {
                $this->error("Invalid month format: {$monthOption}. Expected format: YYYY-MM (e.g. 2026-09).");
                return 1;
            }
        } else {
            // NoOverflow: plain subMonth() on 31 March lands on 3 March, not February.
            $month = now()->subMonthNoOverflow()->startOfMonth();
        }

        if ($month->gte(now()->startOfMonth())) {
            $this->error('Invoices can only be generated for closed past months.');
            return 1;
        }

        $formattedMonth = $month->format('F Y');
        $this->info("Generating commission invoices for {$formattedMonth} ({$month->format('Y-m')})...");

        // Count total eligible turfs
        $totalTurfsWithCommission = CommissionWalletTransaction::query()
            ->join('payments', 'payments.id', '=', 'commission_wallet_transactions.reference_id')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('commission_wallet_transactions.type', 'commission_debit')
            ->where('commission_wallet_transactions.reference_type', Payment::class)
            ->whereBetween('commission_wallet_transactions.created_at', [
                $month->copy()->startOfMonth(),
                $month->copy()->endOfMonth(),
            ])
            ->distinct()
            ->count('bookings.turf_id');

        try {
            $created = $service->generateForMonth($month);
            $createdCount = count($created);
            $skippedCount = max(0, $totalTurfsWithCommission - $createdCount);

            $this->info("Completed: {$createdCount} invoice(s) generated, {$skippedCount} skipped as already existing.");
            return 0;
        } catch (Exception $e) {
            $this->error("Error generating commission invoices: " . $e->getMessage());
            return 1;
        }
    }
}
