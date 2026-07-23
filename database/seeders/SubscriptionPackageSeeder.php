<?php

namespace Database\Seeders;

use App\Models\SubscriptionPackage;
use Illuminate\Database\Seeder;

class SubscriptionPackageSeeder extends Seeder
{
    public function run(): void
    {
        if (SubscriptionPackage::count() === 0) {
            SubscriptionPackage::create([
                'name' => 'Starter Monthly',
                'description' => 'Ideal for newly launched single-turf grounds starting out with digital bookings.',
                'amount' => 1499.00,
                'days' => 30,
                'total_percentage' => 5.00,
                'payment_gateway_percentage' => 2.00,
                'commission_percentage' => 3.00,
                'is_active' => true,
                'from_date' => now()->toDateString(),
                'to_date' => now()->addYear()->toDateString(),
                'sort_order' => 1,
                'features' => [
                    'Single Turf Management',
                    'Mobile & Web Bookings',
                    '24/7 Standard Support'
                ],
            ]);

            SubscriptionPackage::create([
                'name' => 'Pro Turf Partner',
                'description' => 'Most popular growth plan for active multi-court sports facilities needing full reporting & slot controls.',
                'amount' => 3999.00,
                'days' => 90,
                'total_percentage' => 4.00,
                'payment_gateway_percentage' => 1.80,
                'commission_percentage' => 2.20,
                'is_active' => true,
                'from_date' => now()->toDateString(),
                'to_date' => now()->addYear()->toDateString(),
                'sort_order' => 2,
                'features' => [
                    'Custom Coupon & Slot Locks',
                    'CSV Revenue Export Reports',
                    'Push Notifications Enabled'
                ],
            ]);

            SubscriptionPackage::create([
                'name' => 'Enterprise Annual',
                'description' => 'Complete annual solution with zero commission cap for premium multi-location sports complexes.',
                'amount' => 12999.00,
                'days' => 365,
                'total_percentage' => 3.00,
                'payment_gateway_percentage' => 1.50,
                'commission_percentage' => 1.50,
                'is_active' => true,
                'from_date' => now()->toDateString(),
                'to_date' => now()->addYears(2)->toDateString(),
                'sort_order' => 3,
                'features' => [
                    'Lowest Gateway Fee (1.5%)',
                    'Dedicated Account Manager',
                    'Priority 24/7 Phone Support'
                ],
            ]);
        }
    }
}
