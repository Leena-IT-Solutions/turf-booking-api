<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'saas-admin',
                'display_name' => 'Saas Admin',
            ],
            [
                'name' => 'turf-admin',
                'display_name' => 'Turf Admin',
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
            ],
            [
                'name' => 'customer',
                'display_name' => 'Customer',
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}
