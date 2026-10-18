<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\SaasSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Confirms with Razorpay that an online booking payment is real before the server records it.
 * Without this, a booking request could claim "paid online" with no, a failed, a too-small, or an
 * already-used Razorpay payment and still be recorded as fully paid.
 */
class RazorpayPaymentVerifier
{
    /**
     * @return array{ok: bool, message: string, payload: ?array, gateway_charge: float, gateway_tax: float}
     */
    public function verify(?string $paymentId, ?string $orderId, ?string $signature, float $expectedAmount): array
    {
        $fail = fn (string $message) => ['ok' => false, 'message' => $message, 'payload' => null, 'gateway_charge' => 0.0, 'gateway_tax' => 0.0];

        if (!$paymentId || !$orderId || !$signature) {
            return $fail('Online payment details are missing. Please complete the payment again.');
        }

        [$key, $secret] = $this->credentials();
        if (!$key || !$secret) {
            return $fail('Online payments are not configured. Please contact support.');
        }

        if (!$this->signatureIsValid($paymentId, $orderId, $signature, $secret)) {
            return $fail('Payment could not be verified.');
        }

        if (PaymentGateway::where('gateway_payment_id', $paymentId)->exists()) {
            return $fail('This payment has already been used for another booking.');
        }

        $response = Http::withBasicAuth($key, $secret)->get("https://api.razorpay.com/v1/payments/{$paymentId}");
        if (!$response->successful()) {
            return $fail('Payment could not be verified with the payment gateway.');
        }
        $payment = $response->json();

        if (($payment['order_id'] ?? null) !== $orderId) {
            return $fail('Payment does not belong to this order.');
        }
        if (strtoupper($payment['currency'] ?? 'INR') !== 'INR') {
            return $fail('Unsupported payment currency.');
        }
        $expectedPaise = (int) round($expectedAmount * 100);
        if ((int) ($payment['amount'] ?? 0) !== $expectedPaise) {
            return $fail('Paid amount does not match the booking amount.');
        }

        $status = $payment['status'] ?? '';
        if ($status === 'authorized') {
            $capture = Http::withBasicAuth($key, $secret)->asForm()
                ->post("https://api.razorpay.com/v1/payments/{$paymentId}/capture", [
                    'amount' => $expectedPaise,
                    'currency' => 'INR',
                ]);
            if (!$capture->successful()) {
                return $fail('Payment could not be captured.');
            }
            $payment = $capture->json();
            $status = $payment['status'] ?? '';
        }
        if ($status !== 'captured') {
            return $fail('Payment was not successful.');
        }

        // Razorpay's `fee` already INCLUDES GST and `tax` is the GST part of it. Store them as
        // base charge + GST so that charge + tax == what Razorpay actually charged.
        $fee = (float) ($payment['fee'] ?? 0) / 100;
        $tax = (float) ($payment['tax'] ?? 0) / 100;

        return [
            'ok' => true,
            'message' => 'Payment verified.',
            'payload' => $payment,
            'gateway_charge' => round(max(0.0, $fee - $tax), 2),
            'gateway_tax' => round($tax, 2),
        ];
    }

    public function signatureIsValid(string $paymentId, string $orderId, string $signature, ?string $secret = null): bool
    {
        $secret ??= $this->credentials()[1];
        if (!$secret) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $secret), $signature);
    }

    /**
     * Gives the money back when a genuine payment for one of our orders could not become a booking
     * (slot taken meanwhile, amount mismatch, any other failure). Skips payments that are already
     * linked to a booking. Returns true when a refund was issued.
     */
    public function refundUnusedPayment(?string $paymentId, ?string $orderId, ?string $signature): bool
    {
        if (!$paymentId || !$orderId || !$signature || !$this->signatureIsValid($paymentId, $orderId, $signature)) {
            return false;
        }
        if (PaymentGateway::where('gateway_payment_id', $paymentId)->exists()) {
            return false;
        }

        [$key, $secret] = $this->credentials();
        try {
            $payment = Http::withBasicAuth($key, $secret)->get("https://api.razorpay.com/v1/payments/{$paymentId}")->json();
            $status = $payment['status'] ?? '';
            $amount = (int) ($payment['amount'] ?? 0);

            if ($status === 'authorized') {
                // Not captured yet: capturing then refunding is the only API path; Razorpay also
                // auto-refunds uncaptured payments, but do it now so the player isn't left waiting.
                Http::withBasicAuth($key, $secret)->asForm()
                    ->post("https://api.razorpay.com/v1/payments/{$paymentId}/capture", ['amount' => $amount, 'currency' => 'INR']);
                $status = 'captured';
            }
            if ($status !== 'captured' || $amount <= 0) {
                return false;
            }

            $refund = Http::withBasicAuth($key, $secret)->asForm()
                ->post("https://api.razorpay.com/v1/payments/{$paymentId}/refund", ['amount' => $amount]);
            if ($refund->successful()) {
                Log::warning('Booking failed after payment; payment refunded automatically.', ['payment_id' => $paymentId, 'refund_id' => $refund->json('id')]);
                return true;
            }
            Log::critical('Booking failed after payment and the automatic refund failed. Refund manually.', ['payment_id' => $paymentId, 'response' => $refund->json()]);
        } catch (\Throwable $e) {
            Log::critical('Booking failed after payment and the automatic refund errored. Refund manually.', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);
        }

        return false;
    }

    public static function credentials(): array
    {
        $saas = SaasSetting::first();

        return [
            $saas?->razorpay_key ?: config('services.razorpay.key'),
            $saas?->razorpay_secret ?: config('services.razorpay.secret'),
        ];
    }
}
