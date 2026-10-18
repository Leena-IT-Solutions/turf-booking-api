<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CommissionInvoice;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfSetting;
use App\Models\User;
use App\Support\IndianStates;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommissionInvoiceService
{
    /**
     * Generate commission invoices for all eligible turfs for a given closed month.
     *
     * @return CommissionInvoice[]
     */
    public function generateForMonth(Carbon $month, ?User $generatedBy = null): array
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        // Invoices can only be generated for closed past months
        if ($startOfMonth->gte(now()->startOfMonth())) {
            throw new InvalidArgumentException('Invoices can only be generated for closed past months.');
        }

        $turfIds = CommissionWalletTransaction::query()
            ->join('payments', 'payments.id', '=', 'commission_wallet_transactions.reference_id')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('commission_wallet_transactions.type', 'commission_debit')
            ->where('commission_wallet_transactions.reference_type', Payment::class)
            ->whereBetween('commission_wallet_transactions.created_at', [$startOfMonth, $endOfMonth])
            ->distinct()
            ->pluck('bookings.turf_id');

        $created = [];

        foreach ($turfIds as $turfId) {
            $turf = Turf::with(['location.user', 'turfSetting'])->find($turfId);
            if ($turf) {
                $invoice = $this->generateForTurf($turf, $month, $generatedBy);
                if ($invoice) {
                    $created[] = $invoice;
                }
            }
        }

        return $created;
    }

    /**
     * Generate a single monthly commission invoice for a specific turf.
     */
    public function generateForTurf(Turf $turf, Carbon $month, ?User $generatedBy = null): ?CommissionInvoice
    {
        $startOfMonth = $month->copy()->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();

        if ($startOfMonth->gte(now()->startOfMonth())) {
            throw new InvalidArgumentException('Invoices can only be generated for closed past months.');
        }

        $periodStart = $startOfMonth->toDateString();
        $periodEnd = $endOfMonth->toDateString();

        // 1. Idempotency check
        if (CommissionInvoice::where('turf_id', $turf->id)->where('period_start', $periodStart)->exists()) {
            return null;
        }

        // 2. Query transactions
        $transactions = CommissionWalletTransaction::query()
            ->join('payments', 'payments.id', '=', 'commission_wallet_transactions.reference_id')
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('commission_wallet_transactions.type', 'commission_debit')
            ->where('commission_wallet_transactions.reference_type', Payment::class)
            ->whereBetween('commission_wallet_transactions.created_at', [$startOfMonth, $endOfMonth])
            ->where('bookings.turf_id', $turf->id)
            ->select('commission_wallet_transactions.*')
            ->orderBy('commission_wallet_transactions.created_at')
            ->get();

        // 3. Skip if no commission rows
        if ($transactions->isEmpty()) {
            return null;
        }

        $paymentIds = $transactions->pluck('reference_id')->unique()->filter();
        $payments = Payment::with('booking')->whereIn('id', $paymentIds)->get()->keyBy('id');

        $saasSetting = SaasSetting::first();
        $isGstInvoice = (bool)($saasSetting?->is_gst_billing_active ?? false);

        // 4. Build line items
        $lines = [];

        foreach ($transactions as $tx) {
            $payment = $payments->get($tx->reference_id);
            $booking = $payment?->booking;
            $ledgerTotal = round(abs((float)$tx->amount), 2);

            if (!$isGstInvoice) {
                $taxable = $ledgerTotal;
                $cgst = 0.00;
                $sgst = 0.00;
                $igst = 0.00;
            } else {
                $bTaxable = (float)($booking?->commission_amount ?? 0);
                $bCgst = (float)($booking?->commission_cgst_amount ?? 0);
                $bSgst = (float)($booking?->commission_sgst_amount ?? 0);
                $bIgst = (float)($booking?->commission_igst_amount ?? 0);
                $bSum = round($bTaxable + $bCgst + $bSgst + $bIgst, 2);

                if (abs($bSum - $ledgerTotal) <= 0.01 && $bSum > 0) {
                    $taxable = $bTaxable;
                    $cgst = $bCgst;
                    $sgst = $bSgst;
                    $igst = $bIgst;
                } else {
                    $taxable = (float)($payment?->commission_amount ?? 0);
                    $pGst = (float)($payment?->commission_gst_amount ?? 0);
                    $isInterState = (float)($booking?->commission_igst_amount ?? 0) > 0;
                    if ($isInterState) {
                        $igst = $pGst;
                        $cgst = 0.00;
                        $sgst = 0.00;
                    } else {
                        $cgst = round($pGst / 2, 2);
                        $sgst = round($pGst - $cgst, 2);
                        $igst = 0.00;
                    }
                }
            }

            $lines[] = [
                'booking_id' => $booking?->id,
                'booking_reference' => $booking?->booking_reference ?? ('#' . ($booking?->id ?? 'N/A')),
                'charged_at' => $tx->created_at->toDateTimeString(),
                'booking_amount' => (float)($booking?->total_amount ?? 0),
                'commission_rate' => (float)($booking?->commission_rate ?? 0),
                'taxable' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
                'total' => $ledgerTotal,
            ];
        }

        $linesCollection = collect($lines);
        $taxableAmount = round($linesCollection->sum('taxable'), 2);
        $cgstAmount = round($linesCollection->sum('cgst'), 2);
        $sgstAmount = round($linesCollection->sum('sgst'), 2);
        $igstAmount = round($linesCollection->sum('igst'), 2);
        $totalAmount = round($linesCollection->sum('total'), 2);
        $isInterState = $igstAmount > 0;

        // 7. Snapshots
        $supplierSnapshot = [
            'company_name' => $saasSetting?->company_name ?? config('app.name'),
            'company_address' => $saasSetting?->company_address ?? '',
            'company_email' => $saasSetting?->company_email ?? '',
            'company_phone' => $saasSetting?->company_phone ?? '',
            'state' => $saasSetting?->state ?? 'Maharashtra',
            'state_code' => $saasSetting?->state_code ?? '27',
            'gst_number' => $saasSetting?->gst_number ?? '',
            'commission_gst_sac' => $saasSetting?->commission_gst_sac ?? '998599',
            'commission_gst_percentage' => (float)($saasSetting?->commission_gst_percentage ?? 18),
        ];

        $turfSetting = $turf->turfSetting ?? TurfSetting::where('turf_id', $turf->id)->first();
        $recipientName = $turfSetting?->company_name ?: ($turf->name ?: ($turf->location?->user?->name ?: 'Valued Turf Partner'));

        $addrParts = array_filter([
            $turfSetting?->address,
            $turfSetting?->city,
            $turfSetting?->pincode ? ('- ' . $turfSetting->pincode) : null,
        ]);
        $recipientAddress = !empty($addrParts) ? implode(', ', $addrParts) : ($turf->location?->address ?? '');

        $recipientGstin = ($turfSetting?->is_gst_billing_active && !empty($turfSetting?->gst_number))
            ? $turfSetting->gst_number
            : null;

        $turfStateCode = GstStateResolver::turfStateCode($turf);
        $turfStateName = IndianStates::name($turfStateCode) ?? $turfSetting?->state;

        $recipientSnapshot = [
            'company_name' => $recipientName,
            'turf_name' => $turf->name,
            'address' => $recipientAddress,
            'state' => $turfStateName,
            'state_code' => $turfStateCode,
            'gst_number' => $recipientGstin,
        ];

        // 8. DB Transaction and creation
        try {
            return DB::transaction(function () use (
                $turf, $periodStart, $periodEnd, $isGstInvoice, $isInterState,
                $taxableAmount, $cgstAmount, $sgstAmount, $igstAmount, $totalAmount,
                $lines, $supplierSnapshot, $recipientSnapshot, $endOfMonth, $generatedBy
            ) {
                $exists = CommissionInvoice::where('turf_id', $turf->id)
                    ->where('period_start', $periodStart)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    return null;
                }

                $invoiceNumber = CommissionInvoice::generateInvoiceNumber($endOfMonth);

                return CommissionInvoice::create([
                    'invoice_number' => $invoiceNumber,
                    'turf_id' => $turf->id,
                    'owner_user_id' => $turf->location?->user_id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'is_gst_invoice' => $isGstInvoice,
                    'is_inter_state' => $isInterState,
                    'taxable_amount' => $taxableAmount,
                    'cgst_amount' => $cgstAmount,
                    'sgst_amount' => $sgstAmount,
                    'igst_amount' => $igstAmount,
                    'total_amount' => $totalAmount,
                    'line_count' => count($lines),
                    'line_items' => $lines,
                    'supplier_snapshot' => $supplierSnapshot,
                    'recipient_snapshot' => $recipientSnapshot,
                    'generated_at' => now(),
                    'generated_by_user_id' => $generatedBy?->id,
                ]);
            });
        } catch (QueryException $e) {
            // Error code 23000: duplicate entry constraint violation
            if ($e->getCode() == 23000) {
                return null;
            }
            throw $e;
        }
    }
}
