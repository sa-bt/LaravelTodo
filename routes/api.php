<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\UserSettingController;
use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericWebPush;
use App\Notifications\TaskNotification;
use App\Services\NotificationMessageBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:15,1');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/goals/parentable', [GoalController::class, 'getParentableGoals'])->name('goals.parentable');
    Route::apiResource('goals', GoalController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::post('goal-tasks', [GoalController::class, 'tasks']);
    Route::get('/activities/{year}', [ActivityController::class, 'index']);
    Route::get('/user-setting', [UserSettingController::class, 'getSetting']);
    Route::post('/user-setting', [UserSettingController::class, 'saveSetting']);

    Route::get('/notifications',               [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read',    [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',     [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}',       [NotificationController::class, 'destroy']);
    Route::delete('/notifications',            [NotificationController::class, 'destroyAll']);

    Route::middleware('auth:sanctum')->post('/save-subscription', [PushSubscriptionController::class, 'store']);
});

Route::post('/captcha/new', [CaptchaController::class, 'new'])
    ->middleware('throttle:10,1'); // حداکثر ۳۰ بار در دقیقه

// بررسی پاسخ
Route::post('/captcha/verify', [CaptchaController::class, 'verify'])
    ->middleware('throttle:60,1');
    // مسیرهای حفاظت شده ادمین
Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
   
    Route::get('/admin/courses/list', [CourseController::class, 'listCourses']); // 👈 مسیر جدید
    Route::get('/admin/course/{slug}', [CourseController::class, 'show']);
});
Route::get('/test', function () {

       $now = now()->setTimezone(config('app.timezone')); // ⬅️ این خطو اضافه کن
        $currentTime = $now->format('H:i');              // HH:mm
        $today       = $now->toDateString();
        $ttlSeconds  = 70;                               // کمی بیشتر از یک دقیقه برای پوشش جیتِر
        $onePerGoal  = (bool) (config('notifications.reminders.one_per_goal', true)); // true: فقط یکی برای هر goal

        Log::info("Goal reminder scan at {$currentTime}", ['today' => $today, 'one_per_goal' => $onePerGoal]);

        $base = Goal::query()
            ->where('send_task_reminder', true)
            ->whereDoesntHave('children')                   // فقط لیف
            ->whereTime('reminder_time', $currentTime)      // دقیقه‌ی فعلی
            // یوزری که subscription دارد (prefilter برای کاهش کار اضافه)
            ->whereHas('user.pushSubscriptions')
            ->with([
                'user',
                'tasks' => function ($q) use ($today) {
                    $q->whereDate('day', $today)
                        ->where('is_done', false);
                },
            ])
            ->orderBy('id'); // برای chunkById

        $dispatched = 0;

        $base->chunkById(200, function ($goals) use ($today, $currentTime, $ttlSeconds, $onePerGoal, &$dispatched) {
            foreach ($goals as $goal) {
                // اگر تسک ناتمام امروز ندارد، ادامه
                if ($goal->tasks->isEmpty() || !$goal->user) {
                    continue;
                }

                // dedup به‌ازای goal و دقیقه — اتمیک (return true فقط اگر برای اولین بار ست شود)
                $cacheKey = "reminder:goal:{$goal->id}:{$today}:{$currentTime}";
                if (!Cache::add($cacheKey, 1, now()->addSeconds($ttlSeconds))) {
                    // قبلاً در همین دقیقه دیسپچ شده
                    continue;
                }

                // ارسال یک جاب یا برای همه‌ی تسک‌های امروز
                if ($onePerGoal) {
                    $task = $goal->tasks->first();
                    dispatch(new GoalReminderJob($task->id, $goal->user->id));
                    $dispatched++;
                } else {
                    foreach ($goal->tasks as $task) {
                        dispatch(new GoalReminderJob($task->id, $goal->user->id));
                        $dispatched++;
                    }
                }
            }
        });

        $this->info("Dispatched {$dispatched} reminder jobs at {$currentTime}");
        Log::info("Goal reminder dispatch completed", ['dispatched' => $dispatched, 'at' => $currentTime]);

        return Command::SUCCESS;
});

