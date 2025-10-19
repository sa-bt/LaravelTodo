<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyReportNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SendDailyReportsDue extends Command
{
    protected $signature = 'reports:send-due';
    protected $description = 'Send daily report push for users whose report_time matches now';

    public function handle(): int
    {
        $now = Carbon::now();
        $current = $now->format('H:i');     // مطابق فرمت کنترلرت
        $today   = $now->toDateString();

        User::where('daily_report', true)
            ->whereNotNull('report_time')
            ->where('report_time', $current)
            ->chunkById(200, function ($users) use ($today) {
                foreach ($users as $user) {
                    $key = "daily_report_sent:{$user->id}:{$today}";
                    if (Cache::has($key)) continue;

                    SendDailyReportNotification::dispatch($user->id);
                    Cache::put($key, true, now()->endOfDay());
                }
            });

        $this->info('reports:send-due checked');
        return self::SUCCESS;
    }
}
