<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'John Employee',
            'email' => 'employee@example.com',
            'password' => bcrypt('password'),
            'role' => 'employee',
        ]);

        User::create([
            'name' => 'Jane Approver',
            'email' => 'approver@example.com',
            'password' => bcrypt('password'),
            'role' => 'approver',
        ]);
    }
}
