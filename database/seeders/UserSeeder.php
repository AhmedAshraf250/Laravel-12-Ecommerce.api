<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'type' => 'admin',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Customer User',
                'email' => 'customer@example.com',
                'type' => 'customer',
                'password' => Hash::make('password'),
            ],
            [
                'name' => 'Delivery User',
                'email' => 'delivery@example.com',
                'type' => 'delivery',
                'password' => Hash::make('password'),
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create($userData);
            $user->assignRole($userData['type']);
        }
    }
}
