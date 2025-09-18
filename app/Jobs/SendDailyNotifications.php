<?php

namespace App\Jobs;


use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Notifications\DailyReportNotification;
use Carbon\Carbon;

class SendTaskNotification implements ShouldQueue
{
   use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle()
    {
        $now = Carbon::now()->format('H:i');

        User::chunk(50, function($users) use ($now) {
            foreach ($users as $user) {
                // گزارش روزانه
                if ($user->daily_report && $user->report_time === $now) {
                    $user->notify(new DailyReportNotification($user, 'report'));
                }

                // یادآوری تسک
                if ($user->task_reminder && $user->task_reminder_time === $now) {
                    $user->notify(new DailyReportNotification($user, 'task'));
                }
            }
        });
    }
}
