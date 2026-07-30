<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

        Schedule::command('reports:send-due')->everyMinute()->withoutOverlapping();
        Schedule::command('reports:send-weekly-due')->everyMinute()->withoutOverlapping();
        Schedule::command('tasks:send-due')->everyMinute()->withoutOverlapping();

        /*
         * هشدار افت، شنبه ساعت ده صبح.
         *
         * برخلاف گزارش هفتگی، ساعت آن به کاربر واگذار نشده. این پیام وقتی
         * ارزش دارد که هفته تازه شروع شده باشد و هنوز بشود کاری کرد. عدد شش
         * در تقویم زمان‌بند یعنی شنبه.
         */
        Schedule::command('reports:send-drop-alerts-due')
            ->weeklyOn(6, '10:00')
            ->withoutOverlapping();
        /*
         * جمع‌بندی بازدید، هر شب.
         *
         * بعد از نیمه‌شب اجرا می‌شود تا روز کامل شده باشد. خودش چند روز عقب را
         * هم بازسازی می‌کند، پس یک شب اجرانشدن، سوراخ همیشگی در تاریخچه
         * نمی‌گذارد. هرس داده خام بعد از آن می‌آید تا هیچ روزی پیش از
         * جمع‌بندی‌شدن حذف نشود.
         */
        Schedule::command('visits:aggregate')->dailyAt('00:20')->withoutOverlapping();
        Schedule::command('visits:prune')->dailyAt('03:30')->withoutOverlapping();

        Schedule::command('app:send-goal-reminders')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler-reminders.log'));

    
