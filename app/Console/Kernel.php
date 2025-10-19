<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\SendDailyNotifications;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */

    protected function scheduleTimezone(): ?\DateTimeZone
    {
        return new \DateTimeZone(config('app.timezone')); // کل شِدولر بر اساس این TZ اجرا میشه
    }
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('reports:send-due')->everyMinute()->withoutOverlapping();
        $schedule->command('tasks:send-due')->everyMinute()->withoutOverlapping();
        $schedule->command('app:send-goal-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler-reminders.log'));

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
