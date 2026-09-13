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
                'commission_percentage' => 0.00,
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

        // Pro Multi-Court Plan
        SubscriptionPackage::updateOrCreate(
            ['name' => 'Pro Multi-Court Partner'],
            [
                'description' => 'Designed for busy multi-court sports facilities with advanced analytics, custom coupons, and priority listing.',
                'monthly_amount' => 5000.00,
                'yearly_amount' => 50000.00,
                'commission_percentage' => 0.00,
                'is_active' => true,
                'sort_order' => 2,
                'is_offer_active' => true,
                'offer_badge' => '⚡ Early Bird Special',
                'offer_monthly_amount' => 1999.00,
                'offer_yearly_amount' => 19999.00,
                'offer_max_claims' => 50,
                'offer_claimed_count' => 0,
                'offer_expires_at' => null,
                'features' => [
                    'All Standard Partner Features',
                    'Multi-Pitch Simultaneous Scheduler',
                    'Custom Turf Promos & Coupons Generator',
                    'Customer Database & Export Reports',
                    'Featured Partner Badge on Player App',
                    'Zero Booking Commission',
                ],
            ]
        );
    }
}
