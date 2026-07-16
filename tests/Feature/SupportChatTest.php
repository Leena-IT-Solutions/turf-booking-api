<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SupportMessage;
use Livewire\Volt\Volt;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_is_redirected_from_chat_manager(): void
    {
        $this->get('/turf/support')->assertRedirect('/login');
    }

    public function test_customer_cannot_access_chat_manager(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $this->actingAs($customer)->get('/turf/support')->assertStatus(403);
    }

    public function test_admin_can_access_chat_manager_and_see_chat_elements(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        // Initiate conversation
        SupportMessage::create([
            'user_id' => $customer->id,
            'sender_id' => $customer->id,
            'message' => 'Please reply ASAP',
            'is_read_by_user' => true,
            'is_read_by_admin' => false,
        ]);

        $this->actingAs($admin)->get('/turf/support')->assertOk();

        Volt::test('turf.chat-manager')
            ->assertSee($customer->name)
            ->assertSee('Please reply ASAP');
    }

    public function test_admin_can_reply_to_customer_messages(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        // Initiate conversation
        SupportMessage::create([
            'user_id' => $customer->id,
            'sender_id' => $customer->id,
            'message' => 'First message',
            'is_read_by_user' => true,
            'is_read_by_admin' => false,
        ]);

        Volt::actingAs($admin)
            ->test('turf.chat-manager')
            ->call('selectCustomer', $customer->id)
            ->set('replyMessage', 'Here is my reply')
            ->call('sendReply')
            ->assertSet('replyMessage', '');

        // Verify databases has admin's reply
        $this->assertDatabaseHas('support_messages', [
            'user_id' => $customer->id,
            'sender_id' => $admin->id,
            'message' => 'Here is my reply',
            'is_read_by_admin' => true,
            'is_read_by_user' => false,
        ]);

        // Verify the customer's initial message was marked read by admin
        $this->assertDatabaseHas('support_messages', [
            'user_id' => $customer->id,
            'sender_id' => $customer->id,
            'message' => 'First message',
            'is_read_by_admin' => true,
        ]);
    }
}
