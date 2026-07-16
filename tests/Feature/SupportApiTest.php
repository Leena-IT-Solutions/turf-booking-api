<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\SupportMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class SupportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_support_messages(): void
    {
        $this->getJson('/api/support/messages')->assertStatus(401);
        $this->postJson('/api/support/messages', ['message' => 'Hello'])->assertStatus(401);
    }

    public function test_customer_can_send_support_message(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/support/messages', [
                'message' => 'Need help with my booking.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Need help with my booking.')
            ->assertJsonPath('user_id', $customer->id)
            ->assertJsonPath('sender_id', $customer->id)
            ->assertJsonPath('is_read_by_admin', false)
            ->assertJsonPath('is_read_by_user', true);

        $this->assertDatabaseHas('support_messages', [
            'user_id' => $customer->id,
            'sender_id' => $customer->id,
            'message' => 'Need help with my booking.',
            'is_read_by_admin' => false,
            'is_read_by_user' => true,
        ]);
    }

    public function test_customer_can_retrieve_and_mark_messages_read(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        
        $admin = User::factory()->create();
        $admin->assignRole('turf-admin');

        // 1. Customer sends a message
        SupportMessage::create([
            'user_id' => $customer->id,
            'sender_id' => $customer->id,
            'message' => 'Customer question',
            'is_read_by_user' => true,
            'is_read_by_admin' => false,
        ]);

        // 2. Admin replies
        SupportMessage::create([
            'user_id' => $customer->id,
            'sender_id' => $admin->id,
            'message' => 'Admin response',
            'is_read_by_user' => false,
            'is_read_by_admin' => true,
        ]);

        // 3. Customer retrieves messages
        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/support/messages');

        $response->assertStatus(200)
            ->assertJsonCount(2);

        // Verify the admin response is now marked as read by user in the database
        $this->assertDatabaseHas('support_messages', [
            'user_id' => $customer->id,
            'sender_id' => $admin->id,
            'message' => 'Admin response',
            'is_read_by_user' => true,
        ]);
    }
}
