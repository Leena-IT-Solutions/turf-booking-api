<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WhatsAppAuthOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_whatsapp_otp_fails_for_registration_if_mobile_already_registered(): void
    {
        User::factory()->create([
            'mobile' => '9876543210',
            'is_quick_created' => false,
        ]);

        $response = $this->postJson('/api/send-whatsapp-otp', [
            'mobile' => '9876543210',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This mobile number is already registered.');
    }

    public function test_send_whatsapp_otp_succeeds_for_new_registration_mobile(): void
    {
        $response = $this->postJson('/api/send-whatsapp-otp', [
            'mobile' => '9988776655',
            'purpose' => 'registration',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'WhatsApp OTP sent successfully.');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'whatsapp_919988776655',
        ]);
    }

    public function test_registration_fails_without_whatsapp_otp_verification(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'mobile' => '9988776655',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'WhatsApp OTP verification is required before registration.');
    }

    public function test_registration_succeeds_after_whatsapp_otp_verification(): void
    {
        $mobile = '9988776655';
        $otp = '123456';

        // 1. Dispatch OTP
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'whatsapp_91' . $mobile],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        // 2. Verify OTP
        $verifyResponse = $this->postJson('/api/verify-whatsapp-otp', [
            'mobile' => $mobile,
            'otp' => $otp,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('message', 'WhatsApp OTP verified successfully.');

        \App\Models\Role::firstOrCreate(['name' => 'customer'], ['display_name' => 'Customer']);

        // 3. Register User
        $registerResponse = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'mobile' => $mobile,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse->assertStatus(201)
            ->assertJsonStructure(['access_token', 'token_type', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
            'mobile' => $mobile,
        ]);
    }

    public function test_forgot_password_otp_fails_if_user_does_not_exist(): void
    {
        $response = $this->postJson('/api/send-whatsapp-otp', [
            'mobile' => '9999999999',
            'purpose' => 'forgot_password',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('message', 'No user found with this mobile number.');
    }

    public function test_forgot_password_and_reset_password_with_whatsapp_otp(): void
    {
        $user = User::factory()->create([
            'mobile' => '919876543210',
            'password' => Hash::make('oldpassword'),
        ]);

        $otp = '654321';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'whatsapp_919876543210'],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        $resetResponse = $this->postJson('/api/reset-password', [
            'mobile' => '9876543210',
            'otp' => $otp,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $resetResponse->assertStatus(200)
            ->assertJsonPath('message', 'Password reset successfully.');

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}
