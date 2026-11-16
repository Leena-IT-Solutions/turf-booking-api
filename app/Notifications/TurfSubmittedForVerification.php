<?php

namespace App\Notifications;

use App\Models\Turf;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TurfSubmittedForVerification extends Notification
{
    use Queueable;

    public function __construct(
        public Turf $turf,
        public ?User $submittedBy = null
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $location = $this->turf->location;
        $owner = $location?->user;
        $onboarder = $this->submittedBy ?: $this->turf->onboardedBy;

        return (new MailMessage)
            ->subject("🏟️ Turf Submitted for Verification: {$this->turf->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("A turf venue has completed the onboarding wizard and is ready for verification.")
            ->line("**Turf Name:** {$this->turf->name} ({$this->turf->type})")
            ->line("**Location:** " . ($location?->name ?? 'N/A') . ' — ' . ($location?->address ?? 'N/A'))
            ->line("**Turf Owner:** " . ($owner?->name ?? 'N/A') . ' (' . ($owner?->mobile ?? $owner?->email ?? 'N/A') . ')')
            ->line("**Onboarded / Submitted By:** " . ($onboarder?->name ?? 'Turf Owner'))
            ->line("**Submitted At:** " . now('Asia/Kolkata')->format('d M Y, h:i A') . ' IST')
            ->action('Review in Turf Verification', route('saas.turf-verification'))
            ->line('Thank you for growing the TurfBooking network!');
    }
}

