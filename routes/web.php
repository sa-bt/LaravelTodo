<?php

use App\Mail\OtpMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
Route::get('/', function () {
    $user = \App\Models\User::find(1);
    $task = Task::findOrFail(50);

    if ($task) {
        \App\Jobs\GoalReminderJob::dispatchSync($task->id, $user->id);
        echo "Notification sent!";
    }
});
