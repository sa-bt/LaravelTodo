<?php

namespace App\Services;

use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

/**
 * Every activity report is built here.
 *
 * The controller only validates input and resolves goal ownership. Keeping the
 * calculations in one place is what makes a range wider than a single jalali
 * year possible: nothing below knows or cares about years.
 */
class ActivityReportService
{
    /**
     * Widest range a single report may span.
     *
     * The daily array is built day by day in PHP, so an unbounded range is a
     * memory hazard. Three jalali years covers every report on the roadmap,
     * including a year against year comparison.
     */
    public const MAX_RANGE_DAYS = 1100;

    /**
     * Jalali years the yearly report accepts.
     * Keeps malformed route input from reaching the Jalali parser.
     */
    private const MIN_JALALI_YEAR = 1300;

    private const MAX_JALALI_YEAR = 1500;

    public function isSupportedJalaliYear(int $year): bool
    {
        return $year >= self::MIN_JALALI_YEAR && $year <= self::MAX_JALALI_YEAR;
    }

    /**
     * First and last gregorian day of a jalali year.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function jalaliYearRange(int $year): array
    {
        $isLeap = ((($year * 8) + 29) % 33) < 8;
        $lastDay = $isLeap ? 30 : 29;

        return [
            Jalalian::fromFormat('Y-m-d', $year . '-01-01')->toCarbon()->startOfDay(),
            Jalalian::fromFormat('Y-m-d', $year . '-12-' . $lastDay)->toCarbon()->startOfDay(),
        ];
    }

    /**
     * Whole days in an inclusive range.
     */
    public function rangeLength(CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
    }

    /**
     * Every day of the range keyed by its jalali date, days without tasks included.
     *
     * The key carries the year, so a range crossing the jalali new year stays
     * unambiguous and callers need no special case for it.
     */
    public function dailyActivity(Collection $goalIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        $tasks = Task::query()
            ->whereIn('goal_id', $goalIds)
            ->whereBetween('day', $this->dateBounds($start, $end))
            ->selectRaw('day, COUNT(*) as total, SUM(is_done = 1) as done')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => Jalalian::fromDateTime($row->day)->format('Y-n-j'));

        $days = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $key = Jalalian::fromCarbon($cursor)->format('Y-n-j');

