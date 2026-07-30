<?php

namespace App\Console\Commands;

use App\Models\PageView;
use App\Models\VisitDailyStat;
use App\Services\VisitReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Rolls raw page views into one permanent row per day.
 *
 * Recent windows are still read live from the raw rows, so this is not what
 * makes today's page work. It exists so the raw rows can be pruned without
 * losing the history behind them.
 */
class AggregateVisitStats extends Command
{
    protected $signature = 'visits:aggregate
                            {--date= : Aggregate a single day instead of the recent ones}
                            {--days=3 : How many days back to rebuild when no date is given}';

    protected $description = 'Roll raw page views into the daily visit statistics table';

    public function __construct(private VisitReportService $reports)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = $this->resolveDays();

        foreach ($days as $day) {
            $this->aggregate($day);
        }

        $this->info('Aggregated '.count($days).' day(s) of visit statistics.');

        Log::info('VisitStats aggregation completed', [
            'days' => count($days),
            'first' => $days[0]->toDateString(),
            'last' => end($days)->toDateString(),
        ]);

        return self::SUCCESS;
    }

    /**
     * Without a date, the last few days are rebuilt rather than only
     * yesterday. A night the scheduler did not run would otherwise leave a
     * permanent hole, and rebuilding a day that is already correct costs
     * nothing.
     *
     * @return array<int, Carbon>
     */
    private function resolveDays(): array
    {
        if ($date = $this->option('date')) {
            return [Carbon::parse($date)->startOfDay()];
        }

        $count = max(1, (int) $this->option('days'));
        $days = [];

        for ($back = $count; $back >= 1; $back--) {
            $days[] = Carbon::today()->subDays($back);
        }

        return $days;
    }

    private function aggregate(Carbon $day): void
    {
        $totals = $this->reports->totals($day, $day);

        VisitDailyStat::query()->updateOrCreate(
            ['date' => $day->toDateString()],
            [
                'views' => $totals['views'],
                'unique_visitors' => $totals['unique_visitors'],
                'sessions' => $totals['sessions'],
                'bounced_sessions' => $totals['bounced_sessions'],
                'guest_views' => $totals['guest_views'],
                'member_views' => $totals['member_views'],
                'active_members' => $totals['active_members'],
                'new_visitors' => $totals['new_visitors'],
                'avg_session_seconds' => $totals['avg_session_seconds'],
                'top_paths' => $this->reports->topPaths($day, $day),
                'referrer_groups' => $this->reports->breakdown($day, $day, 'referrer_group'),
                'device_types' => $this->reports->breakdown($day, $day, 'device_type'),
                'browsers' => $this->reports->breakdown($day, $day, 'browser'),
                'hourly' => $this->hourly($day),
            ]
        );
    }

    /**
     * Twenty four numbers, hour zero first.
     *
     * @return array<int, int>
     */
    private function hourly(Carbon $day): array
    {
        $rows = PageView::query()
            ->human()
            ->whereBetween('created_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->selectRaw('substr(created_at, 12, 2) as bucket')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('bucket')
            ->pluck('views', 'bucket');

        $hourly = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $key = str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
            $hourly[] = (int) ($rows[$key] ?? 0);
        }

        return $hourly;
    }
}
