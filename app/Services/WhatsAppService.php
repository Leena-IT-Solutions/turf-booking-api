<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $token;
    protected string $phoneNumberId;

    public function __construct()
    {
        $setting = \App\Models\SaasSetting::first();
        $this->token = $setting?->whatsapp_token ?? env('WHATSAPP_TOKEN', '');
        $this->phoneNumberId = $setting?->whatsapp_phone_number_id ?? env('WHATSAPP_PHONE_NUMBER_ID', '');
    }

    /**
     * Send OTP message via Meta WhatsApp Cloud API.
     */
    public function sendOtp(string $mobile, string $otp, string $purpose = 'verification'): bool
    {
        // Clean mobile number - format to E.164 (e.g., 919664588677 or +919664588677 -> 919664588677)
        $cleanMobile = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($cleanMobile) === 10) {
            $cleanMobile = '91' . $cleanMobile; // Default to India (+91) if 10 digits
        }

        if (empty($this->token) || empty($this->phoneNumberId)) {
            Log::warning("WhatsApp credentials missing in .env. OTP {$otp} not sent via WhatsApp to {$cleanMobile}.");
            return false;
        }

        $url = "https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages";

        $messageText = "Your TurfBooking OTP code for {$purpose} is: {$otp}. It is valid for 10 minutes. Please do not share it with anyone.";

        try {
            // First attempt sending as freeform text message (works during 24-hr customer service window or sandbox testing)
            $response = Http::withToken($this->token)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $cleanMobile,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $messageText,
                    ],
                ]);

            if ($response->successful()) {
                Log::info("WhatsApp OTP sent successfully to {$cleanMobile}.");
                return true;
            }

            // Fallback: Attempt template message if text message fails due to template policy
            $templateResponse = Http::withToken($this->token)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'to' => $cleanMobile,
                    'type' => 'template',
                    'template' => [
                        'name' => 'hello_world',
                        'language' => [
                            'code' => 'en_US',
                        ],
                    ],
                ]);

            if ($templateResponse->successful()) {
                Log::info("WhatsApp test template sent to {$cleanMobile}.");
                return true;
            }

            Log::error("WhatsApp API error: " . $response->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("WhatsApp API Exception: " . $e->getMessage());
            return false;
        }
    }
}
