<?php

namespace App\Console\Commands;

use App\Jobs\SendDailyReportNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendDailyReportsDue extends Command
{
    protected $signature = 'reports:send-due';
    protected $description = 'Send daily report notification for users whose report_time is due now';

    public function handle(): int
    {
        // از timezone برنامه استفاده کن
        $now = Carbon::now(config('app.timezone', 'UTC'));

        // بازه‌ی تحمل ±۱ دقیقه با ثانیه
        $start = $now->copy()->subMinute()->format('H:i:s');
        $end   = $now->copy()->addMinute()->format('H:i:s');

        $today = $now->toDateString();

        // فقط ستون‌های لازم
        User::query()
            ->where('daily_report', true)
            ->whereNotNull('report_time')
            // اگر report_time نوع TIME است، whereTime عالی کار می‌کند
            // ->whereTime('report_time', '>=', $start)
            // ->whereTime('report_time', '<=', $end)
            ->select(['id', 'report_time'])
            ->orderBy('id') // (برای consistency در chunk پیشنهاد می‌شود)
            ->chunkById(200, function ($users) use ($today, $now) {
                foreach ($users as $user) {
                    // dd($user->report_time, $now->format('H:i:s'));
                    // $key = "daily_report_sent:{$user->id}:{$today}";

                    // // ست کردن اتمیک: اگر وجود ندارد، بساز و همزمان dispatch کن
                    // $set = Cache::add($key, true, $now->copy()->endOfDay());
                    // if (!$set) {
                    //     // قبلاً امروز ارسال شده
                    //     continue;
                    // }

                    // حالا امن dispatch کن
                    SendDailyReportNotification::dispatch($user->id);
                }
            });

        $this->info('reports:send-due checked');
        return self::SUCCESS;
    }
}
