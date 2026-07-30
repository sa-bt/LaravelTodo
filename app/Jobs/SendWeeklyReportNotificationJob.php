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
 * The weekly summary, with last week next to it.
 *
 * A bare number says little. "Sixty nine percent" only becomes information
 * when the user learns it was fifty last week, which is why the comparison is
 * the point of this notification rather than a decoration on it.
 *
 * Numbers come from ActivityReportService, so the notification and the report
 * page always agree.
 */
class SendWeeklyReportNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use SerializesModels;

    /** Length of the reported week, in days. */
    public const WEEK_DAYS = 7;

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
        $dedupKey = self::dedupKey($user->id, $today->toDateString());

        if (Cache::has($dedupKey)) {
            Log::info('Weekly report notification skipped because it was already sent.', [
                'user_id' => $user->id,
                'week_end' => $today->toDateString(),
            ]);

            return;
        }

        // The reported week ends today, so the user reads it while it is still
        // the week they lived through.
        $from = $today->copy()->subDays(self::WEEK_DAYS - 1);
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays(self::WEEK_DAYS - 1);

        $goalIds = $user->goals()->pluck('id');

        $week = $this->summarize($reports, $goalIds, $from, $today);
        $previous = $this->summarize($reports, $goalIds, $previousFrom, $previousTo);

        // Two empty weeks in a row means the user is not using the app at all.
        // A summary of nothing, twice, is nagging rather than reporting.
        if ($week['total'] === 0 && $previous['total'] === 0) {
            Log::info('Weekly report notification skipped because both weeks are empty.', [
                'user_id' => $user->id,
                'week_end' => $today->toDateString(),
            ]);

            return;
        }

        $previousPercent = $previous['total'] > 0 ? $previous['percent'] : null;
        $backlog = $reports->backlog($goalIds, 1)['count'];

        try {
            $user->notify(new ReportNotification(
                title: '📅 گزارش هفتگی',
                body: $messages->weeklyBody($week, $previousPercent, $backlog),
                type: 'weekly_report',
                tag: "weekly-report-{$today->toDateString()}",
                url: '/app/year?range=last30',
                percent: $week['percent'],
                remaining: $week['total'] - $week['done'],
                meta: [
                    'type' => 'weekly_report',
                    'from' => $from->toDateString(),
                    'to' => $today->toDateString(),
                    'from_shamsi' => Jalalian::fromCarbon($from)->format('Y-m-d'),
                    'to_shamsi' => Jalalian::fromCarbon($today)->format('Y-m-d'),
                    'total' => $week['total'],
                    'done' => $week['done'],
                    'percent' => $week['percent'],
                    'perfect_days' => $week['perfectDays'],
                    'previous_percent' => $previousPercent,
                    'backlog' => $backlog,
                ],
            ));

            Cache::put($dedupKey, true, now()->addDays(6));

            Log::info('Weekly report notification sent successfully.', [
                'user_id' => $user->id,
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'percent' => $week['percent'],
                'previous_percent' => $previousPercent,
            ]);
        } catch (\Throwable $exception) {
            Log::error('Weekly report notification failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Key that stops a second send in the same week.
     *
     * The TTL is six days, not seven: a user who moves the report to an earlier
     * weekday should still get the next one.
     */
    public static function dedupKey(int $userId, string $weekEnd): string
    {
        return "weekly-report:user:{$userId}:week:{$weekEnd}";
    }

    /**
     * @return array{total: int, done: int, percent: int, perfectDays: int}
     */
    private function summarize(
        ActivityReportService $reports,
        $goalIds,
        Carbon $from,
        Carbon $to
    ): array {
        $summary = $reports->summarize($reports->dailyActivity($goalIds, $from, $to));

        return [
            'total' => $summary['total_tasks'],
            'done' => $summary['done_tasks'],
            'percent' => (int) round($summary['average_percent']),
            'perfectDays' => $summary['perfect_days'],
        ];
    }

    private function canSend(?User $user): bool
    {
        if (! $user) {
            Log::warning('Weekly report notification skipped because user was not found.', [
                'user_id' => $this->userId,
            ]);

            return false;
        }

        if (! $user->weekly_report) {
            Log::info('Weekly report notification skipped because weekly report is disabled.', [
                'user_id' => $user->id,
            ]);

            return false;
        }

        return true;
    }
}
