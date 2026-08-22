<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;
    /**
     * Test that the public home page loads successfully.
     */
    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('One Platform.');
        $response->assertSee('Two Experiences.');
        $response->assertSee('For Turf Owners');
        $response->assertSee('For Players');
        $response->assertSee('Still Managing Your Turf Manually?');
        $response->assertSee('Bookings scattered across WhatsApp');
        $response->assertSee('TurfBooking puts everything in one place.');
        $response->assertSee('Everything You Need to Run Your Turf');
        $response->assertSee('Manage Your Turf Smarter');
        $response->assertSee('Ready to Take Your Turf Business to the Next Level?');
        $response->assertSee('List Your Turf');
        $response->assertSee('Download TurfBooking App');
        $response->assertSee('Simple, Transparent Pricing');
        $response->assertSee('Subscription tiers');
        $response->assertSee('Free Listing');
        $response->assertSee('Pro Tier');
        $response->assertSee('Explore Free Plan');
        $response->assertSee('View Pro Plans');
        $response->assertSee('Business Benefits');
        $response->assertSee('More bookings');
        $response->assertSee('Less manual work');
        $response->assertSee('Better management');
        $response->assertSee('How It Works for Owners');
        $response->assertSee('How It Works for Players');
        $response->assertSee('Venue Management');
        $response->assertSee('Configure Calendar');
        $response->assertSee('Team Scheduling');
        $response->assertSee('Discover Venues');
        $response->assertSee('Find Your Perfect Turf on the TurfBooking App');
        $response->assertSee('Search nearby turfs, compare options');
        $response->assertSee('App Features');
        $response->assertSee('Live slot availability');
        $response->assertSee('Booking history');
    }

    /**
     * Test that the features page loads successfully.
     */
    public function test_features_page_loads_successfully(): void
    {
        $response = $this->get('/features');
        $response->assertStatus(200);
        $response->assertSee('Turf Management');
        $response->assertSee('Booking Management');
        $response->assertSee('Slot Management');
        $response->assertSee('Pricing');
        $response->assertSee('Financial Management');
        $response->assertSee('Operations');
        $response->assertSee('Reports');
    }

    /**
     * Test that the pricing page loads successfully.
     */
    public function test_pricing_page_loads_successfully(): void
    {
        $response = $this->get('/pricing');
        $response->assertStatus(200);
        $response->assertSee('Simple, Transparent');
        $response->assertSee('Free Listing');
        $response->assertSee('Pro');
        $response->assertSee('3,000');
        $response->assertSee('30,000');
    }

    public function test_contact_page_loads_successfully(): void
    {
        $response = $this->get('/contact');
        $response->assertStatus(200);
        $response->assertSee("Here to Help");
        $response->assertSee('leenaitsolutions@gmail.com');
        $response->assertSee('9096189183');
        $response->assertSee('B101 Sai Section, Hutatma Chowk, Kansai Section, Ambernath, Maharashtra 421501');
        $response->assertSee('Chat on WhatsApp');
        $response->assertSee('https://wa.me/919096189183');
    }

    /**
     * Test that the contact form saves messages to the database.
     */
    public function test_contact_form_saves_message_successfully(): void
    {
        \Livewire\Livewire::test('contact-form')
            ->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('user_type', 'owner')
            ->set('reason', 'Sales')
            ->set('contact_no', '9876543210')
            ->set('subject', 'Test Subject')
            ->set('message', 'This is a test message details.')
            ->call('submitForm')
            ->assertSet('success', true)
            ->assertSet('name', '')
            ->assertSet('email', '')
            ->assertSet('user_type', 'owner')
            ->assertSet('reason', '')
            ->assertSet('contact_no', '')
            ->assertSet('subject', '')
            ->assertSet('message', '');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'user_type' => 'owner',
            'reason' => 'Sales',
            'contact_no' => '9876543210',
            'subject' => 'Test Subject',
            'message' => 'This is a test message details.',
            'is_read' => false,
        ]);
    }

    /**
     * Test that the contact form validates contact number formatting.
     */
    public function test_contact_form_validates_contact_number(): void
    {
        // 1. Invalid (non-numeric / letters)
        \Livewire\Livewire::test('contact-form')
            ->set('contact_no', 'abc1234567')
            ->call('submitForm')
            ->assertHasErrors(['contact_no' => 'regex']);

        // 2. Invalid (less than 10 digits)
        \Livewire\Livewire::test('contact-form')
            ->set('contact_no', '98765432')
            ->call('submitForm')
            ->assertHasErrors(['contact_no' => 'regex']);

        // 3. Invalid (starts with 5)
        \Livewire\Livewire::test('contact-form')
            ->set('contact_no', '5876543210')
            ->call('submitForm')
            ->assertHasErrors(['contact_no' => 'regex']);

        // 4. Valid (starts with 9, 10 digits)
        \Livewire\Livewire::test('contact-form')
            ->set('contact_no', '9876543210')
            ->call('submitForm')
            ->assertHasNoErrors(['contact_no' => 'regex']);
    }

    /**
     * Test that the for turf owners page loads successfully.
     */
    public function test_for_turf_owners_page_loads_successfully(): void
    {
        $response = $this->get('/for-turf-owners');
        $response->assertStatus(200);
        $response->assertSee('For Turf Owners');
        $response->assertSee('Scale Your Sports Complex');
        $response->assertSee('Why List Your Turf?');
        $response->assertSee('How TurfBooking Works');
        $response->assertSee('Dashboard features');
        $response->assertSee('Subscription plans');
        $response->assertSee('Benefits');
        $response->assertSee('FAQs');
        $response->assertSee('Register Your Turf');
    }

    /**
     * Test that the download page loads successfully.
     */
    public function test_download_page_loads_successfully(): void
    {
        $response = $this->get('/download');
        $response->assertStatus(200);
        $response->assertSee('Companion Player App');
        $response->assertSee('Your Pocket');
    }

    /**
     * Test that the how it works page loads successfully.
     */
    public function test_how_it_works_page_loads_successfully(): void
    {
        $response = $this->get('/how-it-works');
        $response->assertStatus(200);
        $response->assertSee('How TurfBooking Works');
        $response->assertSee('How It Works for Turf Owners');
        $response->assertSee('How It Works for Players');
        $response->assertSee('Your Next Game Is Just a Few Taps Away.');
    }

    /**
     * Test that the faqs page loads successfully.
     */
    public function test_faqs_page_loads_successfully(): void
    {
        $response = $this->get('/faqs');
        $response->assertStatus(200);
        $response->assertSee('Frequently Asked Questions');
        $response->assertSee('Player FAQs');
        $response->assertSee('Turf Owner FAQs');
        $response->assertSee('Can I book a turf from the website?');
        $response->assertSee('Can I list my turf for free?');
    }
}
