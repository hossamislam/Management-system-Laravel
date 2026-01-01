<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'hossam manager',
            'email' => 'manager@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        User::create([
            'name' => 'hossam Manager2',
            'email' => 'manager2@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        User::create([
            'name' => 'hossam User',
            'email' => 'user1@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'hossam User',
            'email' => 'user2@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        User::create([
            'name' => 'hossam User',
            'email' => 'user3@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
    }
}
