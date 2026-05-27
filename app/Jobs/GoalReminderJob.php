<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericWebPush;
use App\Services\NotificationMessageBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GoalReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    #[WithoutRelations]
    public int $taskId;

    #[WithoutRelations]
    public int $userId;

    public ?string $title = null;
    public ?string $body = null;
    public ?string $url = null;
    public ?string $icon = null;
    public ?string $tag = null;
    public array $meta = [];
    public ?string $dedupKey = null;
    public int $dedupTtl = 70;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function __construct(int $taskId, int $userId, array $options = [])
    {
        $this->taskId = $taskId;
        $this->userId = $userId;

        $this->title = $options['title'] ?? null;
        $this->body = $options['body'] ?? null;
        $this->url = $options['url'] ?? null;
        $this->icon = $options['icon'] ?? null;
        $this->tag = $options['tag'] ?? null;
        $this->meta = $options['meta'] ?? [];
        $this->dedupKey = $options['dedupKey'] ?? null;

        if (isset($options['dedupTtl']) && is_int($options['dedupTtl'])) {
            $this->dedupTtl = $options['dedupTtl'];
        }
    }

    public function handle(): void
    {
        Log::info('GoalReminderJob started.', [
            'task_id' => $this->taskId,
            'user_id' => $this->userId,
        ]);

        $task = $this->findTask();
        $user = User::find($this->userId);

        if (!$this->canSendReminder($task, $user)) {
            return;
        }

        $dedupKey = $this->makeDedupKey($task, $user);

        if (Cache::has($dedupKey)) {
            Log::info('Goal reminder skipped because dedup key exists.', [
                'dedup_key' => $dedupKey,
                'task_id' => $task->id,
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            $user->notify($this->makeNotification($task));

            Cache::put($dedupKey, true, now()->addSeconds($this->dedupTtl));

            Log::info('Goal reminder notification sent.', [
                'task_id' => $task->id,
                'goal_id' => $task->goal_id,
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Goal reminder notification failed.', [
                'task_id' => $task->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function findTask(): ?Task
    {
        return Task::query()
            ->with([
                'goal' => fn ($query) => $query->withCount('children'),
            ])
            ->find($this->taskId);
    }

    private function canSendReminder(?Task $task, ?User $user): bool
    {
        if (!$task || !$user || !$task->goal) {
            Log::warning('Goal reminder skipped because required models are missing.', [
                'task_id' => $this->taskId,
                'user_id' => $this->userId,
                'task_exists' => (bool) $task,
                'user_exists' => (bool) $user,
                'goal_exists' => (bool) $task?->goal,
            ]);

            return false;
        }

        if ($task->is_done) {
            Log::info('Goal reminder skipped because task is already done.', [
                'task_id' => $task->id,
                'user_id' => $user->id,
            ]);

            return false;
        }

        if ($task->goal->children_count > 0) {
            Log::info('Goal reminder skipped because goal is not a leaf goal.', [
                'goal_id' => $task->goal->id,
                'task_id' => $task->id,
                'user_id' => $user->id,
            ]);

            return false;
        }

        return true;
    }

    private function makeDedupKey(Task $task, User $user): string
    {
        return $this->dedupKey
            ?: "reminder:user:{$user->id}:task:{$task->id}:day:{$task->day}";
    }

    private function makeNotification(Task $task): GenericWebPush
    {
        $title = $this->title ?? $task->goal->title;
        $body = $this->body ?? NotificationMessageBuilder::build($task);

        $url = $this->url ?? '/app/day';
        $icon = $this->icon ?? '/pwa-192x192.png';
        $tag = $this->tag ?? "task-reminder-{$task->id}-{$task->day}";

        $meta = array_merge([
            'type' => 'task_reminder',
            'goal_id' => $task->goal_id,
            'task_id' => $task->id,
            'day' => $task->day,
        ], $this->meta);

        return new GenericWebPush(
            title: $title,
            body: $body,
            url: $url,
            meta: $meta,
            icon: $icon,
            tag: $tag,
            persisted: true,
        );
    }
}
