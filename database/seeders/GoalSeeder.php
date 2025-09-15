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
                'title' => 'PHP/Laravel',
                'description' => 'Complete Laravel 12 basics and build a simple project.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'Docker',
                'description' => '',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'Git/GitHub',
                'description' => '',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'زبان انگلیسی',
                'description' => '.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'نصرت',
                'description' => '',
                'parent_id' => 4,
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'LearnIT',
                'description' => '',
                'parent_id' => 4,
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'لینوکس',
                'description' => 'Complete Laravel 12 basics and build a simple project.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],[
                'title' => 'تمرین خط',
                'description' => 'Complete Laravel 12 basics and build a simple project.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,

            ],
            [
                'title' => 'کتاب',
                'description' => 'Read 100 pages of a personal development book.',
                'status' => 'pending',
                'priority' => 'medium',
                'user_id' => $user->id,
            ],
            [
                'title' => 'شرکت',
                'description' => 'Read 100 pages of a personal development book.',
                'status' => 'pending',
                'priority' => 'medium',
                'user_id' => $user->id,
            ],
            [
                'title' => 'ورزش',
                'description' => 'Workout at least 3 times a week.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,
            ],
            [
                'title' => 'Vue.js',
                'description' => 'Workout at least 3 times a week.',
                'status' => 'pending',
                'priority' => 'high',
                'user_id' => $user->id,
            ],
        ];

        foreach ($goals as $goal) {
            Goal::firstOrCreate(["title"=>$goal["title"]],$goal);
        }
    }
}
