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
        // 1. Sandeep Rathod (Protected Master Admin)
        $sandeep = User::where('mobile', '9664588677')
            ->orWhereIn('email', ['sandeep198558@yahoo.com', 'sandeep198558@gmail.com'])
            ->first();

        if (! $sandeep) {
            $sandeep = User::create([
                'name' => 'Sandeep Rathod',
                'email' => 'sandeep198558@yahoo.com',
                'mobile' => '9664588677',
                'password' => Hash::make('password'),
            ]);
        }

        $sandeep->roles()->sync(
            Role::whereIn('name', ['saas-admin', 'customer'])->pluck('id')->toArray()
        );
    }
}
