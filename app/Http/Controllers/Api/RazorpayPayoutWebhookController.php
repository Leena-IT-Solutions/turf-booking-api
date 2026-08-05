<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutWebhook;
use App\Models\SaasSetting;
use App\Models\TurfPayout;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RazorpayPayoutWebhookController extends Controller
{
    /**
     * Handle incoming RazorpayX payout webhooks securely.
     */
    public function handle(Request $request): JsonResponse
    {
        $saas = SaasSetting::first();
        $webhookSecret = $saas?->razorpayx_webhook_secret;

        $signature = $request->header('X-Razorpay-Signature');
        $payload = $request->getContent();

        // 1. Verify HMAC Signature if secret configured
        if ($webhookSecret && $signature) {
            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);
            if (!hash_equals($expectedSignature, $signature)) {
                return response()->json(['message' => 'Invalid webhook signature'], 400);
            }
        }

        $data = $request->all();
        $eventId = $request->header('X-Razorpay-Event-Id') ?: ($data['event_id'] ?? null ?: ('evt_' . time() . '_' . uniqid()));
        $eventType = $data['event'] ?? null;

        // 2. Log & Deduplicate event
        $webhookLog = PayoutWebhook::firstOrCreate(
            ['event_id' => $eventId],
            [
                'event_type' => $eventType,
                'payload' => $data,
                'processed' => false,
            ]
        );

        if (!$webhookLog->wasRecentlyCreated && $webhookLog->processed) {
            return response()->json(['message' => 'Event already processed']);
        }

        $entity = $data['payload']['payout']['entity'] ?? null;
        $razorpayPayoutId = $entity['id'] ?? null;

        if ($razorpayPayoutId) {
            $payout = TurfPayout::where('razorpay_payout_id', $razorpayPayoutId)->first();

            if ($payout) {
                $walletService = new WalletService();

                if ($eventType === 'payout.processed') {
                    if ($payout->status !== 'completed') {
                        $payout->update([
                            'status' => 'completed',
                            'processed_at' => now(),
                        ]);
                    }
                } elseif (in_array($eventType, ['payout.failed', 'payout.reversed', 'payout.rejected'])) {
                    if ($payout->status === 'processing' || $payout->status === 'pending') {
                        $failureReason = $entity['status_details']['reason'] ?? ($entity['error']['description'] ?? 'Payout failed on Razorpay');

                        $payout->update([
                            'status' => 'failed',
                            'failure_reason' => $failureReason,
                        ]);

                        // Reversals: Credit full requested amount back to turf admin wallet
                        $walletService->applyDelta($payout->user, (float)$payout->requested_amount, 'payout_reversal', $payout);
                    }
                }
            }
        }

        $webhookLog->update(['processed' => true]);

        return response()->json(['status' => 'success']);
    }
}
