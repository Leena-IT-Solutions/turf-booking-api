<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_users_can_register(): void
    {
        $mobile = '9876543210';
        $otp = '123456';

        $component = Volt::test('pages.auth.register')
            ->set('name', 'Test User')
            ->set('email', 'test@example.com')
            ->set('mobile', $mobile)
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('sendOtp');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'whatsapp_91' . $mobile,
        ]);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'whatsapp_91' . $mobile],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        $component->set('otp', $otp);
        $component->call('verifyAndRegister');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('customer'));
    }
}
