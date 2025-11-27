<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

        Schedule::command('reports:send-due')->everyMinute()->withoutOverlapping();
        Schedule::command('tasks:send-due')->everyMinute()->withoutOverlapping();
        Schedule::command('app:send-goal-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler-reminders.log'));

    
