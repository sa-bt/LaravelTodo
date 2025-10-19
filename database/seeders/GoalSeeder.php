<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Goal;
use App\Models\User;
use App\Models\Task;
use Carbon\Carbon;

class GoalSeeder extends Seeder
{
    /**
     * Seed the application's goals table.
     */
    public function run(): void
    {
        $user = User::where('email', 'sa.bt@chmail.ir')->first();

        if (!$user) {
            echo "User with email sa.bt@chmail.ir not found. Cannot seed goals and tasks.\n";
            return;
        }

        $goalsData = [
            ['title' => 'PHP/Laravel', 'description' => 'Complete Laravel 12 basics and build a simple project.', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:15:00'],
            ['title' => 'Docker', 'description' => '', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:45:00'],
            ['title' => 'Git/GitHub', 'description' => '', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => false, 'reminder_time' => null],
            ['title' => 'زبان انگلیسی', 'description' => '.', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:05:00'],
            // هشدار: parent_id عدد ثابت است؛ اگر محیط‌ها متفاوت باشند، بهتر است با عنوان والد ست شود.
            ['title' => 'نصرت',  'description' => '', 'parent_id' => 4, 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:30:00'],
            ['title' => 'LearnIT', 'description' => '', 'parent_id' => 4, 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '23:00:00'],
            ['title' => 'لینوکس', 'description' => 'Complete Laravel 12 basics and build a simple project.', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:20:00'],
            ['title' => 'تمرین خط', 'description' => 'Complete Laravel 12 basics and build a simple project.', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:50:00'],
            ['title' => 'کتاب', 'description' => 'Read 100 pages of a personal development book.', 'status' => 'pending', 'priority' => 'medium', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:40:00'],
            ['title' => 'شرکت', 'description' => 'Read 100 pages of a personal development book.', 'status' => 'pending', 'priority' => 'medium', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:00:00'],
            ['title' => 'ورزش', 'description' => 'Workout at least 3 times a week.', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:35:00'],
            ['title' => 'Vue.js', 'description' => 'Workout at least 3 times a week.', 'status' => 'pending', 'priority' => 'high', 'user_id' => $user->id, 'send_task_reminder' => true,  'reminder_time' => '22:55:00'],
        ];

        // فاز ۱: ساخت/به‌روزرسانی Goalها
        foreach ($goalsData as $goalData) {
            Goal::firstOrCreate(
                ['title' => $goalData['title']],
                $goalData
            );
        }

        // فاز ۲: فقط برای Goalهای برگ (بدون فرزند) تسک بساز
        $leafGoals = Goal::where('user_id', $user->id)
            ->whereDoesntHave('children')
            ->get();

        // بازه ۴۵ روزه: ۱۵ روز قبل تا ۳۰ روز بعد
        $today     = Carbon::today();
        $startDate = $today->copy()->subDays(15); // 15 روز قبل
        $daysTotal = 45;                          // مجموع روزها

        foreach ($leafGoals as $goal) {
            for ($i = 0; $i < $daysTotal; $i++) {
                $date = $startDate->copy()->addDays($i)->toDateString();

                // رندوم فقط برای گذشته و امروز؛ آینده همیشه false
                $isPastOrToday = Carbon::parse($date)->lte($today);
                $done = $isPastOrToday ? (bool) random_int(0, 1) : false;

                // عنوان را معنادار بر اساس تاریخ بسازیم
                $title = "تسک تاریخ {$date}: {$goal->title}";

                Task::updateOrCreate(
                    [
                        'goal_id' => $goal->id,
                        'day'     => $date,
                    ],
                    [
                        'title'   => $title,
                        'is_done' => $done,
                    ]
                );
            }
        }
    }
}
