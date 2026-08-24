<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyReportNotificationJob;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklyReportsDue extends Command
{
    protected $signature = 'reports:send-weekly-due';

    protected $description = 'Send the weekly report to users whose weekly day and time are due now';

    public function handle(): int
    {
        $now = Carbon::now(config('app.timezone', 'UTC'));
        $start = $now->copy()->subMinute()->format('H:i:s');
        $end = $now->copy()->addMinute()->format('H:i:s');
        $weekday = self::jalaliWeekday($now);

        $dispatched = 0;

        User::query()
            ->where('weekly_report', true)
            ->where('weekly_report_day', $weekday)
            ->whereNotNull('weekly_report_time')
            ->whereTime('weekly_report_time', '>=', $start)
            ->whereTime('weekly_report_time', '<=', $end)
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$dispatched) {
                foreach ($users as $user) {
                    SendWeeklyReportNotificationJob::dispatch($user->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} weekly report jobs at {$now->format('H:i')}");

        /*
         * This command runs every minute, so logging the scan unconditionally
         * wrote a line a minute forever: roughly 7,200 lines a day, and 110 MB
         * of laravel.log in six months, of which 99.99% said "dispatched 0".
         * A scan that dispatched nothing is the normal case and says nothing,
         * so only a scan that actually sent something is worth a line.
         */
        if ($dispatched > 0) {
            Log::info('WeeklyReport scan completed', [
                'dispatched' => $dispatched,
                'weekday' => $weekday,
                'time' => $now->format('H:i'),
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * Day of the jalali week: 0 is Saturday and 6 is Friday.
     *
     * Same numbering as the weekday chart on the report page, so a user who
     * picks "Friday" in the settings gets the day the chart calls Friday.
     */
    public static function jalaliWeekday(Carbon $date): int
    {
        return ($date->dayOfWeek + 1) % 7;
    }
}
