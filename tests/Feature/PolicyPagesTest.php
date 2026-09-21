<?php

namespace Tests\Feature;

use Tests\TestCase;

class PolicyPagesTest extends TestCase
{
    public function test_terms_and_conditions_page_is_accessible(): void
    {
        $response = $this->get('/terms-and-conditions');
        $response->assertStatus(200);
        $response->assertSee('Terms & Conditions', false);
        $response->assertSee('LEENA IT SOLUTIONS', false);
        $response->assertSee('Ambernath East', false);
    }

    public function test_privacy_policy_page_is_accessible(): void
    {
        $response = $this->get('/privacy-policy');
        $response->assertStatus(200);
        $response->assertSee('Privacy Policy', false);
        $response->assertSee('LEENA IT SOLUTIONS', false);
        $response->assertSee('Grievance Officer', false);
    }

    public function test_refund_and_cancellation_policy_page_is_accessible(): void
    {
        $response = $this->get('/refund-and-cancellation-policy');
        $response->assertStatus(200);
        $response->assertSee('Refund & Cancellation Policy', false);
        $response->assertSee('7 days', false);
        $response->assertSee('LEENA IT SOLUTIONS', false);
    }

    public function test_return_policy_page_is_accessible(): void
    {
        $response = $this->get('/return-policy');
        $response->assertStatus(200);
        $response->assertSee('Return Policy', false);
        $response->assertSee('7 days', false);
        $response->assertSee('LEENA IT SOLUTIONS', false);
    }

    public function test_shipping_policy_page_is_accessible(): void
    {
        $response = $this->get('/shipping-policy');
        $response->assertStatus(200);
        $response->assertSee('Shipping Policy', false);
        $response->assertSee('7 days', false);
        $response->assertSee('LEENA IT SOLUTIONS', false);
    }

    public function test_policies_api_endpoint_returns_json(): void
    {
        $response = $this->getJson('/api/policies');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'company',
            'address',
            'email',
            'phone',
            'policies' => [
                '*' => ['id', 'title', 'url'],
            ],
        ]);
        $response->assertJsonFragment([
            'company' => 'LEENA IT SOLUTIONS',
        ]);
    }
}
