<?php

namespace App\Services;

use App\Console\Commands\SendWeeklyReportsDue;
use App\Models\PageView;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

/**
 * Every visit report is built here.
 *
 * The controller only validates the requested window. Keeping the queries in
 * one place is what lets the daily, the weekly and the free range view share a
 * single definition of a visit, a session and a bounce.
 */
class VisitReportService
{
    /**
     * Widest window a single report may cover.
     *
     * The daily series is built day by day, and the page turns unreadable long
     * before this, but a bound has to exist because the range comes from the
     * query string.
     */
    public const MAX_RANGE_DAYS = 400;

    /** How many pages the top list carries. A list, not a table dump. */
    private const TOP_PATHS_LIMIT = 15;

    /** Periods shown in the trend line next to the current one. */
    private const TREND_POINTS = 14;

    public const PERIOD_DAY = 'day';
    public const PERIOD_WEEK = 'week';
    public const PERIOD_RANGE = 'range';

    public function dayReport(CarbonInterface $date): array
    {
        $day = $date->copy()->startOfDay();

        return $this->buildReport(self::PERIOD_DAY, $day, $day->copy());
    }

    /**
     * The jalali week holding the given day, Saturday through Friday.
     *
     * The weekday numbering comes from the weekly notification command rather
     * than a second copy of the same formula, so the week the admin sees here
     * and the week a user is mailed about start on the same day.
     */
    public function weekReport(CarbonInterface $anyDayOfWeek): array
    {
        $start = $anyDayOfWeek->copy()->startOfDay()
            ->subDays(SendWeeklyReportsDue::jalaliWeekday($anyDayOfWeek->copy()));

        return $this->buildReport(self::PERIOD_WEEK, $start, $start->copy()->addDays(6));
    }

    public function rangeReport(CarbonInterface $from, CarbonInterface $to): array
    {
        return $this->buildReport(
            self::PERIOD_RANGE,
            $from->copy()->startOfDay(),
            $to->copy()->startOfDay()
        );
    }

    /**
     * Whole days in an inclusive range.
     */
    public function rangeLength(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
    }

    private function buildReport(string $type, Carbon $from, Carbon $to): array
    {
        $length = $this->rangeLength($from, $to);

        /*
         * The comparison window is the same number of days ending right before
         * this one. A day is compared to yesterday, a week to the week before,
         * and a nine day range to the nine days before it.
         */
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($length - 1);

        $totals = $this->totals($from, $to);
        $previous = $this->totals($previousFrom, $previousTo);

        return [
            'period' => [
                'type' => $type,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $length,
                'jalali_from' => Jalalian::fromCarbon($from)->format('Y/m/d'),
                'jalali_to' => Jalalian::fromCarbon($to)->format('Y/m/d'),
            ],
            'totals' => $totals,
            'previous' => $previous,
            'change' => $this->change($totals, $previous),
            'series' => $this->series($type, $from, $to),
            'trend' => $this->trend($type, $from),
            'top_paths' => $this->topPaths($from, $to),
            'referrer_groups' => $this->breakdown($from, $to, 'referrer_group'),
            'devices' => $this->breakdown($from, $to, 'device_type'),
            'browsers' => $this->breakdown($from, $to, 'browser'),
            'top_referrers' => $this->topReferrers($from, $to),
        ];
    }

    /**
     * The headline numbers of one window.
     */
    public function totals(Carbon $from, Carbon $to): array
    {
        $bounds = $this->bounds($from, $to);

        $row = $this->human()
            ->whereBetween('created_at', $bounds)
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_id) as unique_visitors')
            ->selectRaw('COUNT(DISTINCT session_id) as sessions')
            ->selectRaw('COUNT(DISTINCT user_id) as active_members')
            ->selectRaw('SUM(CASE WHEN is_guest = 1 THEN 1 ELSE 0 END) as guest_views')
            ->selectRaw('SUM(CASE WHEN is_guest = 0 THEN 1 ELSE 0 END) as member_views')
            ->first();

        $views = (int) ($row->views ?? 0);
        $sessions = (int) ($row->sessions ?? 0);
        $bounced = $this->bouncedSessions($from, $to);

