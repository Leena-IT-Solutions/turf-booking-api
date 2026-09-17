<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\PaymentGateway;
use App\Models\Payment;
use App\Models\Booking;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $gateways = PaymentGateway::all();

        foreach ($gateways as $gw) {
            $payload = $gw->response_payload;
            $payment = $gw->payment;

            if ($payment) {
                // If we have actual Razorpay response payload, use exact fee & tax reported by Razorpay
                $fee = 0.00;
                $tax = 0.00;

                if (is_array($payload)) {
                    if (isset($payload['fee'])) {
                        $fee = round((float)$payload['fee'] / 100, 2);
                    }
                    if (isset($payload['tax'])) {
                        $tax = round((float)$payload['tax'] / 100, 2);
                    }
                }

                $payment->update([
                    'gateway_charge_amount' => $fee,
                    'gateway_tax_amount' => $tax,
                ]);

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
