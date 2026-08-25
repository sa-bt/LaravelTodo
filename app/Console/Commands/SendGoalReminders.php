<?php

namespace App\Console\Commands;

use App\Jobs\GoalReminderJob;
use App\Models\Goal;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendGoalReminders extends Command
{
    protected $signature = 'app:send-goal-reminders';
    protected $description = 'Dispatch reminder jobs for incomplete today tasks of leaf goals at their reminder_time.';

    public function handle(): int
    {
        $now = now()->setTimezone(config('app.timezone')); // ⬅️ این خطو اضافه کن
        $currentTime = $now->format('H:i');              // HH:mm
        $today       = $now->toDateString();
        $ttlSeconds  = 70;                               // کمی بیشتر از یک دقیقه برای پوشش جیتِر
        $onePerGoal  = (bool) (config('notifications.reminders.one_per_goal', true)); // true: فقط یکی برای هر goal

        /*
         * There used to be a "Goal reminder scan at ..." line here. It ran
         * before the query, so it fired every single minute regardless of
         * whether anything was due, and recorded nothing the completion line
         * below does not already say when work actually happens.
         */

        $base = Goal::query()
            ->where('send_task_reminder', true)
            ->whereDoesntHave('children')                   // فقط لیف
            ->whereTime('reminder_time', $currentTime)      // دقیقه‌ی فعلی
            // یوزری که subscription دارد (prefilter برای کاهش کار اضافه)
            ->whereHas('user.pushSubscriptions')
            ->with([
                'user',
                'tasks' => function ($q) use ($today) {
                    $q->whereDate('day', $today)
                        ->where('is_done', false);
                },
            ])
            ->orderBy('id'); // برای chunkById

        $dispatched = 0;

        $base->chunkById(200, function ($goals) use ($today, $currentTime, $ttlSeconds, $onePerGoal, &$dispatched) {
            foreach ($goals as $goal) {
                // اگر تسک ناتمام امروز ندارد، ادامه
                if ($goal->tasks->isEmpty() || !$goal->user) {
                    continue;
                }

                // dedup به‌ازای goal و دقیقه — اتمیک (return true فقط اگر برای اولین بار ست شود)
                $cacheKey = "reminder:goal:{$goal->id}:{$today}:{$currentTime}";
                if (!Cache::add($cacheKey, 1, now()->addSeconds($ttlSeconds))) {
                    // قبلاً در همین دقیقه دیسپچ شده
                    continue;
                }

                // ارسال یک جاب یا برای همه‌ی تسک‌های امروز
                if ($onePerGoal) {
                    $task = $goal->tasks->first();
                    dispatch(new GoalReminderJob($task->id, $goal->user->id));
                    $dispatched++;
                } else {
                    foreach ($goal->tasks as $task) {
                        dispatch(new GoalReminderJob($task->id, $goal->user->id));
                        $dispatched++;
                    }
                }
            }
        });

        $this->info("Dispatched {$dispatched} reminder jobs at {$currentTime}");

        // Same reason as the removed scan line: every-minute command, so only a
        // run that dispatched something is worth a log entry.
        if ($dispatched > 0) {
            Log::info("Goal reminder dispatch completed", ['dispatched' => $dispatched, 'at' => $currentTime]);
        }

        return Command::SUCCESS;
    }
}
