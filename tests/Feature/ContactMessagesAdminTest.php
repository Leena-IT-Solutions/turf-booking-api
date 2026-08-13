<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactMessagesAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_contact_messages_manager(): void
    {
        $response = $this->get('/saas/contact-messages');
        $response->assertRedirect('/login');
    }

    public function test_non_saas_admin_cannot_access_contact_messages_manager(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $response = $this->actingAs($customer)->get('/saas/contact-messages');
        $response->assertStatus(403);
    }

    public function test_saas_admin_can_access_contact_messages_manager(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $response = $this->actingAs($admin)->get('/saas/contact-messages');
        $response->assertOk();
    }

    public function test_contact_messages_livewire_component_functions(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('saas-admin');

        $msg1 = ContactMessage::create([
            'name' => 'Alice Jones',
            'email' => 'alice@example.com',
            'user_type' => 'owner',
            'reason' => 'Listing',
            'contact_no' => '9876543210',
            'subject' => 'Inquiry about cricket net booking options',
            'message' => 'Hello, I wanted to know if cricket nets are included.',
            'is_read' => false,
        ]);

        $msg2 = ContactMessage::create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'user_type' => 'player',
            'reason' => 'App support',
            'contact_no' => '9876543211',
            'subject' => 'Dynamic rate configs',
            'message' => 'Help with configuring custom slots.',
            'is_read' => true,
        ]);

        // Access manager and check default list contains both messages
        Livewire::actingAs($admin)
            ->test('saas.contact-messages')
            ->assertSee('Alice Jones')
            ->assertSee('bob@example.com')
            ->assertSee('Inquiry about cricket')
            ->assertSee('Dynamic rate configs')
            
            // Search filtration
            ->set('search', 'cricket')
            ->assertSee('Alice Jones')
            ->assertDontSee('Bob Smith')
            ->set('search', '')
            
            // Status filtration
            ->set('status', 'unread')
            ->assertSee('Alice Jones')
            ->assertDontSee('Bob Smith')
            
            // Open modal to view message details and mark as read
            ->set('status', 'all')
            ->call('openDetailModal', $msg1->id)
            ->assertSet('showDetailModal', true)
            ->assertSet('selectedMessageId', $msg1->id)
            ->assertSee('Hello, I wanted to know if cricket nets are included.')
            
            // Close modal
            ->call('closeDetailModal')
            ->assertSet('showDetailModal', false);

        // Assert message 1 was marked as read
        $this->assertTrue($msg1->fresh()->is_read);

        // Mark as unread
        Livewire::actingAs($admin)
            ->test('saas.contact-messages')
            ->call('markAsUnread', $msg1->id);

        $this->assertFalse($msg1->fresh()->is_read);

        // Delete message
        Livewire::actingAs($admin)
            ->test('saas.contact-messages')
            ->call('confirmDelete', $msg2->id)
            ->assertSet('showDeleteConfirm', true)
            ->assertSet('deletingId', $msg2->id)
            ->call('performDelete')
            ->assertSet('showDeleteConfirm', false);

        $this->assertDatabaseMissing('contact_messages', ['id' => $msg2->id]);
    }
}
