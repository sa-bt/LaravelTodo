<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ReportNotification;
use App\Services\ActivityReportService;
use App\Services\ReportMessageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

/**
 * The drop alert: a gentle nudge after two weeks of falling completion rate.
 *
 * Unlike the weekly report, this one is not a summary. It fires only on a real
 * pattern, and it lands at the start of the week rather than its end, because a
 * warning the user can still act on is worth more than one that arrives when
 * the week is already spent.
 *
 * Numbers come from ActivityReportService, so the alert, the weekly report and
 * the report page can never count the same week differently.
 */
class SendDropAlertNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    /** Length of one period, in days. Same window the weekly report uses. */
    public const WEEK_DAYS = 7;

    /**
     * Periods compared.
     *
     * Two consecutive declines need three weeks. Two weeks would only prove a
     * single step down, which the weekly report already says on its own.
     */
    public const PERIODS = 3;

    /**
     * Quietest possible cadence: at most one alert every two weeks.
     *
     * Someone in a long slump would otherwise get the same sentence every
     * single week, which is exactly the nagging this message is trying not to
     * be.
     */
    public const QUIET_DAYS = 13;

    #[WithoutRelations]
    public int $userId;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function handle(ReportMessageService $messages, ActivityReportService $reports): void
    {
        $user = User::find($this->userId);

        if (! $this->canSend($user)) {
            return;
        }

        $today = Carbon::today();

        if (Cache::has(self::dedupKey($user->id))) {
            Log::info('Drop alert skipped because one was sent recently.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        /*
         * The weekly report already carries a comparison with last week. Two
         * messages about the same numbers on the same day read as a bug.
         */
        if (Cache::has(SendWeeklyReportNotificationJob::dedupKey($user->id, $today->toDateString()))) {
            Log::info('Drop alert skipped because the weekly report went out today.', [
                'user_id' => $user->id,
            ]);

            return;
        }

        $weeks = $this->weeks($reports, $user->goals()->pluck('id'), $today);

        if (! $this->isDeclining($weeks)) {
            Log::info('Drop alert skipped because there is no two week decline.', [
                'user_id' => $user->id,
                'percents' => array_column($weeks, 'percent'),
                'totals' => array_column($weeks, 'total'),
            ]);

            return;
        }

        $percents = array_column($weeks, 'percent');
        $backlog = $reports->backlog($user->goals()->pluck('id'), 1)['count'];
        $current = $weeks[count($weeks) - 1];

        try {
            $user->notify(new ReportNotification(
                title: '🌱 هفته تازه',
                body: $messages->dropAlertBody($percents, $backlog),
                type: 'drop_alert',
                tag: "drop-alert-{$today->toDateString()}",
                // تب هفتگی، چون همان جایی است که این افت دیده می‌شود.
                url: '/app/year?tab=weekly&range=last30',
                percent: $current['percent'],
                remaining: $current['total'] - $current['done'],
                meta: [
                    'type' => 'drop_alert',
                    'from' => $weeks[0]['from'],
                    'to' => $current['to'],
                    'from_shamsi' => Jalalian::fromCarbon(Carbon::parse($weeks[0]['from']))->format('Y-m-d'),
                    'to_shamsi' => Jalalian::fromCarbon(Carbon::parse($current['to']))->format('Y-m-d'),
                    'percents' => $percents,
                    'total' => $current['total'],
                    'done' => $current['done'],
                    'percent' => $current['percent'],
                    'backlog' => $backlog,
                ],
            ));

            Cache::put(self::dedupKey($user->id), true, now()->addDays(self::QUIET_DAYS));

            Log::info('Drop alert sent successfully.', [
                'user_id' => $user->id,
                'percents' => $percents,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Drop alert failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Key that keeps the alert rare.
     *
     * It is not tied to a date: the point is the gap since the last alert, not
     * the week it belonged to.
     */
    public static function dedupKey(int $userId): string
    {
        return "drop-alert:user:{$userId}";
    }

    /**
     * The last three weeks, oldest first, each ending where the next begins.
     *
     * @return array<int, array{from: string, to: string, total: int, done: int, percent: int}>
     */
    private function weeks(ActivityReportService $reports, $goalIds, Carbon $today): array
    {
        $weeks = [];

        for ($step = self::PERIODS - 1; $step >= 0; $step--) {
            $to = $today->copy()->subDays($step * self::WEEK_DAYS);
            $from = $to->copy()->subDays(self::WEEK_DAYS - 1);

            $summary = $reports->summarize($reports->dailyActivity($goalIds, $from, $to));

            $weeks[] = [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'total' => $summary['total_tasks'],
                'done' => $summary['done_tasks'],
                'percent' => (int) round($summary['average_percent']),
            ];
        }

        return $weeks;
    }

    /**
     * Two consecutive declines, worth mentioning.
     *
     * Three guards, each one there because without it the alert would lie:
     *
     * - Every week must have tasks. A week nobody planned is not a week the
     *   user did badly in, and the report page leaves such weeks out of its
     *   trend for the same reason.
     * - Every step must actually go down. Up then further down is a bad week,
     *   not a slide.
     * - The whole fall must reach MIN_GAP. Below that it is noise, the same
     *   threshold every other comparison in the app uses.
     *
     * @param  array<int, array{total: int, percent: int}>  $weeks
     */
    private function isDeclining(array $weeks): bool
    {
        foreach ($weeks as $week) {
            if ($week['total'] === 0) {
                return false;
            }
        }

        for ($i = 1; $i < count($weeks); $i++) {
            if ($weeks[$i]['percent'] >= $weeks[$i - 1]['percent']) {
                return false;
            }
        }

        return ($weeks[0]['percent'] - $weeks[count($weeks) - 1]['percent']) >= ReportMessageService::MIN_GAP;
    }

    private function canSend(?User $user): bool
    {
        if (! $user) {
            Log::warning('Drop alert skipped because user was not found.', [
                'user_id' => $this->userId,
            ]);

            return false;
        }

        if (! $user->drop_alert) {
            Log::info('Drop alert skipped because it is disabled.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        return true;
    }
}
