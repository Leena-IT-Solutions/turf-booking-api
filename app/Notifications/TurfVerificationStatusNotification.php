<?php

namespace App\Notifications;

use App\Models\Turf;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TurfVerificationStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Turf $turf,
        public string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isApproved = $this->status === 'Approved';
        $subject = $isApproved
            ? "🎉 {$this->turf->name} is Verified & Live on ".config('app.name', 'TurfBooking')
            : "Update on {$this->turf->name} Verification ({$this->status})";

        $mail = (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$notifiable->name},");

        if ($isApproved) {
            $mail->line("Great news! Your turf **{$this->turf->name}** has been verified and approved.")
                ->line('Players can now discover and book slots at your venue on the mobile app and web platform.')
                ->action('Open Turf Dashboard', url('/turf/dashboard'));
        } else {
            $mail->line("The verification status of your turf **{$this->turf->name}** has been updated to **{$this->status}**.")
                ->line('Please review your turf setup checklist in the dashboard and update any pending items.')
                ->action('Review Turf Setup', url('/turf/setup-wizard'));
        }

        return $mail->line('Thank you for partnering with '.config('app.name', 'TurfBooking').'!');
    }
}
