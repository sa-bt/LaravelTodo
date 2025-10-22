<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CaptchaController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\PushSubscriptionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\UserSettingController;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:15,1');

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:15,1');
Route::middleware('auth:sanctum')->group(function () {
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
    ->middleware('throttle:30,1'); // حداکثر ۳۰ بار در دقیقه

// بررسی پاسخ
Route::post('/captcha/verify', [CaptchaController::class, 'verify'])
    ->middleware('throttle:60,1');
Route::get('/test', function () {
    \Log::info('این یک پیام تست لاگ است!');

        $this->info('پیام لاگ نوشته شد ✅');
    // $user = App\Models\User::first();
    // $user->notify(new App\Notifications\TaskNotification());
    // return 'Notification sent!';
});
