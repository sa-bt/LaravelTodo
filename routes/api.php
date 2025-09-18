<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\UserSettingController;
use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('goals', GoalController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::post('goal-tasks', [GoalController::class, 'tasks']);
    Route::get('/activities/{year}', [ActivityController::class, 'index']);
    Route::get('/user-setting', [UserSettingController::class, 'getSetting']);
    Route::post('/user-setting', [UserSettingController::class, 'saveSetting']);


    Route::post('/save-subscription', function (Request $request) {
        $user = Auth::user();
        \Log::info('Push subscription request', $request->all());
        \Log::info('User', $user->toArray());

        $user->updatePushSubscription(
            $request->endpoint,
            $request->keys['p256dh'],
            $request->keys['auth']
        );
        return response()->json(['success' => true]);
    });
});
// Route::post('/save-subscription', function (Request $request) {
//     return response()->json(['success' => true]);
// });
Route::get('/test', function () {
    $user = App\Models\User::first();
    $user->notify(new App\Notifications\TaskNotification());
    return 'Notification sent!';
});
