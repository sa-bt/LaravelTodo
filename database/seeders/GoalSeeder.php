<?php

namespace Database\Seeders;
use App\Models\Goal;
use App\Models\User;
use Illuminate\Database\Seeder;

class GoalSeeder extends Seeder
{
    /**
     * Seed the application's goals table.
     */
    public function run(): void
    {
                $user=User::where( ['email'=>"sa.bt@chmail.ir"])->first();

        $goals = [
            [
                'title' => 'Learn Laravel',
                'description' => 'Complete Laravel 12 basics and build a simple project.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],
            [
                'title' => 'Read a Book',
                'description' => 'Read 100 pages of a personal development book.',
                'status' => 'pending',
                'priority' => 'medium',
                'user_id' => $user->id,
            ],
            [
                'title' => 'Exercise',
                'description' => 'Workout at least 3 times a week.',
                'status' => 'pending',
                'priority' => 'low',
                'user_id' => $user->id,
            ],
        ];

        foreach ($goals as $goal) {
            Goal::firstOrCreate(["title"=>$goal["title"]],$goal);
        }
    }
}
