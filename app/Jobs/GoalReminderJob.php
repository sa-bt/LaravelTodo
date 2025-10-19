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

class GoalReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    #[WithoutRelations] public int $taskId;
    #[WithoutRelations] public int $userId;

    // ✅ گزینه‌های داینامیک (اختیاری)
    public ?string $title = null;
    public ?string $body  = null;
    public ?string $url   = null;
    public ?string $icon  = null;
    public ?string $tag   = null;
    public array   $meta  = [];
    public array   $channels = [];      // مثلا ['database', 'webpush'] — اگر خالی بود، پیش‌فرض نوتیف اعمال می‌شود
    public ?string $dedupKey = null;    // جلوگیری از ارسال تکراری
    public int $dedupTtl = 70;          // ثانیه

    public $tries = 3;
    public $backoff = [10, 30, 60];

    /**
     * @param int $taskId
     * @param int $userId
     * @param array $options  کلیدهای مجاز: title, body, url, icon, tag, meta(array), channels(array), dedupKey(string), dedupTtl(int)
     */
    public function __construct(int $taskId, int $userId, array $options = [])
    {
        $this->taskId = $taskId;
        $this->userId = $userId;

        // overrideهای اختیاری
        $this->title    = $options['title']    ?? null;
        $this->body     = $options['body']     ?? null;
        $this->url      = $options['url']      ?? null;
        $this->icon     = $options['icon']     ?? null;
        $this->tag      = $options['tag']      ?? null;
        $this->meta     = $options['meta']     ?? [];
        $this->channels = $options['channels'] ?? [];
        $this->dedupKey = $options['dedupKey'] ?? null;
        if (isset($options['dedupTtl']) && is_int($options['dedupTtl'])) {
            $this->dedupTtl = $options['dedupTtl'];
        }
    }

    public function handle(): void
    {
        $task = Task::with(['goal' => fn($q) => $q->withCount('children')])->find($this->taskId);
        $user = User::find($this->userId);

        if (!$task || !$user || !$task->goal) {
            Log::warning("Reminder skip: missing models (taskId={$this->taskId}, userId={$this->userId})");
            return;
        }

        // ✅ Double-check: اگر در فاصله تا اجرا انجام شده باشد
        if ($task->is_done) {
            Log::info("Reminder skip: Task {$task->id} already done.");
            return;
        }

        // ✅ نگهبان: هدف باید لیف باشد
        if ($task->goal->children_count > 0) {
            Log::info("Reminder skip: Goal {$task->goal->id} is not leaf.");
            return;
        }

        // ✅ حداقل یک اشتراک پوش
        if (!$user->pushSubscriptions()->exists()) {
            Log::info("Reminder skip: User {$user->id} has no push subscription.");
            return;
        }

        // ✅ جلوگیری از ارسال تکراری (اختیاری)
        $dedupKey = $this->dedupKey ?: "reminder:task:{$task->id}:{$task->day}";
        if (Cache::has($dedupKey)) {
            Log::info("Reminder dedup skipped for key: {$dedupKey}");
            return;
        }

        // ✅ ساخت payload داینامیک
        $goalTitle = $task->goal->title;
        $title = $this->title ?? "یادآور تسک";
        $body  = $this->body  ?? "«{$task->title}» از هدف «{$goalTitle}» هنوز انجام نشده.";
        $url   = $this->url   ?? '/tasks/'.$task->id;
        $icon  = $this->icon  ?? '/icons/notification.png';
        $tag   = $this->tag   ?? "task-reminder-{$task->id}-{$task->day}";
        $meta  = array_merge([
            'type'    => 'task_reminder',
            'goal_id' => $task->goal_id,
            'task_id' => $task->id,
            'day'     => $task->day,
        ], $this->meta);

        try {
            // ✅ ارسال نوتیف داینامیک (کانال‌ها قابل تنظیم)
            $notification = new GenericWebPush(
                title: $title,
                body:  $body,
                url:   $url,
                meta:  $meta,
                icon:  $icon,
                tag:   $tag,
                channels: $this->channels // اگر خالی باشد، GenericWebPush پیش‌فرض خودش را اعمال می‌کند
            );

            $user->notify($notification);

            // ✅ ثبت dedup
            Cache::put($dedupKey, 1, now()->addSeconds($this->dedupTtl));

            Log::info("Reminder sent for Task {$task->id} to User {$user->id}");
        } catch (\Throwable $e) {
            Log::error("Reminder failed (task {$task->id}): " . $e->getMessage());
            throw $e; // تا retry شود
        }
    }
}
