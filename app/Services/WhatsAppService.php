<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $token;
    protected string $phoneNumberId;
    protected string $templateName;

    public function __construct()
    {
        $setting = \App\Models\SaasSetting::first();
        $this->token = $setting?->whatsapp_token ?? env('WHATSAPP_TOKEN', '');
        $this->phoneNumberId = $setting?->whatsapp_phone_number_id ?? env('WHATSAPP_PHONE_NUMBER_ID', '');
        $this->templateName = $setting?->whatsapp_otp_template ?: (env('WHATSAPP_OTP_TEMPLATE') ?: 'turf_otp');
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
            Log::warning("WhatsApp credentials missing. OTP {$otp} not sent via WhatsApp to {$cleanMobile}.");
            return false;
        }

        $url = "https://graph.facebook.com/v20.0/{$this->phoneNumberId}/messages";

        try {
            // 1. Primary Attempt: Body Parameter Template (e.g. "{{1}} is your verification code...")
            foreach (['en_US', 'en'] as $lang) {
                $templateResponse = Http::withToken($this->token)
                    ->post($url, [
                        'messaging_product' => 'whatsapp',
                        'to' => $cleanMobile,
                        'type' => 'template',
                        'template' => [
                            'name' => $this->templateName,
                            'language' => [
                                'code' => $lang,
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => (string) $otp,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ]);

                if ($templateResponse->successful()) {
                    Log::info("WhatsApp OTP template '{$this->templateName}' ({$lang}) sent successfully with OTP {$otp} to {$cleanMobile}.");
                    return true;
                }
            }

            // 2. Secondary Attempt: Authentication Copy-Code Button Template
            foreach (['en_US', 'en'] as $lang) {
                $authButtonResponse = Http::withToken($this->token)
                    ->post($url, [
                        'messaging_product' => 'whatsapp',
                        'to' => $cleanMobile,
                        'type' => 'template',
                        'template' => [
                            'name' => $this->templateName,
                            'language' => [
                                'code' => $lang,
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => (string) $otp,
                                        ]
                                    ]
                                ],
                                [
                                    'type' => 'button',
                                    'sub_type' => 'url',
                                    'index' => '0',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => (string) $otp,
                                        ]
                                    ]
                                ]
                            ]
                        ],
                    ]);

                if ($authButtonResponse->successful()) {
                    Log::info("WhatsApp Authentication Button OTP template '{$this->templateName}' ({$lang}) sent successfully with OTP {$otp} to {$cleanMobile}.");
                    return true;
                }
            }

            // 3. Tertiary Attempt: Direct freeform text message (for users within active 24h customer window)
            $messageText = "Your TurfBooking OTP code for {$purpose} is: {$otp}. It is valid for 10 minutes. Please do not share it with anyone.";
            $textResponse = Http::withToken($this->token)
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

            if ($textResponse->successful()) {
                Log::info("WhatsApp text OTP {$otp} sent successfully to {$cleanMobile}.");
                return true;
            }

            Log::error("WhatsApp API All Attempts Failed for {$cleanMobile}. Template: {$this->templateName}.");
            return false;

        } catch (\Throwable $e) {
            Log::error("WhatsApp API Exception: " . $e->getMessage());
            return false;
        }
    }
}
