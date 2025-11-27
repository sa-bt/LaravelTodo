<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's goals table.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => "Pourya Bakhshian",
                'email' => "bakhshian2020@google.com",

                'password' => "123456789",
            ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(["email" => $user["email"]], $user);
        }
    }
}
