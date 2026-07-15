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
        User::create([
            'name' => 'Sandeep Rathod',
            'email' => 'sandeep198558@gmail.com',
            'mobile' => '9664588677',
            'password' => Hash::make('password'),
        ]);

        User::create([
            'name' => 'Leena Adam',
            'email' => 'leenaadam28@gmail.com',
            'mobile' => '9769409405',
            'password' => Hash::make('password'),
        ]);
    }
}
