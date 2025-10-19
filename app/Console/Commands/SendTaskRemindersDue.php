<?php

namespace App\Console\Commands;

use App\Jobs\SendTaskReminderNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendTaskRemindersDue extends Command
{
    protected $signature = 'tasks:send-due';
    protected $description = 'Send task reminder push for users whose task_reminder_time matches now';

    public function handle(): int
    {
        $now = Carbon::now();
        $current = $now->format('H:i');
        $today   = $now->toDateString();

        User::where('task_reminder', true)
            ->whereNotNull('task_reminder_time')
            ->where('task_reminder_time', $current)
            ->chunkById(200, function ($users) use ($today) {
                foreach ($users as $user) {
                    $key = "task_reminder_sent:{$user->id}:{$today}";
                    if (Cache::has($key)) continue;

                    SendTaskReminderNotification::dispatch($user->id);
                    Cache::put($key, true, now()->endOfDay());
                }
            });

        $this->info('tasks:send-due checked');
        return self::SUCCESS;
    }
}
