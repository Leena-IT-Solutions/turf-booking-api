<?php

namespace Tests\Feature;

use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    /**
     * Test that the public home page loads successfully.
     */
    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Run Your Sports Venue');
        $response->assertSee('SaaS Platform for Turf Owners');
    }

    /**
     * Test that the features page loads successfully.
     */
    public function test_features_page_loads_successfully(): void
    {
        $response = $this->get('/features');
        $response->assertStatus(200);
        $response->assertSee('Smart Slot Management');
        $response->assertSee('Payment Integration');
    }

    /**
     * Test that the pricing page loads successfully.
     */
    public function test_pricing_page_loads_successfully(): void
    {
        $response = $this->get('/pricing');
        $response->assertStatus(200);
        $response->assertSee('Simple, Transparent');
        $response->assertSee('Starter Plan');
        $response->assertSee('Growth Plan');
    }

    /**
     * Test that the contact page loads successfully.
     */
    public function test_contact_page_loads_successfully(): void
    {
        $response = $this->get('/contact');
        $response->assertStatus(200);
        $response->assertSee("Here to Help");
        $response->assertSee('support@turfbooking.com');
    }
}
