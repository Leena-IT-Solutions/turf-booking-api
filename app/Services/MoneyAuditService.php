<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingCancellation;
use App\Models\BookingReschedule;
use App\Models\CommissionWalletTransaction;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use App\Models\Turf;
use App\Models\TurfPayout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class MoneyAuditService
{
    /**
     * Audit a single booking against all financial and GST business rules.
     *
     * @return array<int, array{check: string, expected: ?string, actual: ?string, result: 'PASS'|'FAIL'|'SKIP'}>
     */
    public function checkBooking(Booking $b, bool $useGateway = true): array
    {
        $b->loadMissing([
            'bookingDates.bookingSlots',
            'bookingDates.reschedules',
            'payments.paymentGateway',
            'turf.turfSetting',
            'turf.location.user',
            'bookingCancellations',
        ]);

        $turf = $b->turf;
        $saas = SaasSetting::first();
        $rows = [];

        // Wallet transactions of this booking (reference_type = Payment, reference_id in booking's payment ids)
        $paymentIds = $b->payments->pluck('id')->all();
        $walletRows = CommissionWalletTransaction::where('reference_type', Payment::class)
            ->whereIn('reference_id', $paymentIds)
            ->get();

        // -------------------------------------------------------------
        // Pricing and GST
        // -------------------------------------------------------------

        // 1. total_amount = sum(bookingDates.amount)
        $sumDates = round((float) $b->bookingDates->sum('amount'), 2);
        $totalAmount = round((float) $b->total_amount, 2);
        $pass1 = abs($totalAmount - $sumDates) <= 0.01;
        $rows[] = [
            'check' => '1. total_amount = sum(bookingDates.amount)',
            'expected' => number_format($sumDates, 2),
            'actual' => number_format($totalAmount, 2),
            'result' => $pass1 ? 'PASS' : 'FAIL',
        ];

        // 2. total_amount = taxable_amount + turf_gst_amount + platform_fee + platform_fee_gst
        $taxable = (float) $b->taxable_amount;
        $turfGst = (float) $b->turf_gst_amount;
        $platformFee = (float) $b->platform_fee;
        $platformFeeGst = (float) $b->platform_fee_gst;
        $expectedSum = round($taxable + $turfGst + $platformFee + $platformFeeGst, 2);
        $pass2 = abs($totalAmount - $expectedSum) <= 0.01;
        $rows[] = [
            'check' => '2. total_amount = taxable + turf GST + platform fee + platform fee GST',
            'expected' => number_format($expectedSum, 2),
            'actual' => number_format($totalAmount, 2),
            'result' => $pass2 ? 'PASS' : 'FAIL',
        ];

        // 3. turf_cgst_amount + turf_sgst_amount = turf_gst_amount
        $turfCgst = (float) $b->turf_cgst_amount;
        $turfSgst = (float) $b->turf_sgst_amount;
        $turfGstSum = round($turfCgst + $turfSgst, 2);
        $pass3 = abs($turfGstSum - $turfGst) <= 0.01;
        $rows[] = [
            'check' => '3. turf_cgst + turf_sgst = turf_gst_amount',
            'expected' => number_format($turfGst, 2),
            'actual' => number_format($turfGstSum, 2),
            'result' => $pass3 ? 'PASS' : 'FAIL',
        ];

        // 4. Platform fee GST: platform_fee_cgst + platform_fee_sgst + platform_fee_igst = platform_fee_gst
        $pfCgst = (float) $b->platform_fee_cgst;
        $pfSgst = (float) $b->platform_fee_sgst;
        $pfIgst = (float) $b->platform_fee_igst;
        $pfGstSum = round($pfCgst + $pfSgst + $pfIgst, 2);
        $pass4 = abs($pfGstSum - $platformFeeGst) <= 0.01;
        $rows[] = [
            'check' => '4. platform_fee_cgst + sgst + igst = platform_fee_gst',
            'expected' => number_format($platformFeeGst, 2),
            'actual' => number_format($pfGstSum, 2),
            'result' => $pass4 ? 'PASS' : 'FAIL',
        ];

        // 5. Platform fee GST type (uses the turf's current state)
        if ($platformFeeGst <= 0.001) {
            $rows[] = [
                'check' => '5. Platform fee GST type (uses the turf\'s current state)',
                'expected' => null,
                'actual' => 'No platform fee GST',
                'result' => 'SKIP',
            ];
        } else {
            $isIntra = $turf ? GstStateResolver::isIntraState($turf, $saas) : true;
            if ($isIntra) {
                $expectedType = 'CGST+SGST > 0, IGST = 0';
                $pass5 = ($pfIgst == 0.0) && (($pfCgst + $pfSgst) > 0.0);
            } else {
                $expectedType = 'IGST > 0, CGST=SGST = 0';
                $pass5 = ($pfCgst == 0.0) && ($pfSgst == 0.0) && ($pfIgst > 0.0);
            }
            $actualType = "CGST={$pfCgst}, SGST={$pfSgst}, IGST={$pfIgst}";
            $rows[] = [
                'check' => '5. Platform fee GST type (uses the turf\'s current state)',
                'expected' => $expectedType,
                'actual' => $actualType,
                'result' => $pass5 ? 'PASS' : 'FAIL',
            ];
        }

        // 6. Commission GST: commission_cgst_amount + commission_sgst_amount + commission_igst_amount = commission_gst_amount
        $commGst = (float) $b->commission_gst_amount;
        $commCgst = (float) $b->commission_cgst_amount;
        $commSgst = (float) $b->commission_sgst_amount;
        $commIgst = (float) $b->commission_igst_amount;
        $commGstSum = round($commCgst + $commSgst + $commIgst, 2);
        $pass6 = abs($commGstSum - $commGst) <= 0.01;
        $rows[] = [
            'check' => '6. commission_cgst + sgst + igst = commission_gst_amount',
            'expected' => number_format($commGst, 2),
            'actual' => number_format($commGstSum, 2),
            'result' => $pass6 ? 'PASS' : 'FAIL',
        ];

        // 7. Commission GST type (uses the turf's current state)
        if ($commGst <= 0.001) {
            $rows[] = [
                'check' => '7. Commission GST type (uses the turf\'s current state)',
                'expected' => null,
                'actual' => 'No commission GST',
                'result' => 'SKIP',
            ];
        } else {
            $isIntra = $turf ? GstStateResolver::isIntraState($turf, $saas) : true;
            if ($isIntra) {
                $expectedType = 'CGST+SGST > 0, IGST = 0';
                $pass7 = ($commIgst == 0.0) && (($commCgst + $commSgst) > 0.0);
            } else {
                $expectedType = 'IGST > 0, CGST=SGST = 0';
                $pass7 = ($commCgst == 0.0) && ($commSgst == 0.0) && ($commIgst > 0.0);
            }
            $actualType = "CGST={$commCgst}, SGST={$commSgst}, IGST={$commIgst}";
            $rows[] = [
                'check' => '7. Commission GST type (uses the turf\'s current state)',
                'expected' => $expectedType,
                'actual' => $actualType,
                'result' => $pass7 ? 'PASS' : 'FAIL',
            ];
        }

        // 8. For each booking date, taxable_amount + turf_gst_amount <= amount
        $invalidDates = [];
        foreach ($b->bookingDates as $bDate) {
            $dateTaxable = (float) ($bDate->taxable_amount ?? 0);
            $dateTurfGst = (float) ($bDate->turf_gst_amount ?? 0);
            $dateAmt = (float) $bDate->amount;
            if (round($dateTaxable + $dateTurfGst - $dateAmt, 2) > 0.01) {
                $invalidDates[] = "#{$bDate->id} (taxable+gst=" . round($dateTaxable + $dateTurfGst, 2) . " > amt={$dateAmt})";
            }
        }
        $pass8 = empty($invalidDates);
        $rows[] = [
            'check' => '8. Each date taxable + turf GST <= date amount',
            'expected' => '<= date.amount for all dates',
            'actual' => $pass8 ? 'All dates valid' : implode(', ', $invalidDates),
            'result' => $pass8 ? 'PASS' : 'FAIL',
        ];

        // -------------------------------------------------------------
        // Payments
        // -------------------------------------------------------------

        $successAppPayments = $b->payments->where('status', 'Success')->where('payment_method', 'App');

        // 9. Every Success payment with payment_method = 'App' has a paymentGateway row with non-empty gateway_payment_id
        if ($successAppPayments->isEmpty()) {
            $rows[] = [
                'check' => '9. Online payments have gateway payment ID',
                'expected' => null,
                'actual' => 'No online payment',
                'result' => 'SKIP',
            ];
        } else {
            $missingGateway = [];
            foreach ($successAppPayments as $p) {
                if (!$p->paymentGateway || empty($p->paymentGateway->gateway_payment_id)) {
                    $missingGateway[] = "Payment #{$p->id}";
                }
            }
            $pass9 = empty($missingGateway);
            $rows[] = [
                'check' => '9. Online payments have gateway payment ID',
                'expected' => 'All have gateway_payment_id',
                'actual' => $pass9 ? 'All present' : 'Missing on ' . implode(', ', $missingGateway),
                'result' => $pass9 ? 'PASS' : 'FAIL',
            ];
        }

        // 10. No gateway_payment_id belongs to two different bookings
        $gatewayPaymentIds = $successAppPayments->map(fn ($p) => $p->paymentGateway?->gateway_payment_id)->filter()->unique()->all();
        if (empty($gatewayPaymentIds)) {
            $rows[] = [
                'check' => '10. Gateway payment ID is unique to this booking',
                'expected' => null,
                'actual' => 'No online payment',
                'result' => 'SKIP',
            ];
        } else {
            $reusedBookings = PaymentGateway::whereIn('gateway_payment_id', $gatewayPaymentIds)
                ->join('payments', 'payments.id', '=', 'payment_gateways.payment_id')
                ->where('payments.booking_id', '!=', $b->id)
                ->pluck('payments.booking_id')
                ->unique()
                ->all();
            $pass10 = empty($reusedBookings);
            $rows[] = [
                'check' => '10. Gateway payment ID is unique to this booking',
                'expected' => 'Unique to booking #' . $b->id,
                'actual' => $pass10 ? 'Unique' : 'Reused in booking(s): ' . implode(', ', $reusedBookings),
                'result' => $pass10 ? 'PASS' : 'FAIL',
            ];
        }

        // 11. Razorpay fee, unless --no-gateway
        if (!$useGateway) {
            $rows[] = [
                'check' => '11. Razorpay fee and payment match gateway',
                'expected' => null,
                'actual' => 'Skipped by --no-gateway',
                'result' => 'SKIP',
            ];
        } elseif (empty($gatewayPaymentIds)) {
            $rows[] = [
                'check' => '11. Razorpay fee and payment match gateway',
                'expected' => null,
                'actual' => 'No online payment',
                'result' => 'SKIP',
            ];
        } else {
            [$key, $secret] = RazorpayPaymentVerifier::credentials();
            $gatewaySkip = null;
            $feeFailures = [];

            $grouped = $successAppPayments->groupBy(fn ($p) => $p->paymentGateway?->gateway_payment_id);
            foreach ($grouped as $gwId => $groupPayments) {
                if (!$gwId) {
                    continue;
                }
                try {
                    $resp = Http::withBasicAuth($key, $secret)->get("https://api.razorpay.com/v1/payments/{$gwId}");
                    if (!$resp->successful()) {
                        $gatewaySkip = 'Gateway returned HTTP ' . $resp->status();
                        break;
                    }
                    $payload = $resp->json();
                    $rzpFee = round(((float) ($payload['fee'] ?? 0)) / 100, 2);
                    $rzpAmt = round(((float) ($payload['amount'] ?? 0)) / 100, 2);
                    $rzpStatus = $payload['status'] ?? '';

                    $storedFee = round((float) $groupPayments->sum(fn ($p) => (float) $p->gateway_charge_amount + (float) $p->gateway_tax_amount), 2);
                    $storedAmt = round((float) $groupPayments->sum('amount'), 2);

                    if (abs($rzpFee - $storedFee) > 0.01) {
                        $feeFailures[] = "{$gwId}: fee expected ₹{$rzpFee} != actual ₹{$storedFee}";
                    }
                    if (abs($rzpAmt - $storedAmt) > 0.01) {
                        $feeFailures[] = "{$gwId}: amount expected ₹{$rzpAmt} != actual ₹{$storedAmt}";
                    }
                    if (!in_array($rzpStatus, ['captured', 'refunded'], true)) {
                        $feeFailures[] = "{$gwId}: status {$rzpStatus} not captured/refunded";
                    }
                } catch (\Throwable $e) {
                    $gatewaySkip = 'Gateway error: ' . $e->getMessage();
                    break;
                }
            }

            if ($gatewaySkip !== null) {
                $rows[] = [
                    'check' => '11. Razorpay fee and payment match gateway',
                    'expected' => null,
                    'actual' => $gatewaySkip,
                    'result' => 'SKIP',
                ];
            } else {
                $pass11 = empty($feeFailures);
                $rows[] = [
                    'check' => '11. Razorpay fee and payment match gateway',
                    'expected' => 'Razorpay fee, amount and captured/refunded status match',
                    'actual' => $pass11 ? 'Matched Razorpay records' : implode('; ', $feeFailures),
                    'result' => $pass11 ? 'PASS' : 'FAIL',
                ];
            }
        }

        // 12. Payment status: sum(Success amount) - sum(refunded_amount) <= total_amount
        $successPayments = $b->payments->where('status', 'Success');
        $netPaid = round((float) $successPayments->sum(fn ($p) => (float) $p->amount - (float) $p->refunded_amount), 2);
        $pass12 = $netPaid <= ($totalAmount + 0.01);
        $rows[] = [
            'check' => '12. Payments do not exceed total_amount',
            'expected' => '<= ' . number_format($totalAmount, 2),
            'actual' => number_format($netPaid, 2),
            'result' => $pass12 ? 'PASS' : 'FAIL',
        ];

        // -------------------------------------------------------------
        // Deductions: posted once at payment
        // -------------------------------------------------------------
        $settled = $b->payments->whereNotNull('deductions_settled_at')->isNotEmpty();

        // 13. Platform fee: sum(platform_fee_debit) = -(platform_fee + platform_fee_gst) if settled, else 0
        $pfDebits = round((float) $walletRows->where('type', 'platform_fee_debit')->sum('amount'), 2);
        $expectedPfDebit = $settled ? -round($platformFee + $platformFeeGst, 2) : 0.00;
        $pass13 = abs($pfDebits - $expectedPfDebit) <= 0.01;
        $rows[] = [
            'check' => '13. Platform fee deducted once',
            'expected' => number_format($expectedPfDebit, 2),
            'actual' => number_format($pfDebits, 2),
            'result' => $pass13 ? 'PASS' : 'FAIL',
        ];

        // 14. Commission: sum(commission_debit) = -(commission_amount + commission_gst_amount) if settled, else 0
        $commDebits = round((float) $walletRows->where('type', 'commission_debit')->sum('amount'), 2);
        $bookingCommTotal = (float) $b->commission_amount + (float) $b->commission_gst_amount;
        $expectedCommDebit = $settled ? -round($bookingCommTotal, 2) : 0.00;
        $pass14 = abs($commDebits - $expectedCommDebit) <= 0.01;
        $rows[] = [
            'check' => '14. Commission deducted once',
            'expected' => number_format($expectedCommDebit, 2),
            'actual' => number_format($commDebits, 2),
            'result' => $pass14 ? 'PASS' : 'FAIL',
        ];

        // 15. Gateway: sum(gateway_charge_debit) = -sum(gateway_charge + gateway_tax) over online settled payments
        $gwDebits = round((float) $walletRows->where('type', 'gateway_charge_debit')->sum('amount'), 2);
        $expectedGwDebit = -round((float) $successAppPayments->whereNotNull('deductions_settled_at')
            ->sum(fn ($p) => (float) $p->gateway_charge_amount + (float) $p->gateway_tax_amount), 2);
        $pass15 = abs($gwDebits - $expectedGwDebit) <= 0.01;
        $rows[] = [
            'check' => '15. Gateway fee deducted once',
            'expected' => number_format($expectedGwDebit, 2),
            'actual' => number_format($gwDebits, 2),
            'result' => $pass15 ? 'PASS' : 'FAIL',
        ];

        // 16. No duplicates: at most one platform_fee_debit, commission_debit per booking, and gateway_charge_debit per payment
        $pfCount = $walletRows->where('type', 'platform_fee_debit')->count();
        $commCount = $walletRows->where('type', 'commission_debit')->count();
        $gwGrouped = $walletRows->where('type', 'gateway_charge_debit')->groupBy('reference_id');
        $maxGwPerPayment = $gwGrouped->map->count()->max() ?? 0;

        $dupFailures = [];
        if ($pfCount > 1) {
            $dupFailures[] = "{$pfCount} platform_fee_debit rows";
        }
        if ($commCount > 1) {
            $dupFailures[] = "{$commCount} commission_debit rows";
        }
        if ($maxGwPerPayment > 1) {
            $dupFailures[] = 'Multiple gateway_charge_debit rows on a single payment';
        }

        $pass16 = empty($dupFailures);
        $rows[] = [
            'check' => '16. No duplicate deduction rows',
            'expected' => 'At most 1 of each deduction row',
            'actual' => $pass16 ? 'No duplicates' : implode(', ', $dupFailures),
            'result' => $pass16 ? 'PASS' : 'FAIL',
        ];

        // -------------------------------------------------------------
        // Credit to the turf: released when session date arrives
        // -------------------------------------------------------------
        $walletService = app(WalletService::class);
        $credited = round((float) $walletRows->whereIn('type', ['payment_credit', 'refund_adjustment'])->sum('amount'), 2);
        $todayStr = Carbon::today()->toDateString();
        $hasFutureActive = $b->bookingDates->where('status', '!=', 'Cancelled')->where('booking_date', '>', $todayStr)->isNotEmpty();
        $hasPendingCancel = $b->bookingCancellations->whereIn('refund_status', ['Pending', 'Pending Resolution'])->isNotEmpty();
        $entitlement = $walletService->turfOnlineEntitlement($b);
        $pendingCredit = $walletService->pendingCredit($b);

        if (!$hasFutureActive && !$hasPendingCancel) {
            $pass17 = abs($credited - $entitlement) <= 0.01 && $pendingCredit <= 0.01;
            $rows[] = [
                'check' => '17. Turf credit matches entitlement',
                'expected' => number_format($entitlement, 2) . ' (pending: 0.00)',
                'actual' => number_format($credited, 2) . ' (pending: ' . number_format($pendingCredit, 2) . ')',
                'result' => $pass17 ? 'PASS' : 'FAIL',
            ];
        } else {
            $pass17 = $credited <= ($entitlement + 0.01);
            $rows[] = [
                'check' => '17. Turf credit matches entitlement (sessions still upcoming)',
                'expected' => '<= ' . number_format($entitlement, 2),
                'actual' => number_format($credited, 2) . ' (pending: ' . number_format($pendingCredit, 2) . ')',
                'result' => $pass17 ? 'PASS' : 'FAIL',
            ];
        }

        // 18. Offline money never reaches the wallet
        $offlinePaymentIds = $b->payments->where('payment_method', '!=', 'App')->pluck('id')->all();
        $offlineWalletCredits = CommissionWalletTransaction::where('reference_type', Payment::class)
            ->whereIn('reference_id', $offlinePaymentIds)
            ->whereIn('type', ['payment_credit', 'refund_adjustment'])
            ->get();
        $offlineNonZero = $offlineWalletCredits->filter(fn ($tx) => abs((float) $tx->amount) > 0.01);

        $offlineRecords = $walletRows->where('type', 'offline_booking_record');
        $nonZeroRecords = $offlineRecords->filter(fn ($tx) => abs((float) $tx->amount) > 0.01);

        $offlineFails = [];
        if ($offlineNonZero->isNotEmpty()) {
            $offlineFails[] = $offlineNonZero->count() . ' credit/refund rows on offline payments';
        }
        if ($nonZeroRecords->isNotEmpty()) {
            $offlineFails[] = $nonZeroRecords->count() . ' non-zero offline_booking_record rows';
        }

        $pass18 = empty($offlineFails);
        $rows[] = [
            'check' => '18. Offline payments do not credit wallet',
            'expected' => '0 offline credits, offline records amount = 0',
            'actual' => $pass18 ? 'No offline credits' : implode(', ', $offlineFails),
            'result' => $pass18 ? 'PASS' : 'FAIL',
        ];

        // -------------------------------------------------------------
        // Cancellations
        // -------------------------------------------------------------
        if ($b->bookingCancellations->isEmpty()) {
            for ($cCheck = 19; $cCheck <= 23; $cCheck++) {
                $labels = [
                    19 => '19. Cancellation refund does not exceed paid amount',
                    20 => '20. Cancellation turf fee GST split',
                    21 => '21. Cancellation SaaS fee GST split and type',
                    22 => '22. Cancellation refund taxable and GST sum',
                    23 => '23. Cancellation refund matches Razorpay refund',
                ];
                $rows[] = [
                    'check' => $labels[$cCheck],
                    'expected' => null,
                    'actual' => 'No cancellations',
                    'result' => 'SKIP',
                ];
            }
        } else {
            // Check 19
            $c19Fails = [];
            foreach ($b->bookingCancellations as $c) {
                $refAmt = (float) $c->refund_amount;
                $datePayments = $b->payments->where('booking_date_id', $c->booking_date_id)->where('status', 'Success');
                if ($datePayments->isEmpty()) {
                    $datePayments = $b->payments->where('status', 'Success');
                }
                $paidAmt = (float) $datePayments->sum('amount');
                if ($refAmt < 0.0 || $refAmt > ($paidAmt + 0.01)) {
                    $c19Fails[] = "Cancel #{$c->id}: refund {$refAmt} not in [0, {$paidAmt}]";
                }
            }
            $pass19 = empty($c19Fails);
            $rows[] = [
                'check' => '19. Cancellation refund does not exceed paid amount',
                'expected' => '0 <= refund <= date paid amount',
                'actual' => $pass19 ? 'Valid' : implode(', ', $c19Fails),
                'result' => $pass19 ? 'PASS' : 'FAIL',
            ];

            // Check 20: turf_fee_cgst_amount + turf_fee_sgst_amount = turf_fee_gst_amount
            $c20Fails = [];
            foreach ($b->bookingCancellations as $c) {
                $cgst = (float) $c->turf_fee_cgst_amount;
                $sgst = (float) $c->turf_fee_sgst_amount;
                $gst = (float) $c->turf_fee_gst_amount;
                if (abs(($cgst + $sgst) - $gst) > 0.01) {
                    $c20Fails[] = "Cancel #{$c->id}: cgst+sgst=" . round($cgst + $sgst, 2) . " != gst={$gst}";
                }
            }
            $pass20 = empty($c20Fails);
            $rows[] = [
                'check' => '20. Cancellation turf fee GST split',
                'expected' => 'turf_fee_cgst + sgst = turf_fee_gst',
                'actual' => $pass20 ? 'Valid' : implode(', ', $c20Fails),
                'result' => $pass20 ? 'PASS' : 'FAIL',
            ];

            // Check 21: saas_fee_cgst + sgst + igst = saas_fee_gst, plus intra/inter type rule
            $c21Fails = [];
            $isIntra = $turf ? GstStateResolver::isIntraState($turf, $saas) : true;
            foreach ($b->bookingCancellations as $c) {
                $cgst = (float) $c->saas_fee_cgst_amount;
                $sgst = (float) $c->saas_fee_sgst_amount;
                $igst = (float) $c->saas_fee_igst_amount;
                $gst = (float) $c->saas_fee_gst_amount;

                if (abs(($cgst + $sgst + $igst) - $gst) > 0.01) {
                    $c21Fails[] = "Cancel #{$c->id}: cgst+sgst+igst=" . round($cgst + $sgst + $igst, 2) . " != gst={$gst}";
                }
                if ($gst > 0.001) {
                    if ($isIntra && ($igst > 0.0 || ($cgst + $sgst) <= 0.0)) {
                        $c21Fails[] = "Cancel #{$c->id}: intra-state should have CGST+SGST > 0, IGST = 0";
                    } elseif (!$isIntra && ($igst <= 0.0 || ($cgst + $sgst) > 0.0)) {
                        $c21Fails[] = "Cancel #{$c->id}: inter-state should have IGST > 0, CGST=SGST = 0";
                    }
                }
            }
            $pass21 = empty($c21Fails);
            $rows[] = [
                'check' => '21. Cancellation SaaS fee GST split and type',
                'expected' => 'Split matches GST amount & state rule',
                'actual' => $pass21 ? 'Valid' : implode(', ', $c21Fails),
                'result' => $pass21 ? 'PASS' : 'FAIL',
            ];

            // Check 22: refund_taxable_amount + refund_gst_amount = refund_amount, and refund_cgst + sgst = refund_gst
            $c22Fails = [];
            foreach ($b->bookingCancellations as $c) {
                $taxable = (float) $c->refund_taxable_amount;
                $gst = (float) $c->refund_gst_amount;
                $total = (float) $c->refund_amount;
                $cgst = (float) $c->refund_cgst_amount;
                $sgst = (float) $c->refund_sgst_amount;

                if (abs(($taxable + $gst) - $total) > 0.01) {
                    $c22Fails[] = "Cancel #{$c->id}: taxable+gst=" . round($taxable + $gst, 2) . " != total={$total}";
                }
                if (abs(($cgst + $sgst) - $gst) > 0.01) {
                    $c22Fails[] = "Cancel #{$c->id}: cgst+sgst=" . round($cgst + $sgst, 2) . " != gst={$gst}";
                }
            }
            $pass22 = empty($c22Fails);
            $rows[] = [
                'check' => '22. Cancellation refund taxable and GST sum',
                'expected' => 'taxable+gst = refund, cgst+sgst = gst',
                'actual' => $pass22 ? 'Valid' : implode(', ', $c22Fails),
                'result' => $pass22 ? 'PASS' : 'FAIL',
            ];

            // Check 23: Refund to Razorpay, unless --no-gateway
            if (!$useGateway) {
                $rows[] = [
                    'check' => '23. Cancellation refund matches Razorpay refund',
                    'expected' => null,
                    'actual' => 'Skipped by --no-gateway',
                    'result' => 'SKIP',
                ];
            } else {
                $c23Skip = null;
                $c23Fails = [];
                [$key, $secret] = RazorpayPaymentVerifier::credentials();

                $refundCount = 0;
                foreach ($b->bookingCancellations as $c) {
                    $refAmt = (float) $c->refund_amount;
                    if ($refAmt <= 0.0) {
                        continue;
                    }
                    if (($c->disbursement_channel === 'offline' || $c->refund_status === 'Cash / Offline Refund') && empty($c->razorpay_refund_id)) {
                        continue;
                    }
                    if (in_array($c->refund_status, ['Pending', 'Pending Resolution'], true)) {
                        $c23Skip = "Cancellation #{$c->id} is {$c->refund_status}";
                        break;
                    }

                    $datePayments = $b->payments->where('booking_date_id', $c->booking_date_id)
                        ->where('payment_method', 'App');
                    if ($datePayments->isEmpty()) {
                        $datePayments = $successAppPayments;
                    }
                    if ($datePayments->isEmpty() && empty($c->razorpay_refund_id)) {
                        continue;
                    }

                    $refundCount++;

                    $gwIds = $datePayments->map(fn ($p) => $p->paymentGateway?->gateway_payment_id)->filter()->unique()->all();
                    $rzpRefundSum = 0.0;
                    foreach ($gwIds as $gwId) {
                        try {
                            $resp = Http::withBasicAuth($key, $secret)->get("https://api.razorpay.com/v1/payments/{$gwId}/refunds");
                            if (!$resp->successful()) {
                                $c23Skip = 'Gateway returned HTTP ' . $resp->status();
                                break 2;
                            }
                            $items = $resp->json('items') ?? [];
                            foreach ($items as $item) {
                                if (($item['status'] ?? '') !== 'failed') {
                                    $rzpRefundSum += ((float) ($item['amount'] ?? 0)) / 100;
                                }
                            }
                        } catch (\Throwable $e) {
                            $c23Skip = 'Gateway error: ' . $e->getMessage();
                            break 2;
                        }
                    }

                    if (round($rzpRefundSum - $refAmt, 2) < -0.01) {
                        $c23Fails[] = "Cancel #{$c->id}: Razorpay refund ₹{$rzpRefundSum} < refund_amount ₹{$refAmt}";
                    }
                }

                if ($c23Skip !== null) {
                    $rows[] = [
                        'check' => '23. Cancellation refund matches Razorpay refund',
                        'expected' => null,
                        'actual' => $c23Skip,
                        'result' => 'SKIP',
                    ];
                } elseif ($refundCount === 0) {
                    $rows[] = [
                        'check' => '23. Cancellation refund matches Razorpay refund',
                        'expected' => null,
                        'actual' => 'No online gateway refund',
                        'result' => 'SKIP',
                    ];
                } else {
                    $pass23 = empty($c23Fails);
                    $rows[] = [
                        'check' => '23. Cancellation refund matches Razorpay refund',
                        'expected' => 'Razorpay refund sum >= cancellation refund',
                        'actual' => $pass23 ? 'Verified on Razorpay' : implode('; ', $c23Fails),
                        'result' => $pass23 ? 'PASS' : 'FAIL',
                    ];
                }
            }
        }

        // -------------------------------------------------------------
        // Reschedules
        // -------------------------------------------------------------
        $reschedules = $b->bookingDates->flatMap->reschedules;
        if ($reschedules->isEmpty()) {
            $rows[] = [
                'check' => '24. Reschedule price difference',
                'expected' => null,
                'actual' => 'No reschedules',
                'result' => 'SKIP',
            ];
            $rows[] = [
                'check' => '25. Reschedule refund recorded in payments',
                'expected' => null,
                'actual' => 'No reschedules',
                'result' => 'SKIP',
            ];
        } else {
            // Check 24: new_amount - old_amount = price_difference
            $c24Fails = [];
            foreach ($reschedules as $r) {
                $diff = round((float) $r->new_amount - (float) $r->old_amount, 2);
                $storedDiff = round((float) $r->price_difference, 2);
                if (abs($diff - $storedDiff) > 0.01) {
                    $c24Fails[] = "Reschedule #{$r->id}: new - old = {$diff} != price_diff {$storedDiff}";
                }
            }
            $pass24 = empty($c24Fails);
            $rows[] = [
                'check' => '24. Reschedule price difference',
                'expected' => 'new_amount - old_amount = price_difference',
                'actual' => $pass24 ? 'Valid' : implode(', ', $c24Fails),
                'result' => $pass24 ? 'PASS' : 'FAIL',
            ];

            // Check 25: If settlement_type = 'refund', refund_amount > 0 and date's payments refunded_amount sum >= refund_amount
            $c25Fails = [];
            $refundReschedules = $reschedules->where('settlement_type', 'refund');
            if ($refundReschedules->isEmpty()) {
                $rows[] = [
                    'check' => '25. Reschedule refund recorded in payments',
                    'expected' => null,
                    'actual' => 'No refund reschedules',
                    'result' => 'SKIP',
                ];
            } else {
                foreach ($refundReschedules as $r) {
                    $refAmt = (float) $r->refund_amount;
                    if ($refAmt <= 0.0) {
                        $c25Fails[] = "Reschedule #{$r->id}: refund_amount <= 0";
                    }
                    $datePayments = $b->payments->where('booking_date_id', $r->booking_date_id);
                    if ($datePayments->isEmpty()) {
                        $datePayments = $b->payments;
                    }
                    $refundedSum = (float) $datePayments->sum('refunded_amount');
                    if (round($refundedSum - $refAmt, 2) < -0.01) {
                        $c25Fails[] = "Reschedule #{$r->id}: date refunded_amount {$refundedSum} < refund {$refAmt}";
                    }
                }
                $pass25 = empty($c25Fails);
                $rows[] = [
                    'check' => '25. Reschedule refund recorded in payments',
                    'expected' => 'payments.refunded_amount >= reschedule refund',
                    'actual' => $pass25 ? 'Recorded in payments' : implode('; ', $c25Fails),
                    'result' => $pass25 ? 'PASS' : 'FAIL',
                ];
            }
        }

        return $rows;
    }

    /**
     * Audit an owner's entire wallet, ledger consistency, balance rule, and payouts.
     *
     * @return array<int, array{check: string, expected: ?string, actual: ?string, result: 'PASS'|'FAIL'|'SKIP'}>
     */
    public function checkOwner(User $owner, bool $useGateway = true): array
    {
        $rows = [];
        $walletService = app(WalletService::class);

        // 30. Ledger integrity: users.commission_wallet_balance = sum(all owner CommissionWalletTransaction.amount)
        $walletBalance = round((float) $owner->fresh()->commission_wallet_balance, 2);
        $ledgerSum = round((float) CommissionWalletTransaction::where('user_id', $owner->id)->sum('amount'), 2);
        $pass30 = abs($walletBalance - $ledgerSum) <= 0.02;

        $rows[] = [
            'check' => '30. Wallet balance = sum(ledger transactions)',
            'expected' => number_format($ledgerSum, 2),
            'actual' => number_format($walletBalance, 2),
            'result' => $pass30 ? 'PASS' : 'FAIL',
        ];

        // 31. The balance rule
        $ownerTurfIds = Turf::whereHas('location', fn ($q) => $q->where('user_id', $owner->id))->pluck('id')->all();

        // online net = sum(amount - refunded_amount) of Success App payments on owner's turfs
        $onlinePayments = Payment::where('status', 'Success')
            ->where('payment_method', 'App')
            ->whereHas('booking', fn ($q) => $q->whereIn('turf_id', $ownerTurfIds))
            ->get();
        $onlineNet = round((float) $onlinePayments->sum(fn ($p) => (float) $p->amount - (float) $p->refunded_amount), 2);

        // platform keeps
        // 1) Bookings with any Success payment
        $bookingsWithSuccess = Booking::whereIn('turf_id', $ownerTurfIds)
            ->whereHas('payments', fn ($q) => $q->where('status', 'Success'))
            ->get();
        $feesSum = (float) $bookingsWithSuccess->sum(fn ($b) =>
            (float) $b->platform_fee + (float) $b->platform_fee_gst +
            (float) $b->commission_amount + (float) $b->commission_gst_amount
        );

        // 2) Unique gateway_payment_id gateway fees
        $uniqueGatewayIds = $onlinePayments->map(fn ($p) => $p->paymentGateway?->gateway_payment_id)->filter()->unique()->all();
        $gwFeeSum = 0.0;
        foreach ($uniqueGatewayIds as $gwId) {
            $gwPayments = Payment::whereHas('paymentGateway', fn ($q) => $q->where('gateway_payment_id', $gwId))->get();
            $gwFeeSum += (float) $gwPayments->sum(fn ($p) => (float) $p->gateway_charge_amount + (float) $p->gateway_tax_amount);
        }

        // 3) Cancellations
        $cancellations = BookingCancellation::whereHas('booking', fn ($q) => $q->whereIn('turf_id', $ownerTurfIds))->get();
        $cancellationShareSum = (float) $cancellations->sum(fn ($c) =>
            min((float) $c->saas_cancellation_fee, max(0.0, (float) $c->total_cancellation_fee - (float) $c->platform_fee_retained))
        );

        $platformKeeps = round($feesSum + $gwFeeSum + $cancellationShareSum, 2);

        // payouts = sum(TurfPayout.requested_amount) where status is in ACTIVE_STATUSES
        $payouts = round((float) TurfPayout::where('user_id', $owner->id)->whereIn('status', TurfPayout::ACTIVE_STATUSES)->sum('requested_amount'), 2);

        $expectedWallet = round($onlineNet - $platformKeeps - $payouts, 2);
        $pass31 = abs($walletBalance - $expectedWallet) <= 0.05;

        // Check for unknown transaction types
        $knownTypes = [
            'payment_credit',
            'refund_adjustment',
            'platform_fee_debit',
            'commission_debit',
            'gateway_charge_debit',
            'offline_booking_record',
            'payout_debit',
            'payout_reversal',
            'cancellation_fee_debit', // platform cancellation fee on cash bookings
        ];
        $otherTypes = CommissionWalletTransaction::where('user_id', $owner->id)
            ->whereNotIn('type', $knownTypes)
            ->pluck('type')
            ->unique()
            ->all();

        if (!empty($otherTypes)) {
            $countOther = CommissionWalletTransaction::where('user_id', $owner->id)
                ->whereNotIn('type', $knownTypes)
                ->count();
            $rows[] = [
                'check' => 'Note: Other wallet transaction types',
                'expected' => null,
                'actual' => "Owner has {$countOther} wallet rows of other types: " . implode(', ', $otherTypes),
                'result' => 'PASS',
            ];
        }

        $rows[] = [
            'check' => '31. Balance rule: wallet = online net - platform keeps - payouts',
            'expected' => number_format($expectedWallet, 2) . " (online: {$onlineNet}, keeps: {$platformKeeps}, payouts: {$payouts})",
            'actual' => number_format($walletBalance, 2),
            'result' => $pass31 ? 'PASS' : 'FAIL',
        ];

        // 32. Pending Clearance
        $pendingAmount = round((float) $walletService->pendingCredits($ownerTurfIds)->sum('amount'), 2);
        $pass32 = $pendingAmount >= -0.01;
        $rows[] = [
            'check' => '32. Pending Clearance',
            'expected' => '>= 0.00',
            'actual' => '₹' . number_format($pendingAmount, 2),
            'result' => $pass32 ? 'PASS' : 'FAIL',
        ];

        // 33. Payouts
        $ownerPayouts = TurfPayout::where('user_id', $owner->id)->get();
        if ($ownerPayouts->isEmpty()) {
            $rows[] = [
                'check' => '33. Payouts have matching ledger rows',
                'expected' => null,
                'actual' => 'No payouts',
                'result' => 'SKIP',
            ];
        } else {
            $payoutFailures = [];
            foreach ($ownerPayouts as $payout) {
                if (in_array($payout->status, [TurfPayout::STATUS_FAILED, TurfPayout::STATUS_REJECTED], true)) {
                    $hasReversal = CommissionWalletTransaction::where('user_id', $owner->id)
                        ->where('type', 'payout_reversal')
                        ->where('reference_type', TurfPayout::class)
                        ->where('reference_id', $payout->id)
                        ->exists();
                    if (!$hasReversal) {
                        $payoutFailures[] = "Payout #{$payout->id} ({$payout->status}) missing payout_reversal";
                    }
                } else {
                    $hasDebit = CommissionWalletTransaction::where('user_id', $owner->id)
                        ->where('type', 'payout_debit')
                        ->where('reference_type', TurfPayout::class)
                        ->where('reference_id', $payout->id)
                        ->exists();
                    if (!$hasDebit) {
                        $payoutFailures[] = "Payout #{$payout->id} missing payout_debit";
                    }
                }
            }
            $pass33 = empty($payoutFailures);
            $rows[] = [
                'check' => '33. Payouts have matching ledger rows',
                'expected' => 'Matching debit/reversal for each payout',
                'actual' => $pass33 ? 'All payouts matched in ledger' : implode(', ', $payoutFailures),
                'result' => $pass33 ? 'PASS' : 'FAIL',
            ];
        }

        // 34. Manual payouts finished correctly
        $manualPayouts = TurfPayout::where('user_id', $owner->id)->where('mode', 'manual')->get();
        if ($manualPayouts->isEmpty()) {
            $rows[] = [
                'check' => '34. Manual payouts finished correctly',
                'expected' => null,
                'actual' => 'No manual payouts',
                'result' => 'SKIP',
            ];
        } else {
            $completedManual = $manualPayouts->where('status', TurfPayout::STATUS_COMPLETED);
            $c34Fails = [];
            foreach ($completedManual as $mp) {
                if (empty(trim((string) $mp->utr)) || empty($mp->paid_at)) {
                    $c34Fails[] = "Payout #{$mp->id} missing " . (empty(trim((string) $mp->utr)) ? 'UTR ' : '') . (empty($mp->paid_at) ? 'paid_at' : '');
                }
            }
            $pass34 = empty($c34Fails);
            $rows[] = [
                'check' => '34. Manual payouts finished correctly',
                'expected' => 'Completed manual payouts have UTR and paid_at',
                'actual' => $pass34 ? 'All completed manual payouts have UTR and paid_at' : implode(', ', $c34Fails),
                'result' => $pass34 ? 'PASS' : 'FAIL',
            ];
        }

        return $rows;
    }
}
