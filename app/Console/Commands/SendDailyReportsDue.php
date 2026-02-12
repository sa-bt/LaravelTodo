<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyReportNotificationJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendDailyReportsDue extends Command
{
    protected $signature = 'reports:send-due';
    protected $description = 'Send daily report notification for users whose report_time is due now';

    public function handle(): int
    {
        $now = Carbon::now(config('app.timezone', 'UTC'));
        $start = $now->copy()->subMinute()->format('H:i:s');
        $end = $now->copy()->addMinute()->format('H:i:s');
        $currentTime = $now->format('H:i');

        Log::info("DailyReport scan started", ['time' => $currentTime]);

        $dispatched = 0;

        User::query()
            ->where('daily_report', true)
            ->whereNotNull('report_time')
            ->whereTime('report_time', '>=', $start)
            ->whereTime('report_time', '<=', $end)
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$dispatched) {
                foreach ($users as $user) {
                    SendDailyReportNotificationJob::dispatch($user->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} daily report jobs at {$currentTime}");

        Log::info("DailyReport scan completed", [
            'dispatched' => $dispatched,
            'time' => $currentTime,
        ]);

        return self::SUCCESS;
    }
}
