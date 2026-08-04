<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle Meta WhatsApp Webhook Verification (GET) and Events (POST).
     */
    public function handle(Request $request)
    {
        // 1. Meta Webhook Verification Challenge (GET request)
        if ($request->isMethod('get')) {
            $verifyToken = 'turf_booking_whatsapp_verify_token';
            
            $mode = $request->query('hub_mode');
            $token = $request->query('hub_verify_token');
            $challenge = $request->query('hub_challenge');

            if ($mode === 'subscribe' && $token === $verifyToken) {
                Log::info("WhatsApp Webhook verified successfully.");
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            }

            Log::warning("WhatsApp Webhook verification failed.", ['request' => $request->all()]);
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 2. Incoming Messages & Delivery Status Updates (POST request)
        if ($request->isMethod('post')) {
            $data = $request->all();
            Log::info("WhatsApp Webhook Event Received:", $data);

            return response()->json(['status' => 'success'], 200);
        }

        return response()->json(['message' => 'Method not allowed'], 405);
    }
}
