<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Booking;
use App\Models\BookingDate;
use App\Models\Payment;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fix all legacy bookings that have payment_status = 'Paid'
        $paidBookings = Booking::where('payment_status', 'Paid')->get();

        foreach ($paidBookings as $booking) {
            $totalAmount = (float)$booking->total_amount;

            // 1. Force balance_amount to 0.00 and payable_now to total_amount
            $booking->update([
                'balance_amount' => 0.00,
                'payable_now' => $totalAmount,
            ]);

            // 2. Fix booking dates
            $bookingDates = $booking->bookingDates;
            if ($bookingDates->count() === 1) {
                $bDate = $bookingDates->first();
                $bDate->update([
                    'amount' => $totalAmount,
                    'paid_amount' => $totalAmount,
                    'balance_amount' => 0.00,
                    'payment_status' => 'Paid',
                ]);

                // 3. Fix payment amount if it was recorded without platform fee
                $payments = Payment::where('booking_id', $booking->id)
                    ->where('status', 'Success')
                    ->get();

                if ($payments->count() === 1) {
                    $payment = $payments->first();
                    if ((float)$payment->amount < $totalAmount) {
                        $payment->update([
                            'amount' => $totalAmount,
                        ]);
                    }
                }
            } else {
                foreach ($bookingDates as $bDate) {
                    $bDate->update([
                        'balance_amount' => 0.00,
                        'payment_status' => 'Paid',
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
        // Data fix is irreversible and safe
    }
};
