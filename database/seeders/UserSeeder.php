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
        'name'=>"Pourya Bakhshian",
        'email'=>"sa.bt@chmail.ir",

        'password'=>"123456789",
    ],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(["email"=>$user["email"]],$user);
        }
    }
}
