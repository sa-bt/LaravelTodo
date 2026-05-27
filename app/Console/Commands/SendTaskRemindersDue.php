<?php

namespace App\Console\Commands;

use App\Jobs\SendTaskReminderNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendTaskRemindersDue extends Command
{
    protected $signature = 'tasks:send-due';

    protected $description = 'Dispatch task reminder notifications for users whose reminder time matches current time.';

    public function handle(): int
    {
        $now = Carbon::now();
        $currentTime = $now->format('H:i:00');
        $today = $now->toDateString();

        User::query()
            ->where('task_reminder', true)
            ->whereNotNull('task_reminder_time')
            ->whereTime('task_reminder_time', $currentTime)
            ->chunkById(200, function ($users) use ($today) {
                foreach ($users as $user) {
                    $dispatchKey = $this->makeDispatchLockKey($user->id, $today);

                    if (! Cache::add($dispatchKey, true, now()->addMinutes(10))) {
                        continue;
                    }

                    SendTaskReminderNotification::dispatch($user->id);
                }
            });

        $this->info('tasks:send-due checked.');

        return self::SUCCESS;
    }

    private function makeDispatchLockKey(int $userId, string $date): string
    {
        return "task-reminder:dispatch-lock:user:{$userId}:date:{$date}";
    }
}
