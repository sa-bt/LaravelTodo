<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use Illuminate\Support\Facades\Log;
// ایمپورت مدل نوتیفیکیشن اختصاصی شما
use App\Notifications\GenericWebPush;


class SendProgressNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;
    protected $title;
    protected $body;
    protected $progress;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\User $user کاربری که باید اعلان را دریافت کند
     * @param string $title عنوان اعلان
     * @param string $body متن اعلان
     * @param int $progress درصد پیشرفت محاسبه شده
     * @return void
     */
    public function __construct(User $user, string $title, string $body, int $progress)
    {
        $this->user = $user;
        $this->title = $title;
        $this->body = $body;
        $this->progress = $progress;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 1. بررسی حداقل شرایط برای ارسال
        // اگر کاربر اشتراکی ندارد، متد notify() خود به خود ارسال نمی‌کند.
        if ($this->user->pushSubscriptions->isEmpty()) {
            Log::warning("User {$this->user->id} has no valid push subscriptions for per-task notification.");
            return;
        }

        // 2. ساخت پیام اعلان (بر اساس منطق گزارش دهی در TaskController)
        Log::info("Attempting to send Push Notification using User::notify() for User {$this->user->id}");

        try {
            // منطق شما: استفاده از متد notify و کلاس GenericWebPush
            $this->user->notify(new GenericWebPush(
                title: $this->title,
                body:  $this->body,
                // URL را می‌توانیم ثابت بگذاریم یا از طریق TaskController ارسال کنیم.
                // در اینجا فرض می‌کنیم که به صفحه تسک‌های امروز هدایت می‌کند.
                url:   url('/tasks')
            ));

            Log::info("Push Notification Sent successfully via notify(): {$this->title}");

        } catch (\Exception $e) {
            Log::error("Failed to send push notification to user {$this->user->id} using notify(): " . $e->getMessage());
        }
    }
}
