<?php

namespace App\Jobs;

use App\Notifications\DailyReportNotification;
use App\Services\ProgressMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendDailyReportNotification implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user || !$user->daily_report) return;

        $svc = app(ProgressMessageService::class);
        ['total' => $total, 'done' => $done, 'percent' => $percent, 'remaining' => $remaining]
            = $svc->getUserProgressForDate($user->id, now());


        // حتی اگر remaining=0 هم باشه، گزارش روزانه می‌تونه پیام افتخاری بده
        $bodyData = $svc->buildMessage($percent, $remaining, 'report');
        $duration = $bodyData['duration'] ?? 5000;

        $user->notify(new DailyReportNotification(
            title: '📊 گزارش پیشرفت تسک‌ها',
            body:  $bodyData['text'],
            url:   url('/day'),
            percent: $percent,
            remaining: $remaining,
            meta: ['duration' => $duration]
        ));
    }
}
