<?php

namespace App\Notifications;

use App\Models\CommissionInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommissionInvoiceGeneratedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CommissionInvoice $invoice,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $turfName = $this->invoice->recipient_snapshot['turf_name'] ?? ($this->invoice->turf?->name ?? 'Your Turf');
        $totalFormatted = number_format((float) $this->invoice->total_amount, 2);

        return (new MailMessage)
            ->subject("Commission Tax Invoice {$this->invoice->invoice_number} — {$turfName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your monthly platform commission tax invoice **{$this->invoice->invoice_number}** for **{$turfName}** has been generated.")
            ->line("**Billing Period:** {$this->invoice->period_start} to {$this->invoice->period_end}")
            ->line("**Total Amount:** ₹{$totalFormatted}")
            ->action('View Commission Invoices', url('/turf/wallet'))
            ->line('Thank you for partnering with '.config('app.name', 'TurfBooking').'!');
    }
}
