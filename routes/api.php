<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\WeekController;
use App\Http\Controllers\Api\GoalWeekController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

Route::post('/register', [AuthController::class,'register']);

Route::post('/login', [AuthController::class,'login']);

Route::apiResource('goals', GoalController::class);
Route::apiResource('weeks', WeekController::class);
Route::get('/weeks/{id}/goals', [WeekController::class, 'goals']);
Route::post('weeks/{week}/update-goals', [WeekController::class, 'updateGoalStatuses']);
Route::apiResource('goal-weeks', GoalWeekController::class);

