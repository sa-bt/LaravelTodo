<?php

use App\Jobs\GoalReminderJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * این سرور فقط برنامه سمت کاربر را سرویس نمی‌دهد، پس ریشه یک پاسخ سلامت ساده
 * است. قبلاً همین مسیر یک کد آزمایشی بود: کاربر شماره یک و تسک شماره پنجاه را
 * می‌خواند و همان‌جا کار ارسال یادآوری را اجرا می‌کرد. یعنی هر بازدیدکننده
 * ناشناسی می‌توانست برای یک کاربر واقعی اعلان بفرستد.
 */
Route::get('/', fn () => response()->json([
    'status' => true,
    'app' => config('app.name'),
]));

/*
 * همان کد آزمایشی، این بار فقط روی محیط محلی و با شناسه‌های صریح.
 */
if (app()->environment('local')) {
    Route::get('/dev/goal-reminder/{task}/{user}', function (int $task, int $user) {
        GoalReminderJob::dispatchSync(
            Task::findOrFail($task)->id,
            User::findOrFail($user)->id
        );

        return response()->json(['status' => true, 'message' => 'Reminder dispatched.']);
    });
}
