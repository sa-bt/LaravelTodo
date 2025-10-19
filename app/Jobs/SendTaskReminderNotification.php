<?php

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\ProgressMessageService;

class SendTaskReminderNotification implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $userId) {}


    public function handle(): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user || !$user->task_reminder) return;

        $svc = app(ProgressMessageService::class);
        ['total'=>$total, 'done'=>$done, 'percent'=>$percent, 'remaining'=>$remaining] = $svc->getUserProgress($user->id);

        if ($total === 0 || $remaining === 0) return; // چیزی برای یادآوری نیست

        $body = $svc->buildMessage($percent, $remaining, 'reminder');

        $user->notify(new \App\Notifications\GenericWebPush(
            title: 'یادآوری تسک‌ها',
            body:  $body,
            url:   url('/tasks?filter=pending')
        ));
    }
}