            $days[$key] = [
                'total' => $tasks->has($key) ? (int) $tasks[$key]->total : 0,
                'done'  => $tasks->has($key) ? (int) $tasks[$key]->done : 0,
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Totals over a daily array. Future days never take part in a statistic.
     */
    public function summarize(array $days): array
    {
        $todayNumeric = (int) Jalalian::fromCarbon(Carbon::today())->format('Ymd');

        $totalTasks = 0;
        $doneTasks = 0;
        $perfectDays = 0;
        $inactiveDays = 0;

        foreach ($days as $key => $day) {
            if ($this->keyToNumeric($key) > $todayNumeric) {
                continue;
            }

            $totalTasks += $day['total'];
            $doneTasks += $day['done'];

            if ($day['total'] > 0 && $day['done'] === $day['total']) {
                $perfectDays++;
            }

            if ($day['total'] === 0) {
                $inactiveDays++;
            }
        }

        return [
            'total_tasks'     => $totalTasks,
            'done_tasks'      => $doneTasks,
            'perfect_days'    => $perfectDays,
            'inactive_days'   => $inactiveDays,
            'average_percent' => $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 0,
        ];
    }

    /**
     * Highlights of a whole jalali year, for the year card.
     *
     * Everything here comes from the same daily array the report page draws, so
     * a number on the card and the same number on the report page can never
     * disagree. The goal filter is deliberately not part of it: the card is a
     * summary of the year as a whole, the way the user actually lived it.
     *
     * @param  Collection  $titles  goal id => title
     * @param  int  $topGoals  how many goals the card lists
     */
    public function yearReview(Collection $goalIds, Collection $titles, int $year, int $topGoals): array
    {
        [$start, $end] = $this->jalaliYearRange($year);

        $days = $this->dailyActivity($goalIds, $start, $end);
        $summary = $this->summarize($days);

        $todayNumeric = (int) Jalalian::fromCarbon(Carbon::today())->format('Ymd');

        // Saturday is zero, same numbering the weekly report and the weekday
        // chart already use. dailyActivity() returns the range in order, so the
        // weekday of a day is its offset from the first one.
        $startWeekday = ($start->dayOfWeek + 1) % 7;

        $months = [];
        $weekdays = [];

        for ($month = 1; $month <= 12; $month++) {
            $months[$month] = ['month' => $month, 'total' => 0, 'done' => 0];
        }

        for ($weekday = 0; $weekday < 7; $weekday++) {
            $weekdays[$weekday] = ['index' => $weekday, 'total' => 0, 'done' => 0];
        }

        $streak = ['length' => 0, 'start' => null, 'end' => null];
        $running = 0;
        $runningStart = null;

        $activeDays = 0;
        $recordedDays = 0;
        $firstDay = null;
        $busiestDay = null;

        $offset = 0;

        foreach ($days as $key => $day) {
            $weekday = ($startWeekday + $offset) % 7;
            $offset++;

            // A day that has not arrived yet takes part in no statistic.
            if ($this->keyToNumeric($key) > $todayNumeric) {
                continue;
            }

            $recordedDays++;

            $month = (int) explode('-', $key)[1];

            $months[$month]['total'] += $day['total'];
            $months[$month]['done'] += $day['done'];

            $weekdays[$weekday]['total'] += $day['total'];
            $weekdays[$weekday]['done'] += $day['done'];

            // A day without tasks neither breaks the streak nor extends it.
            // Not planning a day is not a failure. Same rule as the report page.
            if ($day['total'] === 0) {
                continue;
            }

            $activeDays++;
            $firstDay ??= $key;

            if ($busiestDay === null || $day['total'] > $busiestDay['total']) {
                $busiestDay = ['day' => $key, 'total' => $day['total'], 'done' => $day['done']];
            }

            if ($day['done'] === $day['total']) {
                $running++;
                $runningStart ??= $key;

                // Equal length replaces, so a tie shows the more recent streak.
                if ($running >= $streak['length']) {
                    $streak = ['length' => $running, 'start' => $runningStart, 'end' => $key];
                }

                continue;
            }

            $running = 0;
            $runningStart = null;
        }

        return [
            'year'          => $year,
            'from'          => $start->toDateString(),
            'to'            => $end->toDateString(),
            'summary'       => $summary + [
                'active_days'   => $activeDays,
                'recorded_days' => $recordedDays,
            ],
            'streak'        => $streak,
            'months'        => array_values(array_map($this->withPercent(...), $months)),
            'weekdays'      => array_values(array_map($this->withPercent(...), $weekdays)),
            'top_goals'     => $this->yearRanking($titles, $start, $end)->take($topGoals)->values(),
            'busiest_day'   => $busiestDay,
            'first_day'     => $firstDay,
            'previous_year' => $this->previousYearSummary($goalIds, $year),
        ];
    }

    /**
     * A short daily series per goal, for the strip drawn on every goal card.
     *
     * The day list is returned once instead of being repeated inside every
     * goal, and each goal carries two arrays aligned with it by position. A
     * goal without a single task in the window is left out entirely: the
     * client already draws a missing goal as an empty strip, so sending
     * arrays full of zeroes would only make the payload bigger.
     *
     * @return array{days: array<int, string>, goals: array<int, array{total: array<int, int>, done: array<int, int>}>}
     */
    public function goalActivity(Collection $goalIds, CarbonInterface $from, CarbonInterface $to): array
    {
        $start = $from->copy()->startOfDay();
        $length = $this->rangeLength($start, $to);

        $days = [];
        $position = [];

        for ($i = 0; $i < $length; $i++) {
            $day = $start->copy()->addDays($i)->toDateString();

            $days[] = $day;
            $position[$day] = $i;
        }

        $rows = Task::query()
            ->whereIn('goal_id', $goalIds)
            ->whereBetween('day', $this->dateBounds($start, $to))
            ->selectRaw('goal_id, day, COUNT(*) as total, SUM(is_done = 1) as done')
            ->groupBy('goal_id', 'day')
            ->get();

        $goals = [];

        foreach ($rows as $row) {
            $day = Carbon::parse($row->day)->toDateString();

            if (! isset($position[$day])) {
                continue;
            }

            $goalId = (int) $row->goal_id;

            $goals[$goalId] ??= [
                'total' => array_fill(0, $length, 0),
                'done'  => array_fill(0, $length, 0),
            ];

            $goals[$goalId]['total'][$position[$day]] = (int) $row->total;
            $goals[$goalId]['done'][$position[$day]] = (int) $row->done;
        }

        return ['days' => $days, 'goals' => $goals];
    }

    /**
     * Tasks whose day is already past while still not done.
     *
     * Today is deliberately excluded. A task planned for today is not late yet.
     */
    public function backlog(Collection $goalIds, int $limit): array
    {
        $query = Task::query()
            ->whereIn('goal_id', $goalIds)
            ->where('is_done', false)
            ->whereDate('day', '<', Carbon::today());

        $count = (clone $query)->count();

        $tasks = $query
            ->with('goal')
            ->orderBy('day')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $oldest = $tasks->first();
        $oldestDay = $oldest ? Carbon::parse($oldest->day)->startOfDay() : null;

        return [
            'count'     => $count,
            'oldestDay' => $oldestDay,
            'tasks'     => $tasks,
        ];
    }

    /**
     * Completion rate of every goal inside a range, best first.
     *
     * Goals without a single task in the range are left out on purpose. They
     * are not weak, they were simply never scheduled, and a zero percent bar
     * would read as a failure.
     *
     * @param  Collection  $titles  goal id => title
     */
    public function goalRanking(Collection $titles, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Task::query()
            ->whereIn('goal_id', $titles->keys())
            ->whereBetween('day', $this->dateBounds($from, $to))
            ->selectRaw('goal_id, COUNT(*) as total, SUM(is_done = 1) as done')
            ->groupBy('goal_id')
            ->get()
            ->map(function ($row) use ($titles) {
                $total = (int) $row->total;
                $done = (int) $row->done;

                return [
                    'id'      => (int) $row->goal_id,
                    'title'   => (string) ($titles[$row->goal_id] ?? ''),
                    'total'   => $total,
                    'done'    => $done,
                    'percent' => $total > 0 ? round(($done / $total) * 100, 1) : 0,
                ];
            })
            // Ties fall back to the busier goal, then to the title.
            ->sort(fn ($a, $b) => [$b['percent'], $b['total'], $a['title']]
                <=> [$a['percent'], $a['total'], $b['title']])
            ->values();
    }

    /**
     * Goal ranking of a year, with the future left out.
     *
     * A year still running would otherwise be judged on days that have not
     * arrived, and every goal would look worse than it is.
     */
    private function yearRanking(Collection $titles, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $today = Carbon::today();
        $last = $end->copy()->gt($today) ? $today : $end;

        return $start->copy()->gt($last)
            ? collect()
            : $this->goalRanking($titles, $start, $last);
    }

    /**
     * The year before, for the comparison line on the card.
     *
     * Returns null when there is nothing to compare against: a first year has
     * no previous one, and comparing against a year without a single task
     * would only produce a meaningless jump.
     */
    private function previousYearSummary(Collection $goalIds, int $year): ?array
    {
        $previous = $year - 1;

        if (! $this->isSupportedJalaliYear($previous)) {
            return null;
        }

        [$start, $end] = $this->jalaliYearRange($previous);

        $summary = $this->summarize($this->dailyActivity($goalIds, $start, $end));

        return $summary['total_tasks'] > 0 ? ['year' => $previous] + $summary : null;
    }

    /**
     * Completion rate of a bucket that already carries its totals.
     */
    private function withPercent(array $bucket): array
    {
        $bucket['percent'] = $bucket['total'] > 0
            ? round(($bucket['done'] / $bucket['total']) * 100, 1)
            : 0;

        return $bucket;
    }

    /**
     * Inclusive bounds for a query against the tasks.day column.
     *
     * That column is a DATE. Binding datetime bounds makes the lower bound
     * miss every task that falls exactly on the first day of the range, because
     * SQLite compares '2026-07-26' against '2026-07-26 00:00:00' as plain text.
     * MySQL hides the mistake by coercing the types. Date strings are correct
     * on both.
     *
     * @return array{0: string, 1: string}
     */
    private function dateBounds(CarbonInterface $from, CarbonInterface $to): array
    {
        return [$from->copy()->toDateString(), $to->copy()->toDateString()];
    }

    /**
     * Jalali key such as 1405-2-11 to its comparable numeric form.
     */
    private function keyToNumeric(string $key): int
    {
        [$year, $month, $day] = explode('-', $key);

        return (int) ($year
            . str_pad($month, 2, '0', STR_PAD_LEFT)
            . str_pad($day, 2, '0', STR_PAD_LEFT));
    }
}