        return [
            'views' => $views,
            'unique_visitors' => (int) ($row->unique_visitors ?? 0),
            'sessions' => $sessions,
            'active_members' => (int) ($row->active_members ?? 0),
            'new_visitors' => $this->newVisitors($from, $to),
            'guest_views' => (int) ($row->guest_views ?? 0),
            'member_views' => (int) ($row->member_views ?? 0),
            'bounced_sessions' => $bounced,

            // A visit that never left its first page. Zero sessions means no
            // rate at all, not a rate of zero.
            'bounce_rate' => $sessions > 0 ? round(($bounced / $sessions) * 100, 1) : null,

            'views_per_session' => $sessions > 0 ? round($views / $sessions, 1) : null,
            'avg_session_seconds' => $this->averageSessionSeconds($from, $to),
        ];
    }

    /**
     * Sessions that carried a single page view.
     */
    private function bouncedSessions(Carbon $from, Carbon $to): int
    {
        $sessions = $this->human()
            ->whereBetween('created_at', $this->bounds($from, $to))
            ->select('session_id')
            ->groupBy('session_id')
            ->havingRaw('COUNT(*) = 1');

        return (int) DB::query()->fromSub($sessions, 'single')->count();
    }

    /**
     * Visitors seen in this window who had never been seen before it.
     *
     * Asked as "no earlier row exists" rather than by grouping the whole table
     * on first visit, so the cost stays tied to the window and not to how long
     * the site has been running.
     */
    private function newVisitors(Carbon $from, Carbon $to): int
    {
        $bounds = $this->bounds($from, $to);

        return (int) $this->human()
            ->whereBetween('created_at', $bounds)
            ->whereNotExists(function ($query) use ($bounds) {
                $query->select(DB::raw(1))
                    ->from('page_views as earlier')
                    ->whereColumn('earlier.visitor_id', 'page_views.visitor_id')
                    ->where('earlier.is_bot', false)
                    ->where('earlier.created_at', '<', $bounds[0]);
            })
            ->distinct()
            ->count('visitor_id');
    }

    /**
     * Average seconds between the first and last view of a session.
     *
     * Single page sessions count as zero rather than being dropped. Leaving
     * them out would turn a day of visitors who bounced immediately into a day
     * with an excellent average, which is the opposite of the truth.
     */
    private function averageSessionSeconds(Carbon $from, Carbon $to): int
    {
        $sessions = $this->human()
            ->whereBetween('created_at', $this->bounds($from, $to))
            ->select('session_id')
            ->selectRaw('MIN(created_at) as started_at')
            ->selectRaw('MAX(created_at) as ended_at')
            ->groupBy('session_id')
            ->get();

        if ($sessions->isEmpty()) {
            return 0;
        }

        $total = $sessions->sum(fn ($session) => Carbon::parse($session->started_at)
            ->diffInSeconds(Carbon::parse($session->ended_at)));

        return (int) round($total / $sessions->count());
    }

    /**
     * Percentage change against the comparison window.
     *
     * A window that had nothing to compare against returns null rather than a
     * hundred percent, because growth from zero is not a percentage.
     */
    private function change(array $totals, array $previous): array
    {
        $keys = ['views', 'unique_visitors', 'sessions', 'active_members', 'new_visitors'];
        $change = [];

        foreach ($keys as $key) {
            $before = (int) ($previous[$key] ?? 0);
            $now = (int) ($totals[$key] ?? 0);

            $change[$key] = $before > 0
                ? round((($now - $before) / $before) * 100, 1)
                : null;
        }

        return $change;
    }

    /**
     * The shape of traffic inside the window: by hour for a single day, by day
     * for anything longer.
     */
    private function series(string $type, Carbon $from, Carbon $to): array
    {
        if ($type === self::PERIOD_DAY) {
            return [
                'unit' => 'hour',
                'points' => $this->hourlyPoints($from),
            ];
        }

        return [
            'unit' => 'day',
            'points' => $this->dailyPoints($from, $to),
        ];
    }

    /**
     * @return array<int, array{label: string, views: int, unique_visitors: int}>
     */
    private function hourlyPoints(Carbon $day): array
    {
        $rows = $this->human()
            ->whereBetween('created_at', $this->bounds($day, $day))
            ->selectRaw($this->hourExpression().' as bucket')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('bucket')
            ->get()
            ->keyBy(fn ($row) => (int) $row->bucket);

        $points = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $points[] = [
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT),
                'views' => (int) ($rows[$hour]->views ?? 0),
                'unique_visitors' => (int) ($rows[$hour]->unique_visitors ?? 0),
            ];
        }

        return $points;
    }

    /**
     * @return array<int, array{date: string, label: string, views: int, unique_visitors: int}>
     */
    private function dailyPoints(Carbon $from, Carbon $to): array
    {
        $rows = $this->dailyCounts($from, $to);

        $points = [];
        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $key = $cursor->toDateString();

            $points[] = [
                'date' => $key,
                'label' => Jalalian::fromCarbon($cursor)->format('m/d'),
                'weekday' => Jalalian::fromCarbon($cursor)->format('D'),
                'views' => (int) ($rows[$key]->views ?? 0),
                'unique_visitors' => (int) ($rows[$key]->unique_visitors ?? 0),
            ];

            $cursor->addDay();
        }

        return $points;
    }

    /**
     * The same window repeated backwards, so the current one can be read
     * against its own recent history rather than against a single neighbour.
     */
    private function trend(string $type, Carbon $from): ?array
    {
        if ($type === self::PERIOD_RANGE) {
            return null;
        }

        $step = $type === self::PERIOD_WEEK ? 7 : 1;
        $spanStart = $from->copy()->subDays($step * (self::TREND_POINTS - 1));
        $rows = $this->dailyCounts($spanStart, $from->copy()->addDays($step - 1));

        $points = [];
        $cursor = $spanStart->copy();

        while ($cursor->lte($from)) {
            $views = 0;
            $unique = 0;
            $bucketEnd = $cursor->copy()->addDays($step - 1);
            $day = $cursor->copy();

            while ($day->lte($bucketEnd)) {
                $key = $day->toDateString();
                $views += (int) ($rows[$key]->views ?? 0);

                /*
                 * Summing daily uniques overcounts anyone who came back on a
                 * second day of the same week. It stays a shape to read the
                 * trend by, and the exact figure is the one in the totals.
                 */
                $unique += (int) ($rows[$key]->unique_visitors ?? 0);
                $day->addDay();
            }

            $points[] = [
                'date' => $cursor->toDateString(),
                'label' => Jalalian::fromCarbon($cursor)->format('m/d'),
                'views' => $views,
                'unique_visitors' => $unique,
            ];

            $cursor->addDays($step);
        }

        return $points;
    }

    /**
     * Views and unique visitors per calendar day, keyed by gregorian date.
     */
    private function dailyCounts(Carbon $from, Carbon $to)
    {
        return $this->human()
            ->whereBetween('created_at', $this->bounds($from, $to))
            ->selectRaw($this->dateExpression().' as bucket')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('bucket')
            ->get()
            ->keyBy('bucket');
    }

    /**
     * @return array<int, array{path: string, views: int, unique_visitors: int}>
     */
    public function topPaths(Carbon $from, Carbon $to, int $limit = self::TOP_PATHS_LIMIT): array
    {
        return $this->human()
            ->whereBetween('created_at', $this->bounds($from, $to))
            ->select('path')
            ->selectRaw('COUNT(*) as views')
            ->selectRaw('COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('path')
            ->orderByDesc('views')
            ->orderBy('path')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'views' => (int) $row->views,
                'unique_visitors' => (int) $row->unique_visitors,
            ])
            ->all();
    }

    /**
     * Views grouped by one column, biggest first.
     *
     * @return array<int, array{key: string, views: int}>
     */
    public function breakdown(Carbon $from, Carbon $to, string $column): array
    {
        return $this->human()
            ->whereBetween('created_at', $this->bounds($from, $to))
            ->select($column)
            ->selectRaw('COUNT(*) as views')
            ->groupBy($column)
            ->orderByDesc('views')
            ->orderBy($column)
            ->get()
            ->map(fn ($row) => [
                'key' => (string) $row->{$column},
                'views' => (int) $row->views,
            ])
            ->all();
    }

    /**
     * Named sites people arrived from. Direct visits have no host and are
     * already counted in the referrer groups.
     *
     * @return array<int, array{host: string, views: int}>
     */
    public function topReferrers(Carbon $from, Carbon $to, int $limit = 10): array
    {
        return $this->human()
            ->whereBetween('created_at', $this->bounds($from, $to))
            ->whereNotNull('referrer_host')
            ->where('referrer_group', '!=', PageView::REFERRER_INTERNAL)
            ->select('referrer_host')
            ->selectRaw('COUNT(*) as views')
            ->groupBy('referrer_host')
            ->orderByDesc('views')
            ->orderBy('referrer_host')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'host' => (string) $row->referrer_host,
                'views' => (int) $row->views,
            ])
            ->all();
    }

    private function human()
    {
        return PageView::query()->human();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function bounds(Carbon $from, Carbon $to): array
    {
        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }

    /*
     * Dates are cut out of the stored text rather than read with a date
     * function, because the reports run on MySQL and the tests run on SQLite
     * and the two spell those functions differently. Both store the timestamp
     * as "YYYY-MM-DD HH:MM:SS" in the application timezone, so the positions
     * below hold on either one.
     */
    private function dateExpression(): string
    {
        return 'substr(created_at, 1, 10)';
    }

    private function hourExpression(): string
    {
        return 'substr(created_at, 12, 2)';
    }
}
