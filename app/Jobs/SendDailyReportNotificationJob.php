<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendDailyReportNotificationJob implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        Log::info("--- DailyReport Job: userId={$this->userId} ---");

        $user = User::find($this->userId);

        // ─── Validation ───
        if (!$user) {
            Log::warning("DailyReport skip: User not found", ['userId' => $this->userId]);
            return;
        }

        if (!$user->daily_report) {
            Log::info("DailyReport skip: Disabled", ['userId' => $this->userId]);
            return;
        }

        if (!$user->pushSubscriptions()->exists()) {
            Log::info("DailyReport skip: No subscription", ['userId' => $this->userId]);
            return;
        }

        // ─── Dedup ───
        $today = now()->toDateString();
        $dedupKey = "daily-report:{$this->userId}:{$today}";

        if (Cache::has($dedupKey)) {
            Log::info("DailyReport skip: Already sent", ['userId' => $this->userId]);
            return;
        }

        // ─── Progress ───
        $stats = $this->getUserProgress($user->id, $today);

        $total = $stats['total'];
        $done = $stats['done'];
        $remaining = $stats['remaining'];
        $percent = $stats['percent'];

        if ($total === 0) {
            Log::info("DailyReport skip: No tasks", ['userId' => $this->userId]);
            return;
        }

        // ─── Title & Body ───
        $title = '📊 گزارش روزانه';

        if ($remaining === 0) {
            $body = "🎉 عالی! همه {$total} تسک رو انجام دادی!";
        } elseif ($done === 0) {
            $body = "هنوز شروع نکردی! {$total} تسک منتظرن 🎯";
        } else {
            $body = "{$done} از {$total} تسک انجام شد";
            if ($percent > 0) {
                $body .= " ({$percent}%)";
            }
        }

        // ─── Send ───
        try {
            $user->notify(new DailyReportNotification(
                title: $title,
                body: $body,
                url: url('/day'),
                percent: $percent,
                remaining: $remaining,
            ));

            Cache::put($dedupKey, 1, now()->addHours(25));

            Log::info("✅ DailyReport sent", [
                'userId' => $this->userId,
                'done' => $done,
                'total' => $total,
                'percent' => $percent,
            ]);

        } catch (\Throwable $e) {
            Log::error("❌ DailyReport failed", [
                'userId' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * محاسبه پیشرفت کاربر در یک روز
     */
    private function getUserProgress(int $userId, string $date): array
    {
        $user = User::with(['goals.tasks' => function ($q) use ($date) {
            $q->whereDate('day', $date);
        }])->find($userId);

        $total = 0;
        $done = 0;

        foreach ($user->goals as $goal) {
            // فقط اهداف leaf (بدون فرزند)
            if ($goal->children()->exists()) {
                continue;
            }

            foreach ($goal->tasks as $task) {
                $total++;
                if ($task->is_done) {
                    $done++;
                }
            }
        }

        $remaining = $total - $done;
        $percent = $total > 0 ? round(($done / $total) * 100) : 0;

        return [
            'total' => $total,
            'done' => $done,
            'remaining' => $remaining,
            'percent' => $percent,
        ];
    }
}
