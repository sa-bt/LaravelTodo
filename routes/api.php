<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\UserSettingController;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
    Route::get('/reports/activity', [ActivityController::class, 'activity']);
    Route::get('/reports/backlog', [ActivityController::class, 'backlog']);
    Route::get('/reports/goal-ranking', [ActivityController::class, 'goalRanking']);
    Route::get('/reports/goal-activity', [ActivityController::class, 'goalActivity']);
    Route::get('/reports/year-review', [ActivityController::class, 'yearReview']);
    Route::get('/user-setting', [UserSettingController::class, 'getSetting']);
    Route::post('/user-setting', [UserSettingController::class, 'saveSetting']);

    Route::get('/notifications',               [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',  [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{id}/read',    [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',     [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}',       [NotificationController::class, 'destroy']);
    Route::delete('/notifications',            [NotificationController::class, 'destroyAll']);

    Route::post('/save-subscription', [PushSubscriptionController::class, 'store']);
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
    Route::apiResource('/admin/contents', ContentController::class)
        ->parameters(['contents' => 'content']);
});

Route::middleware(['throttle:3,1', 'block.spam']) // هم ریت لیمیت و هم هانی‌پات
->post('/contact', [ContactController::class, 'store']);

Route::get('/test', function () {

    $user = App\Models\User::find(1);

    $user->notify(new App\Notifications\TaskNotification());
     return 'Notification sent!';
});


use App\Jobs\SendDailyReportNotificationJob;
use App\Jobs\SendTaskReminderNotification;
use Illuminate\Support\Facades\Cache;

Route::get('/test-notifications/user-1', function () {
    abort_unless(app()->environment(['local', 'testing']), 403);

    $user = User::find(2);

    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User with id=1 not found.',
        ], 404);
    }

    $today = now()->toDateString();

    Cache::forget("daily-report:user:{$user->id}:date:{$today}");
    Cache::forget("task-reminder:sent:user:{$user->id}:date:{$today}");
    Cache::forget("task-reminder:dispatch-lock:user:{$user->id}:date:{$today}");

    $beforeNotificationsCount = $user->notifications()->count();
    $beforeUnreadCount = $user->unreadNotifications()->count();

    $results = [
        'daily_report' => $thisResult = [
            'success' => false,
            'error' => null,
        ],
        'task_reminder' => [
            'success' => false,
            'error' => null,
        ],
    ];

    try {
        SendDailyReportNotificationJob::dispatchSync($user->id);
        $results['daily_report']['success'] = true;
    } catch (Throwable $exception) {
        report($exception);
        $results['daily_report']['error'] = $exception->getMessage();
    }

    try {
        SendTaskReminderNotification::dispatchSync($user->id);
        $results['task_reminder']['success'] = true;
    } catch (Throwable $exception) {
        report($exception);
        $results['task_reminder']['error'] = $exception->getMessage();
    }

    $user->refresh();

    $afterNotificationsCount = $user->notifications()->count();
    $afterUnreadCount = $user->unreadNotifications()->count();

    return response()->json([
        'success' => true,
        'message' => 'Notification jobs tested for user id=1.',
        'data' => [
            'user_id' => $user->id,
            'date' => $today,
            'settings' => [
                'daily_report' => (bool)$user->daily_report,
                'task_reminder' => (bool)$user->task_reminder,
                'task_reminder_time' => $user->task_reminder_time,
                'report_time' => $user->report_time,
            ],
            'notifications' => [
                'before_total' => $beforeNotificationsCount,
                'after_total' => $afterNotificationsCount,
                'created_count' => $afterNotificationsCount - $beforeNotificationsCount,
                'before_unread' => $beforeUnreadCount,
                'after_unread' => $afterUnreadCount,
                'new_unread_count' => $afterUnreadCount - $beforeUnreadCount,
            ],
            'jobs' => $results,
        ],
    ]);
});
