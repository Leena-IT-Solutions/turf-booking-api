<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response
            ->assertSeeVolt('pages.auth.forgot-password')
            ->assertStatus(200);
    }

    public function test_whatsapp_password_reset_otp_can_be_requested(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
        ]);

        $component = Volt::test('pages.auth.forgot-password')
            ->set('mobile', '9876543210')
            ->call('sendOtp');

        $component->assertHasNoErrors();
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'whatsapp_919876543210',
        ]);
    }

    public function test_password_can_be_reset_with_valid_whatsapp_otp(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
            'password' => Hash::make('oldpassword'),
        ]);

        $otp = '654321';

        $component = Volt::test('pages.auth.forgot-password')
            ->set('mobile', '9876543210')
            ->call('sendOtp');

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'whatsapp_919876543210'],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        $component->set('otp', $otp)
            ->set('password', 'newpassword123')
            ->set('password_confirmation', 'newpassword123')
            ->call('resetPassword');

        $component
            ->assertRedirect(route('login'))
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }
}
