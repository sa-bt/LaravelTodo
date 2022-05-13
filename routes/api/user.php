<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('tasks', 'TaskController');

    Route::post('logout', 'AuthController@logout')->name('logout');

    Route::get('/user','AuthController@user' )->name('user.info');


});

Route::post('register', 'AuthController@register')->name('register');
Route::post('login', 'AuthController@login')->name('login');
