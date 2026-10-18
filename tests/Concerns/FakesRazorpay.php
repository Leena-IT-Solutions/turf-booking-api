<?php

namespace Tests\Concerns;

use App\Models\SaasSetting;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Genuine-looking Razorpay payments for tests. The booking API now verifies online payments
 * (signature, order, amount, captured status), so tests pay the way the app does: a payment of the
 * exact amount, for its own order, signed with the account's secret.
 *
 * Payment ids carry their amount ("pay_<paise>_<n>"). The fee ($razorpayFeePercent + 18% GST) is reported
 * the way Razorpay does: `fee` includes the GST, `tax` is the GST part of it.
 */
trait FakesRazorpay
{
    protected string $razorpayTestSecret = 'rzp_test_secret';

    /** Razorpay's fee before GST, in percent. 0 by default so tests that don't care about gateway fees keep exact figures. */
    protected float $razorpayFeePercent = 0.0;

    /** Razorpay's actual fee (GST included) per payment id. */
    protected array $razorpayFees = [];

    private bool $razorpayFaked = false;

    protected function razorpayPayment(float $amount): array
    {
        $this->ensureRazorpayFake();

        $paise = (int) round($amount * 100);
        $paymentId = "pay_{$paise}_" . count($this->razorpayFees);
        $orderId = "order_{$paymentId}";
        $base = (int) round($paise * $this->razorpayFeePercent / 100);
        $this->razorpayFees[$paymentId] = round(($base + (int) round($base * 0.18)) / 100, 2);

        return [
            'razorpay_payment_id' => $paymentId,
            'razorpay_order_id' => $orderId,
            'razorpay_signature' => hash_hmac('sha256', "{$orderId}|{$paymentId}", $this->razorpayTestSecret),
        ];
    }

    protected function ensureRazorpayFake(): void
    {
        $settings = SaasSetting::first() ?? SaasSetting::create([]);
        $settings->update(['razorpay_key' => 'rzp_test_key', 'razorpay_secret' => $this->razorpayTestSecret]);

        if ($this->razorpayFaked) {
            return;
        }
        $this->razorpayFaked = true;

        Http::fake(function (Request $request) {
            if (!preg_match('#/v1/payments/(pay_(\d+)_\d+)(/(capture|refund))?$#', $request->url(), $m)) {
                return null; // not ours: let other fakes in the test answer
            }
            [$all, $paymentId, $paise] = $m;
            $action = $m[4] ?? null;

            if ($action === 'refund') {
                return Http::response(['id' => 'rfnd_' . uniqid(), 'payment_id' => $paymentId, 'status' => 'processed'], 200);
            }

            $base = (int) round((int) $paise * $this->razorpayFeePercent / 100);
            $tax = (int) round($base * 0.18);

            return Http::response([
                'id' => $paymentId,
                'order_id' => "order_{$paymentId}",
                'status' => 'captured',
                'amount' => (int) $paise,
                'currency' => 'INR',
                'fee' => $base + $tax,
                'tax' => $tax,
            ], 200);
        });
    }
}
