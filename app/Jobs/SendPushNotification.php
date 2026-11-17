<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Models\NotificationDelivery;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Delivers one push notification to a set of users and records a delivery row
 * per recipient. Only primitives are serialized so the job is safe to queue.
 */
class SendPushNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * FCM errors are caught and recorded per recipient, so a retry would only
     * re-send to users who already received it. Keep it to a single attempt.
     */
    public int $tries = 1;

    /**
     * @param  array<int>  $userIds
     */
    public function __construct(
        public array $userIds,
        public string $event,
        public string $title,
        public string $body,
        public array $data = [],
    ) {
        $this->afterCommit();
        $this->onConnection(config('notifications.queue_connection', 'sync'));
        $this->onQueue(config('notifications.queue', 'notifications'));
    }

    public function handle(): void
    {
        $tokensByUser = DeviceToken::whereIn('user_id', $this->userIds)
            ->get(['user_id', 'device_token'])
            ->groupBy('user_id');

        foreach ($this->userIds as $userId) {
            $tokens = $tokensByUser->get($userId)?->pluck('device_token')->all() ?? [];

            if (empty($tokens)) {
                $this->record($userId, NotificationDelivery::STATUS_NO_DEVICE);

                continue;
            }

            $result = NotificationService::sendFcmNotification($tokens, $this->title, $this->body, $this->data);

            $this->record(
                $userId,
                $result['status'],
                $result['error'] ?? null,
                $result['status'] === NotificationDelivery::STATUS_SENT ? now() : null,
            );
        }
    }

    private function record(int $userId, string $status, ?string $error = null, $sentAt = null): void
    {
        try {
            NotificationDelivery::create([
                'user_id' => $userId,
                'event' => $this->event,
                'channel' => 'push',
                'title' => $this->title,
                'body' => $this->body,
                'data' => $this->data,
                'status' => $status,
                'error' => $error,
                'sent_at' => $sentAt,
            ]);
        } catch (\Throwable $e) {
            // Logging must never break delivery.
        }
    }
}
