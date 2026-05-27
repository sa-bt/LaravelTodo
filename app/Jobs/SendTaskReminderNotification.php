<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\GenericWebPush;
use App\Services\ProgressMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendTaskReminderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    #[WithoutRelations]
    public int $userId;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function handle(ProgressMessageService $progressService): void
    {
        Log::info('Task reminder notification job started.', [
            'user_id' => $this->userId,
        ]);

        $user = User::find($this->userId);

        if (! $this->canSendReminder($user)) {
            return;
        }

        $today = now()->toDateString();
        $sentKey = $this->makeSentKey($user->id, $today);

        if (Cache::has($sentKey)) {
            Log::info('Task reminder notification skipped because it was already sent today.', [
                'user_id' => $user->id,
                'date' => $today,
            ]);

            return;
        }

        $progress = $progressService->getUserProgressForDate($user->id, $today);

        if (! $this->hasPendingTasks($progress)) {
            Log::info('Task reminder notification skipped because user has no pending tasks.', [
                'user_id' => $user->id,
                'date' => $today,
                'total' => $progress['total'] ?? null,
                'remaining' => $progress['remaining'] ?? null,
            ]);

            return;
        }

        try {
            $user->notify($this->makeNotification($progressService, $progress, $today));

            Cache::put($sentKey, true, now()->endOfDay());

            Log::info('Task reminder notification sent successfully.', [
                'user_id' => $user->id,
                'date' => $today,
                'total' => $progress['total'],
                'done' => $progress['done'],
                'remaining' => $progress['remaining'],
                'percent' => $progress['percent'],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Task reminder notification failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function canSendReminder(?User $user): bool
    {
        if (! $user) {
            Log::warning('Task reminder notification skipped because user was not found.', [
                'user_id' => $this->userId,
            ]);

            return false;
        }

        if (! $user->task_reminder) {
            Log::info('Task reminder notification skipped because reminder is disabled.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        return true;
    }

    private function hasPendingTasks(array $progress): bool
    {
        $total = (int) ($progress['total'] ?? 0);
        $remaining = (int) ($progress['remaining'] ?? 0);

        return $total > 0 && $remaining > 0;
    }

    private function makeNotification(
        ProgressMessageService $progressService,
        array $progress,
        string $date
    ): GenericWebPush {
        $message = $progressService->buildMessage(
            percent: (int) $progress['percent'],
            remaining: (int) $progress['remaining'],
            context: 'reminder',
        );

        return new GenericWebPush(
            title: 'یادآوری تسک‌ها',
            body: $message['text'],
            url: '/app/day',
            meta: [
                'type' => 'task_reminder_summary',
                'date' => $date,
                'total' => $progress['total'],
                'done' => $progress['done'],
                'remaining' => $progress['remaining'],
                'percent' => $progress['percent'],
                'duration' => $message['duration'],
            ],
            icon: '/pwa-192x192.png',
            tag: "task-reminder-summary-{$date}",
            persisted: true,
        );
    }

    private function makeSentKey(int $userId, string $date): string
    {
        return "task-reminder:sent:user:{$userId}:date:{$date}";
    }
}
