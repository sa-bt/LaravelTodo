<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TaskNotification;
use Illuminate\Console\Command;

class SendDailyTaskNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:daily-notifications';
    protected $description = 'ارسال نوتیف روزانه برای کاربران';

    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {
            $user->notify(new TaskNotification());
        }

        $this->info('Daily task notifications sent!');
    }
}
