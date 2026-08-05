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

            // 1. First attempt: Template Message (Meta Sandbox requires template message for initial contact)
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
                Log::info("WhatsApp template OTP sent successfully to {$cleanMobile}.");
                return true;
            }

            // 2. Second attempt: Direct text message
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
                Log::info("WhatsApp text OTP sent successfully to {$cleanMobile}.");
                return true;
            }

            Log::error("WhatsApp API Text Error: " . $response->body() . " | Template Error: " . $templateResponse->body());
            return false;
        } catch (\Throwable $e) {
            Log::error("WhatsApp API Exception: " . $e->getMessage());
            return false;
        }
    }
}

