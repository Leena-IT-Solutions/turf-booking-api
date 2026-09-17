<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentGateway;
use App\Models\Payment;
use App\Models\Booking;
use App\Models\SaasSetting;
use App\Models\PaymentGatewayCharge;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $setting = SaasSetting::first();
        $rzpKey = $setting?->razorpay_key ?: config('services.razorpay.key');
        $rzpSecret = $setting?->razorpay_secret ?: config('services.razorpay.secret');

        $gateways = PaymentGateway::whereNull('response_payload')
            ->whereNotNull('gateway_payment_id')
            ->get();

        foreach ($gateways as $gw) {
            $paymentId = $gw->gateway_payment_id;
            $pData = null;

            if ($rzpKey && $rzpSecret && str_starts_with($paymentId, 'pay_')) {
                try {
                    $res = Http::withBasicAuth($rzpKey, $rzpSecret)
                        ->get("https://api.razorpay.com/v1/payments/{$paymentId}");
                    if ($res->successful()) {
                        $pData = $res->json();
                    }
                } catch (\Exception $e) {
                    Log::warning("Could not fetch payment {$paymentId}: " . $e->getMessage());
                }
            }

            $updates = [];
            if ($pData) {
                $updates['response_payload'] = $pData;
                if (!empty($pData['order_id'])) {
                    $updates['gateway_order_id'] = $pData['order_id'];
                }
            } else {
                // If it was a simulated or older payment not in this live account, set structured fallback response payload
                $updates['response_payload'] = [
                    'id' => $paymentId,
                    'status' => 'captured',
                    'method' => 'upi',
                    'simulated' => true,
                    'note' => 'Backfilled response payload',
                ];
            }

            $gw->update($updates);

            // Update associated Payment gateway charges if currently 0
            $payment = $gw->payment;
            if ($payment && (float)$payment->gateway_charge_amount <= 0.00 && (float)$payment->amount > 0) {
                $payMethod = strtolower($pData['method'] ?? 'upi');
                $chargeRule = PaymentGatewayCharge::where('is_active', true)->where('code', $payMethod)->first()
                    ?? PaymentGatewayCharge::where('is_active', true)->where('code', 'upi')->first()
                    ?? PaymentGatewayCharge::where('is_active', true)->first();

                $chargePct = $chargeRule ? (float)$chargeRule->charge_percentage : 2.00;
                $taxPct = $chargeRule ? (float)$chargeRule->tax_percentage : 18.00;

                $charge = round((float)$payment->amount * ($chargePct / 100), 2);
                $tax = round($charge * ($taxPct / 100), 2);

                $payment->update([
                    'gateway_charge_amount' => $charge,
                    'gateway_tax_amount' => $tax,
                ]);

                // Update overall booking
                if ($payment->booking_id) {
                    Booking::where('id', $payment->booking_id)->update([
                        'gateway_charge_amount' => (float) Payment::where('booking_id', $payment->booking_id)->where('status', 'Success')->sum('gateway_charge_amount'),
                        'gateway_tax_amount' => (float) Payment::where('booking_id', $payment->booking_id)->where('status', 'Success')->sum('gateway_tax_amount'),
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
