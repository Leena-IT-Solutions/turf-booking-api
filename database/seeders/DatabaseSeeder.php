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

        $sandeep = User::firstOrCreate([
            'email' => 'sandeep198558@gmail.com',
        ], [
            'name' => 'Sandeep Rathod',
            'mobile' => '9664588677',
            'password' => Hash::make('password'),
        ]);
        
        $sandeep->roles()->sync(
            \App\Models\Role::whereIn('name', ['saas-admin', 'turf-admin', 'manager', 'customer'])->pluck('id')->toArray()
        );

        $leena = User::firstOrCreate([
            'email' => 'leenaadam28@gmail.com',
        ], [
            'name' => 'Leena Adam',
            'mobile' => '9769409405',
            'password' => Hash::make('password'),
        ]);

        $leena->roles()->sync(
            \App\Models\Role::whereIn('name', ['saas-admin', 'turf-admin', 'manager', 'customer'])->pluck('id')->toArray()
        );

        $this->call(LocationSeeder::class);
    }
}
