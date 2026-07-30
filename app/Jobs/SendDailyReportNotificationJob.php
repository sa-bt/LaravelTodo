<?php

namespace App\Jobs;

use App\Models\Goal;
use App\Models\User;
use App\Notifications\ReportNotification;
use App\Services\ActivityReportService;
use App\Services\ReportMessageService;
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

    public function handle(ReportMessageService $messages, ActivityReportService $reports): void
    {
        Log::info('Daily report notification job started.', [
            'user_id' => $this->userId,
        ]);

        $user = User::find($this->userId);

        if (!$this->canSendDailyReport($user)) {
            return;
        }

        $today = now()->toDateString();
        $dedupKey = self::dedupKey($user->id, $today);

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

        // Yesterday only takes part when it had tasks. Comparing against a day
        // nothing was planned for would read as a collapse that never happened.
        $yesterday = $this->getUserProgress($user->id, now()->subDay()->toDateString());
        $yesterdayPercent = $yesterday['total'] > 0 ? $yesterday['percent'] : null;

        $backlog = $reports->backlog($user->goals()->pluck('id'), 1)['count'];

        $body = $messages->dailyBody($stats, $yesterdayPercent, $backlog);

        try {
            $user->notify(new ReportNotification(
                title: '📊 گزارش روزانه',
                body: $body,
                type: 'daily_report',
                tag: "daily-report-{$today}",
                url: '/app/day',
                percent: $stats['percent'],
                remaining: $stats['remaining'],
                meta: [
                    'type' => 'daily_report',
                    'date' => $today,
                    'total' => $stats['total'],
                    'done' => $stats['done'],
                    'remaining' => $stats['remaining'],
                    'percent' => $stats['percent'],
                    'yesterday_percent' => $yesterdayPercent,
                    'backlog' => $backlog,
                ],
            ));

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

    /**
     * Key that stops a second send on the same day.
     *
     * Public because changing the report settings has to be able to clear it,
     * otherwise a user who turns the report on after its hour has passed waits
     * until tomorrow without knowing why.
     */
    public static function dedupKey(int $userId, string $date): string
    {
        return "daily-report:user:{$userId}:date:{$date}";
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

    /**
     * Today's tasks of the user.
     *
     * Tasks of a goal that has children are left out, the same rule the
     * progress notification uses.
     */
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

        // The counts above are what the message says out loud. The two below
        // decide the percentage, so a high priority goal weighs more there.
        $totalWeight = 0;
        $doneWeight = 0;

        foreach ($user->goals as $goal) {
            if ($goal->children_count > 0) {
                continue;
            }

            $weight = Goal::weightOf($goal->priority);

            foreach ($goal->tasks as $task) {
                $total++;
                $totalWeight += $weight;

                if ($task->is_done) {
                    $done++;
                    $doneWeight += $weight;
                }
            }
        }

        $remaining = $total - $done;
        $percent = $totalWeight > 0 ? (int) round(($doneWeight / $totalWeight) * 100) : 0;

        return [
            'total' => $total,
            'done' => $done,
            'remaining' => $remaining,
            'percent' => $percent,
        ];
    }
}
