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

        $sandeep = User::create([
            'name' => 'Sandeep Rathod',
            'email' => 'sandeep198558@gmail.com',
            'mobile' => '9664588677',
            'password' => Hash::make('password'),
        ]);
        $sandeep->assignRole('saas-admin');
        $sandeep->assignRole('turf-admin');
        $sandeep->assignRole('manager');
        $sandeep->assignRole('customer');

        $leena = User::create([
            'name' => 'Leena Adam',
            'email' => 'leenaadam28@gmail.com',
            'mobile' => '9769409405',
            'password' => Hash::make('password'),
        ]);
        $leena->assignRole('saas-admin');
        $leena->assignRole('turf-admin');
        $leena->assignRole('manager');
        $leena->assignRole('customer');
    }
}
