<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\DailyReportNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendDailyReportNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    #[WithoutRelations]
    public int $userId;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        Log::info('Daily report notification job started.', [
            'user_id' => $this->userId,
        ]);

        $user = User::find($this->userId);

        if (!$this->canSendDailyReport($user)) {
            return;
        }

        $today = now()->toDateString();
        $dedupKey = $this->makeDedupKey($user, $today);

        if (Cache::has($dedupKey)) {
            Log::info('Daily report notification skipped because it was already sent.', [
                'user_id' => $user->id,
                'date' => $today,
            ]);

            return;
        }

        $stats = $this->getUserProgress($user->id, $today);

        if ($stats['total'] === 0) {
            Log::info('Daily report notification skipped because user has no tasks today.', [
                'user_id' => $user->id,
                'date' => $today,
            ]);

            return;
        }

        try {
            $user->notify($this->makeNotification($stats, $today));

            Cache::put($dedupKey, true, now()->addHours(25));

            Log::info('Daily report notification sent successfully.', [
                'user_id' => $user->id,
                'date' => $today,
                'done' => $stats['done'],
                'total' => $stats['total'],
                'remaining' => $stats['remaining'],
                'percent' => $stats['percent'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Daily report notification failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function canSendDailyReport(?User $user): bool
    {
        if (!$user) {
            Log::warning('Daily report notification skipped because user was not found.', [
                'user_id' => $this->userId,
            ]);

            return false;
        }

        if (!$user->daily_report) {
            Log::info('Daily report notification skipped because daily report is disabled.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        return true;
    }

    private function makeDedupKey(User $user, string $date): string
    {
        return "daily-report:user:{$user->id}:date:{$date}";
    }

    private function makeNotification(array $stats, string $date): DailyReportNotification
    {
        return new DailyReportNotification(
            title: '📊 گزارش روزانه',
            body: $this->makeBody($stats),
            url: '/app/day',
            percent: $stats['percent'],
            remaining: $stats['remaining'],
            meta: [
                'type' => 'daily_report',
                'date' => $date,
                'total' => $stats['total'],
                'done' => $stats['done'],
                'remaining' => $stats['remaining'],
                'percent' => $stats['percent'],
            ],
            tag: "daily-report-{$date}",
            persisted: true,
        );
    }

    private function makeBody(array $stats): string
    {
        $total = $stats['total'];
        $done = $stats['done'];
        $remaining = $stats['remaining'];
        $percent = $stats['percent'];

        if ($remaining === 0) {
            return "🎉 عالی! همه {$total} تسک رو انجام دادی!";
        }

        if ($done === 0) {
            return "هنوز شروع نکردی! {$total} تسک منتظرن 🎯";
        }

        return "{$done} از {$total} تسک انجام شد ({$percent}%)";
    }

    private function getUserProgress(int $userId, string $date): array
    {
        $user = User::query()
            ->with([
                'goals' => fn ($query) => $query->withCount('children'),
                'goals.tasks' => fn ($query) => $query->whereDate('day', $date),
            ])
            ->findOrFail($userId);

        $total = 0;
        $done = 0;

        foreach ($user->goals as $goal) {
            if ($goal->children_count > 0) {
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
        $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return [
            'total' => $total,
            'done' => $done,
            'remaining' => $remaining,
            'percent' => $percent,
        ];
    }
}
