<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\ProgressMessageService;

class SendDailyReportNotification implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $userId) {}

// app/Jobs/SendDailyReportNotification.php
    public function handle(): void
    {
        $user = \App\Models\User::find($this->userId);
        if (!$user || !$user->daily_report) return;

        $svc = app(ProgressMessageService::class);
        ['total'=>$total, 'done'=>$done, 'percent'=>$percent, 'remaining'=>$remaining] = $svc->getUserProgress($user->id);

        // حتی اگر remaining=0 هم باشه، گزارش روزانه می‌تونه پیام افتخاری بده
        $body = $svc->buildMessage($percent, $remaining, 'report');

        $user->notify(new \App\Notifications\GenericWebPush(
            title: 'گزارش پیشرفت تسک‌ها',
            body:  $body,
            url:   url('/tasks')
        ));
    }}
