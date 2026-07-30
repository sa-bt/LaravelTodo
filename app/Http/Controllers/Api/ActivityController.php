<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Services\ActivityReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;

class ActivityController extends Controller
{
    /**
     * Upper bound for the task list returned next to the backlog counter.
     * The counter itself is never capped.
     */
    private const BACKLOG_DEFAULT_LIMIT = 100;

    private const BACKLOG_MAX_LIMIT = 200;

    /**
     * Window of the per goal strip. Wide enough to show a habit, short enough
     * to stay readable inside a card.
     */
    private const GOAL_ACTIVITY_DEFAULT_DAYS = 30;

    private const GOAL_ACTIVITY_MIN_DAYS = 7;

    private const GOAL_ACTIVITY_MAX_DAYS = 90;

    /**
     * How many goals the year card lists. A card is a highlight, not a table.
     */
    private const YEAR_REVIEW_TOP_GOALS = 5;

    public function __construct(private ActivityReportService $reports) {}

    /**
     * Yearly activity report.
     *
     * Superseded by activity(), which takes a range instead of a jalali year.
     * This route stays until the client has fully migrated, and its response
     * shape must not change: the flat top level keys below are the old contract.
     */
    public function index(Request $request, $year): JsonResponse
    {
        $validated = $request->validate([
            'goal_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $year = (int) $year;

        if (! $this->reports->isSupportedJalaliYear($year)) {
            return $this->errorResponse(
                errors: ['Invalid Jalali year.'],
                messageKey: 'validation_error',
                code: 422
            );
        }

        $goalIds = $this->resolveGoalIds(
            isset($validated['goal_id']) ? (int) $validated['goal_id'] : null
        );

        if ($goalIds instanceof JsonResponse) {
            return $goalIds;
        }

        [$start, $end] = $this->reports->jalaliYearRange($year);

        $days = $this->reports->dailyActivity($goalIds, $start, $end);
        $summary = $this->reports->summarize($days);

        return response()->json([
            'status' => true,
            'data'   => $days,
            'perfect_days_count'            => $summary['perfect_days'],
            'average_completion_percentage' => $summary['average_percent'],
            'inactive_days'                 => $summary['inactive_days'],
            'total_tasks_year_to_date'      => $summary['total_tasks'],
        ]);
    }

    /**
     * Activity report over an arbitrary range.
     *
     * Unlike the yearly route, a range here may cross the jalali new year, so
     * a custom range is no longer clipped to one year.
     */
    public function activity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from'    => ['required', 'date_format:Y-m-d'],
            'to'      => ['required', 'date_format:Y-m-d'],
            'goal_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $range = $this->resolveRange($validated['from'], $validated['to']);

        if ($range instanceof JsonResponse) {
            return $range;
        }

        [$from, $to] = $range;

        $goalIds = $this->resolveGoalIds(
            isset($validated['goal_id']) ? (int) $validated['goal_id'] : null
        );

        if ($goalIds instanceof JsonResponse) {
            return $goalIds;
        }

        $days = $this->reports->dailyActivity($goalIds, $from, $to);

        return $this->successResponse([
            'from'    => $from->toDateString(),
            'to'      => $to->toDateString(),
            'days'    => $days,
            'summary' => $this->reports->summarize($days),
        ]);
    }

    /**
     * Backlog report: how far behind the user is, and on what.
     */
    public function backlog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'goal_id' => ['nullable', 'integer', 'min:1'],
            'limit'   => ['nullable', 'integer', 'min:1', 'max:' . self::BACKLOG_MAX_LIMIT],
        ]);

        $goalIds = $this->resolveGoalIds(
            isset($validated['goal_id']) ? (int) $validated['goal_id'] : null
        );

        if ($goalIds instanceof JsonResponse) {
            return $goalIds;
        }

        $backlog = $this->reports->backlog(
            $goalIds,
            (int) ($validated['limit'] ?? self::BACKLOG_DEFAULT_LIMIT)
        );

        $oldestDay = $backlog['oldestDay'];

        return $this->successResponse([
            'count'             => $backlog['count'],
            'returned'          => $backlog['tasks']->count(),
            'oldest_day'        => $oldestDay?->toDateString(),
            'oldest_day_shamsi' => $oldestDay ? Jalalian::fromCarbon($oldestDay)->format('Y-m-d') : null,
            // Whole days between the oldest unfinished task and today.
            'days_behind'       => $oldestDay ? $oldestDay->diffInDays(Carbon::today()) : 0,
            'tasks'             => TaskResource::collection($backlog['tasks']),
        ]);
    }

    /**
     * A short daily series per goal, ending today.
     *
     * Feeds the small strip on every goal card, so the user sees how each goal
     * is going without opening the report page. One request covers every goal.
     */
    public function goalActivity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:' . self::GOAL_ACTIVITY_MIN_DAYS, 'max:' . self::GOAL_ACTIVITY_MAX_DAYS],
        ]);

        $days = (int) ($validated['days'] ?? self::GOAL_ACTIVITY_DEFAULT_DAYS);

        // Tomorrow is not part of "the last thirty days".
        $to = Carbon::today();
        $from = $to->copy()->subDays($days - 1);

        $report = $this->reports->goalActivity(auth()->user()->goals()->pluck('id'), $from, $to);

        return $this->successResponse([
            'from'  => $from->toDateString(),
            'to'    => $to->toDateString(),
            'days'  => $report['days'],
            // Cast so a single goal, or none at all, still encodes as an object.
            'goals' => (object) $report['goals'],
        ]);
    }

    /**
     * Goal ranking: completion rate of every goal inside a range.
     *
     * The user's goal filter is deliberately ignored here. The whole point of
     * this report is comparing goals against each other.
     */
    public function goalRanking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to'   => ['required', 'date_format:Y-m-d'],
        ]);

        $range = $this->resolveRange($validated['from'], $validated['to']);

        if ($range instanceof JsonResponse) {
            return $range;
        }

        [$from, $to] = $range;

        // Future days never take part in a statistic, same as every other report.
        $today = Carbon::today();

        if ($to->gt($today)) {
            $to = $today;
        }

        $goals = $from->gt($to)
            ? collect()
            : $this->reports->goalRanking(auth()->user()->goals()->pluck('title', 'id'), $from, $to);

        return $this->successResponse([
            'from'  => $from->toDateString(),
            'to'    => $to->toDateString(),
            'goals' => $goals,
        ]);
    }

    /**
     * Year card: the highlights of a whole jalali year in one response.
     *
     * The goal filter is ignored here on purpose, same as the ranking report.
     * This card is a summary of the year as a whole, not of one goal.
     */
    public function yearReview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer'],
        ]);

        $year = (int) ($validated['year'] ?? Jalalian::fromCarbon(Carbon::today())->getYear());

        if (! $this->reports->isSupportedJalaliYear($year)) {
            return $this->errorResponse(
                errors: ['Invalid Jalali year.'],
                messageKey: 'validation_error',
                code: 422
            );
        }

        $goals = auth()->user()->goals()->pluck('title', 'id');

        return $this->successResponse($this->reports->yearReview(
            $goals->keys(),
            $goals,
            $year,
            self::YEAR_REVIEW_TOP_GOALS
        ));
    }

    /**
     * Shared range parsing for every range based report.
     *
     * Returns the error response itself when the range is unusable, so the
     * caller only has to forward it.
     *
     * @return array{0: Carbon, 1: Carbon}|JsonResponse
     */
    private function resolveRange(string $from, string $to): array|JsonResponse
    {
        $start = Carbon::createFromFormat('Y-m-d', $from)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $to)->startOfDay();

        if ($start->gt($end)) {
            return $this->errorResponse(
                errors: ['from must not be after to.'],
                messageKey: 'validation_error',
                code: 422
            );
        }

        if ($this->reports->rangeLength($start, $end) > ActivityReportService::MAX_RANGE_DAYS) {
            return $this->errorResponse(
                errors: ['Range must not exceed ' . ActivityReportService::MAX_RANGE_DAYS . ' days.'],
                messageKey: 'validation_error',
                code: 422
            );
        }

        return [$start, $end];
    }

    /**
     * Ownership scope for every activity report.
     *
     * The tasks table has no user_id column, so task ownership is derived from
     * the parent goal. Without this scope a report aggregates every user's tasks.
     *
     * Returns the error response itself when the requested goal is not the
     * caller's, so the caller only has to forward it.
     */
    private function resolveGoalIds(?int $requestedGoalId): Collection|JsonResponse
    {
        $goalIds = auth()->user()->goals()->pluck('id');

        if (empty($requestedGoalId)) {
            return $goalIds;
        }

        if (! $goalIds->contains($requestedGoalId)) {
            return $this->errorResponse(
                errors: ['Goal not found or unauthorized.'],
                messageKey: 'forbidden',
                code: 403
            );
        }

        return collect([$requestedGoalId]);
    }
}
