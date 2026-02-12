<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationMessageBuilder;

class GoalReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    #[WithoutRelations] public int $taskId;
    #[WithoutRelations] public int $userId;

    public ?string $title = null;
    public ?string $body = null;
    public ?string $url = null;
    public ?string $icon = null;
    public ?string $tag = null;
    public array $meta = [];
    public ?string $dedupKey = null;
    public int $dedupTtl = 70;

    public $tries = 3;
    public $backoff = [10, 30, 60];

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
        Log::info("--- GoalReminderJob started: taskId={$this->taskId} ---");

        $task = Task::with(['goal' => fn($q) => $q->withCount('children')])->find($this->taskId);
        $user = User::find($this->userId);

        if (!$task || !$user || !$task->goal) {
            Log::warning("Reminder skip: missing models", ['taskId' => $this->taskId, 'userId' => $this->userId]);
            return;
        }

        if ($task->is_done) {
            Log::info("Reminder skip: Task {$task->id} already done.");
            return;
        }

        if ($task->goal->children_count > 0) {
            Log::info("Reminder skip: Goal {$task->goal->id} is not leaf.");
            return;
        }

        if (!$user->pushSubscriptions()->exists()) {
            Log::info("Reminder skip: User {$user->id} has no push subscription.");
            return;
        }

        $dedupKey = $this->dedupKey ?: "reminder:task:{$task->id}:{$task->day}";
        if (Cache::has($dedupKey)) {
            Log::info("Reminder dedup skipped for key: {$dedupKey}");
            return;
        }

        // ─── Build Content ───
        $message = NotificationMessageBuilder::build($task);

        // ✅ title: فقط نام هدف
        $title = $this->title ?? $task->goal->title;

        // ✅ body: پیام بدون نام هدف
        $body = $this->body ?? $message;

        $url = $this->url ?? '/day';
        $icon = $this->icon ?? '/pwa-192x192.png';
        $tag = $this->tag ?? "reminder-{$task->goal_id}-{$task->day}";

        $meta = array_merge([
            'type' => 'task_reminder',
            'goal_id' => $task->goal_id,
            'task_id' => $task->id,
            'day' => $task->day,
        ], $this->meta);

        try {
            $notification = new GenericWebPush(
                title: $title,
                body: $body,
                url: $url,
                meta: $meta,
                icon: $icon,
                tag: $tag,
            );

            $user->notify($notification);
            Cache::put($dedupKey, 1, now()->addSeconds($this->dedupTtl));

            Log::info("✅ Reminder sent", ['taskId' => $task->id, 'goalTitle' => $task->goal->title, 'userId' => $user->id]);
        } catch (\Throwable $e) {
            Log::error("❌ Reminder failed", ['taskId' => $task->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
