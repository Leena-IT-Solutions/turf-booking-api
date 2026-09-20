<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Booking;
use App\Models\Payment;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Find payments created for offline 'pay_at_location' bookings that were automatically marked 'Success'
        $offlinePayments = Payment::where('payment_method', 'offline')
            ->where('status', 'Success')
            ->get();

        foreach ($offlinePayments as $payment) {
            $booking = $payment->booking;
            if (!$booking) {
                continue;
            }

            // Check if this booking had any successful online (App) payment
            $hasOnlineSuccess = Payment::where('booking_id', $booking->id)
                ->where('payment_method', 'App')
                ->where('status', 'Success')
                ->exists();

            if (!$hasOnlineSuccess) {
                $payment->update([
                    'status' => 'Pending',
                    'paid_at' => null,
                ]);

                $bDate = $payment->bookingDate;
                if ($bDate) {
                    $successfulPaidForDate = (float) Payment::where('booking_date_id', $bDate->id)
                        ->where('status', 'Success')
                        ->sum('amount');

                    $bDate->update([
                        'paid_amount' => $successfulPaidForDate,
                        'balance_amount' => max(0.00, (float)$bDate->amount - $successfulPaidForDate),
                        'payment_status' => $successfulPaidForDate >= (float)$bDate->amount && (float)$bDate->amount > 0 ? 'Paid' : ($successfulPaidForDate > 0 ? 'Partially Paid' : 'Unpaid'),
                    ]);
                }

                $successfulTotalPaid = (float) Payment::where('booking_id', $booking->id)
                    ->where('status', 'Success')
                    ->sum('amount');

                $booking->update([
                    'payable_now' => $successfulTotalPaid,
                    'balance_amount' => max(0.00, (float)$booking->total_amount - $successfulTotalPaid),
                    'payment_status' => $successfulTotalPaid >= (float)$booking->total_amount && (float)$booking->total_amount > 0 ? 'Paid' : ($successfulTotalPaid > 0 ? 'Partially Paid' : 'Unpaid'),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reversal needed
    }
};
