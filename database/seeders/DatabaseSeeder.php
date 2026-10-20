<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(SlotCategorySeeder::class);
        $this->call(SlotSeeder::class);
        $this->call(SaasSettingSeeder::class);
        $this->call(SliderImageSeeder::class);
        $this->call(FacilitySeeder::class);
        $this->call(EquipmentSeeder::class);
        $this->call(SportSeeder::class);
        $this->call(PaymentGatewayChargeSeeder::class);
        $this->call(SubscriptionPackageSeeder::class);
        $this->call(UserSeeder::class);
    }
}
