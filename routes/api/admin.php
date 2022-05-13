<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('users', 'UserController');
    Route::get('tasks/deleted', 'TaskController@deleredTasks')->name('tasks.all.deleted');
    Route::apiResource('tasks', 'TaskController');
});
