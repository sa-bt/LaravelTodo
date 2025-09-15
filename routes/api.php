<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\WeekController;
use App\Http\Controllers\Api\GoalWeekController;
use App\Http\Controllers\Api\TaskController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('goals', GoalController::class);
    Route::apiResource('tasks', TaskController::class);
    Route::post('goal-tasks', [GoalController::class, 'tasks']);
    Route::get('/weeks/{id}/goals', [WeekController::class, 'goals']);
    Route::get('/activities/{year}', [ActivityController::class, 'index']);
    Route::post('weeks/{week}/update-goals', [WeekController::class, 'updateGoalStatuses']);
    Route::apiResource('week-goals', GoalWeekController::class);
});
