<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Sandeep Rathod
        $sandeep = User::firstOrCreate([
            'email' => 'sandeep198558@gmail.com',
        ], [
            'name' => 'Sandeep Rathod',
            'mobile' => '9664588677',
            'password' => Hash::make('password'),
        ]);

        $sandeep->roles()->sync(
            Role::whereIn('name', ['saas-admin', 'customer'])->pluck('id')->toArray()
        );

        // 2. Leena Adam
        $leena = User::firstOrCreate([
            'email' => 'leenaadam28@gmail.com',
        ], [
            'name' => 'Leena Adam',
            'mobile' => '9769409405',
            'password' => Hash::make('password'),
        ]);

        $leena->roles()->sync(
            Role::whereIn('name', ['manager', 'customer'])->pluck('id')->toArray()
        );
    }
}
