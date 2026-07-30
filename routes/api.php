<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AdminContactController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminNotificationController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PageViewController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\UserSettingController;
use App\Http\Controllers\Api\VisitReportController;
use App\Jobs\SendDailyReportNotificationJob;
use App\Jobs\SendTaskReminderNotification;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Support\Facades\Cache;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:15,1');

/*
 * سقف روی نشانی شبکه، لایهٔ بیرونی است. لایهٔ اصلی داخل کنترلر و روی خودِ حساب
 * بسته می‌شود، چون این دو مسیر با شناسهٔ عددیِ قابل شمارش کار می‌کنند و مهاجم
 * می‌تواند از چند نشانی موازی بیاید.
 */
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:20,1');
Route::post('/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

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
/*
 * ثبت بازدید صفحه.
 *
 * عمداً باز است. سرور هیچ صفحه‌ای نمی‌سازد، پس شمارش از روی درخواست‌های خودش
 * فقط کاربر واردشده را می‌دید و صفحه معرفی و بازدیدکننده ناشناس اصلاً شمرده
 * نمی‌شدند. محدودیت نرخ، هزینه این بازبودن را مهار می‌کند.
 *
 * سقف روی نشانی شبکه بسته می‌شود نه روی بازدیدکننده، و پشت یک شبکه مشترک
 * ده‌ها نفر یک نشانی دارند. عدد سخاوتمندانه انتخاب شده چون افتادن بی‌صدای
 * بازدید واقعی، دقیقاً همان چیزی است که این قابلیت قرار بود درست کند.
 */
Route::post('/track/view', [PageViewController::class, 'store'])
    ->middleware('throttle:300,1');

Route::middleware(['auth:sanctum', 'can:admin'])->group(function () {
    Route::get('/admin/dashboard', AdminDashboardController::class);
    Route::get('/admin/users', [AdminUserController::class, 'index']);
    Route::get('/admin/users/{user}', [AdminUserController::class, 'show']);
    Route::patch('/admin/users/{user}/role', [AdminUserController::class, 'updateRole']);
    Route::post('/admin/users/{user}/approve', [AdminUserController::class, 'approve']);
    Route::post('/admin/users/{user}/reject', [AdminUserController::class, 'reject']);
    Route::get('/admin/contacts', [AdminContactController::class, 'index']);
    Route::patch('/admin/contacts/{contact}', [AdminContactController::class, 'update']);
    Route::get('/admin/notification-campaigns', [AdminNotificationController::class, 'index']);
    Route::post('/admin/notification-campaigns', [AdminNotificationController::class, 'store']);

    Route::get('/admin/analytics/daily', [VisitReportController::class, 'daily']);
    Route::get('/admin/analytics/weekly', [VisitReportController::class, 'weekly']);
    Route::get('/admin/analytics/overview', [VisitReportController::class, 'overview']);

    Route::get('/admin/courses/list', [CourseController::class, 'listCourses']); // 👈 مسیر جدید
    Route::get('/admin/course/{slug}', [CourseController::class, 'show']);
    Route::apiResource('/admin/contents', ContentController::class)
        ->parameters(['contents' => 'content']);
});

Route::middleware(['throttle:3,1', 'block.spam']) // هم ریت لیمیت و هم هانی‌پات
->post('/contact', [ContactController::class, 'store']);

/*
 * مسیرهای آزمایشی، فقط روی محیط محلی و با شناسه صریح کاربر.
 *
 * قبلاً دو مسیر اینجا بود که هر بازدیدکننده ناشناسی می‌توانست با آن‌ها برای یک
 * کاربر واقعی اعلان بفرستد. یکی هیچ محافظتی نداشت و دیگری اسمش کاربر شماره یک
 * بود ولی شناسه دو را می‌خواند. همان قاعده‌ای که برای مسیر ریشه در
 * routes/web.php گرفته شد، اینجا هم اعمال شد: خارج از محیط محلی این مسیرها
 * اصلاً ثبت نمی‌شوند و شناسه کاربر در نشانی صریح است.
 */
if (app()->environment('local')) {
    Route::get('/dev/task-notification/{userId}', function (int $userId) {
        User::findOrFail($userId)->notify(new TaskNotification());

        return response()->json(['status' => true, 'message' => 'Notification sent.']);
    });

    Route::get('/dev/report-notifications/{userId}', function (int $userId) {
        $user = User::findOrFail($userId);

        $today = now()->toDateString();

        Cache::forget("daily-report:user:{$user->id}:date:{$today}");
        Cache::forget("task-reminder:sent:user:{$user->id}:date:{$today}");
        Cache::forget("task-reminder:dispatch-lock:user:{$user->id}:date:{$today}");

        $beforeNotificationsCount = $user->notifications()->count();
        $beforeUnreadCount = $user->unreadNotifications()->count();

        $results = [
            'daily_report' => [
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
            'message' => "Notification jobs tested for user id={$user->id}.",
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
}
