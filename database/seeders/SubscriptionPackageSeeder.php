<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        // Create or update the primary Standard Turf Partner package with the ₹999 launch offer
        SubscriptionPackage::updateOrCreate(
            ['name' => 'Standard Turf Partner'],
            [
                'description' => 'Complete software suite for turf management, staff logins, WhatsApp booking notifications, and slot scheduling.',
                'monthly_amount' => 3000.00,
                'yearly_amount' => 30000.00,
                'is_active' => true,
                'sort_order' => 1,
                'is_offer_active' => true,
                'offer_badge' => '🔥 First 100 Turfs Founder Offer',
                'offer_monthly_amount' => 999.00,
                'offer_yearly_amount' => 9999.00,
                'offer_max_claims' => 100,
                'offer_claimed_count' => 0,
                'offer_expires_at' => null,
                'features' => [
                    'Unlimited Slots & Pitch Booking Management',
                    'Automated WhatsApp Booking Confirmations',
                    'Staff & Manager Access Controls',
                    'Instant Online & Part Payment Collection',
                    'Zero Platform Commission Cap',
                    '24/7 Dedicated Partner Support',
                ],
            ]
        );
    }
}
