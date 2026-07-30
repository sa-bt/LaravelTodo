<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Services\ActivityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Morilog\Jalali\Jalalian;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    private function createGoal(User $user, string $title = 'Goal'): Goal
    {
        return Goal::query()->create([
            'user_id' => $user->id,
            'title' => $title,
        ]);
    }

    private function createTask(Goal $goal, string $day, bool $isDone): Task
    {
        return Task::query()->create([
            'goal_id' => $goal->id,
            'title' => 'Task',
            'day' => $day,
            'is_done' => $isDone,
        ]);
    }

    public function test_activity_report_only_contains_tasks_of_the_authenticated_user(): void
    {
        $today = Carbon::today();
        $jalaliToday = Jalalian::fromCarbon($today);
        $year = (int) $jalaliToday->getYear();
        $dayKey = $jalaliToday->format('Y-n-j');

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $ownerGoal = $this->createGoal($owner, 'Owner goal');
        $strangerGoal = $this->createGoal($stranger, 'Stranger goal');

        $this->createTask($ownerGoal, $today->toDateString(), true);
        $this->createTask($strangerGoal, $today->toDateString(), false);

        Sanctum::actingAs($owner);

        $this->getJson("/api/activities/{$year}")
            ->assertOk()
            ->assertJsonPath("data.{$dayKey}.total", 1)
            ->assertJsonPath("data.{$dayKey}.done", 1)
            ->assertJsonPath('total_tasks_year_to_date', 1)
            ->assertJsonPath('perfect_days_count', 1)
            ->assertJsonPath('average_completion_percentage', 100);
    }

    public function test_activity_report_can_be_filtered_by_a_single_goal(): void
    {
        $today = Carbon::today();
        $jalaliToday = Jalalian::fromCarbon($today);
        $year = (int) $jalaliToday->getYear();
        $dayKey = $jalaliToday->format('Y-n-j');

        $user = User::factory()->create();

        $firstGoal = $this->createGoal($user, 'First goal');
        $secondGoal = $this->createGoal($user, 'Second goal');

        $this->createTask($firstGoal, $today->toDateString(), true);
        $this->createTask($secondGoal, $today->toDateString(), false);

        Sanctum::actingAs($user);

        $this->getJson("/api/activities/{$year}?goal_id={$firstGoal->id}")
            ->assertOk()
            ->assertJsonPath("data.{$dayKey}.total", 1)
            ->assertJsonPath("data.{$dayKey}.done", 1);

        $this->getJson("/api/activities/{$year}")
            ->assertOk()
            ->assertJsonPath("data.{$dayKey}.total", 2)
            ->assertJsonPath("data.{$dayKey}.done", 1);
    }

    public function test_activity_report_rejects_a_goal_of_another_user(): void
    {
        $jalaliToday = Jalalian::fromCarbon(Carbon::today());
        $year = (int) $jalaliToday->getYear();

        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $strangerGoal = $this->createGoal($stranger, 'Stranger goal');

        Sanctum::actingAs($user);

        $this->getJson("/api/activities/{$year}?goal_id={$strangerGoal->id}")
            ->assertForbidden();
    }

    public function test_activity_report_rejects_an_invalid_year(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/activities/not-a-year')
            ->assertStatus(422);
    }

    public function test_activity_report_requires_authentication(): void
    {
        $this->getJson('/api/activities/1404')
            ->assertUnauthorized();
    }

    public function test_backlog_report_counts_only_past_unfinished_tasks_of_the_user(): void
    {
        $today = Carbon::today();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $ownerGoal = $this->createGoal($owner, 'Owner goal');
        $strangerGoal = $this->createGoal($stranger, 'Stranger goal');

        // شمرده می‌شوند: گذشته و انجام‌نشده
        $this->createTask($ownerGoal, $today->copy()->subDays(5)->toDateString(), false);
        $this->createTask($ownerGoal, $today->copy()->subDay()->toDateString(), false);

        // شمرده نمی‌شوند
        $this->createTask($ownerGoal, $today->copy()->subDays(3)->toDateString(), true);
        $this->createTask($ownerGoal, $today->toDateString(), false);
        $this->createTask($ownerGoal, $today->copy()->addDay()->toDateString(), false);
        $this->createTask($strangerGoal, $today->copy()->subDays(9)->toDateString(), false);

        Sanctum::actingAs($owner);

        $this->getJson('/api/reports/backlog')
            ->assertOk()
            ->assertJsonPath('data.count', 2)
            ->assertJsonPath('data.oldest_day', $today->copy()->subDays(5)->toDateString())
            ->assertJsonPath('data.days_behind', 5)
            ->assertJsonCount(2, 'data.tasks');
    }

    public function test_backlog_report_can_be_filtered_by_a_single_goal(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $firstGoal = $this->createGoal($user, 'First goal');
        $secondGoal = $this->createGoal($user, 'Second goal');

        $this->createTask($firstGoal, $today->copy()->subDay()->toDateString(), false);
        $this->createTask($secondGoal, $today->copy()->subDays(2)->toDateString(), false);

        Sanctum::actingAs($user);

        $this->getJson("/api/reports/backlog?goal_id={$firstGoal->id}")
            ->assertOk()
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.oldest_day', $today->copy()->subDay()->toDateString());

        $this->getJson('/api/reports/backlog')
            ->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_backlog_report_rejects_a_goal_of_another_user(): void
    {
        $user = User::factory()->create();
        $strangerGoal = $this->createGoal(User::factory()->create(), 'Stranger goal');

        Sanctum::actingAs($user);

        $this->getJson("/api/reports/backlog?goal_id={$strangerGoal->id}")
            ->assertForbidden();
    }

    public function test_backlog_report_is_empty_when_nothing_is_late(): void
    {
        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        $this->createTask($goal, Carbon::today()->toDateString(), false);

        Sanctum::actingAs($user);

        $this->getJson('/api/reports/backlog')
            ->assertOk()
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.oldest_day', null)
            ->assertJsonPath('data.days_behind', 0)
            ->assertJsonCount(0, 'data.tasks');
    }

    public function test_backlog_report_requires_authentication(): void
    {
        $this->getJson('/api/reports/backlog')
            ->assertUnauthorized();
    }

    public function test_goal_ranking_orders_goals_by_completion_rate(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $weak = $this->createGoal($user, 'Weak goal');
        $strong = $this->createGoal($user, 'Strong goal');

        // ۱ از ۲ برای هدف ضعیف
        $this->createTask($weak, $today->copy()->subDays(2)->toDateString(), true);
        $this->createTask($weak, $today->copy()->subDay()->toDateString(), false);

        // ۲ از ۲ برای هدف قوی
        $this->createTask($strong, $today->copy()->subDays(2)->toDateString(), true);
        $this->createTask($strong, $today->copy()->subDay()->toDateString(), true);

        Sanctum::actingAs($user);

        $from = $today->copy()->subDays(5)->toDateString();
        $to = $today->toDateString();

        $this->getJson("/api/reports/goal-ranking?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonCount(2, 'data.goals')
            ->assertJsonPath('data.goals.0.title', 'Strong goal')
            ->assertJsonPath('data.goals.0.percent', 100)
            ->assertJsonPath('data.goals.1.title', 'Weak goal')
            ->assertJsonPath('data.goals.1.done', 1)
            ->assertJsonPath('data.goals.1.total', 2)
            ->assertJsonPath('data.goals.1.percent', 50);
    }

    public function test_goal_ranking_leaves_out_goals_without_tasks_in_the_range(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $active = $this->createGoal($user, 'Active goal');
        $this->createGoal($user, 'Untouched goal');

        $this->createTask($active, $today->copy()->subDay()->toDateString(), true);

        Sanctum::actingAs($user);

        $from = $today->copy()->subDays(3)->toDateString();
        $to = $today->toDateString();

        $this->getJson("/api/reports/goal-ranking?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonCount(1, 'data.goals')
            ->assertJsonPath('data.goals.0.title', 'Active goal');
    }

    public function test_goal_ranking_ignores_future_days_and_other_users(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $goal = $this->createGoal($user, 'Goal');
        $strangerGoal = $this->createGoal(User::factory()->create(), 'Stranger goal');

        $this->createTask($goal, $today->toDateString(), true);
        // روز آینده نباید درصد را پایین بکشد
        $this->createTask($goal, $today->copy()->addDay()->toDateString(), false);
        $this->createTask($strangerGoal, $today->toDateString(), false);

        Sanctum::actingAs($user);

        $from = $today->copy()->subDays(3)->toDateString();
        $to = $today->copy()->addDays(10)->toDateString();

        $this->getJson("/api/reports/goal-ranking?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonCount(1, 'data.goals')
            ->assertJsonPath('data.to', $today->toDateString())
            ->assertJsonPath('data.goals.0.total', 1)
            ->assertJsonPath('data.goals.0.percent', 100);
    }

    public function test_goal_ranking_is_empty_for_a_range_fully_in_the_future(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $goal = $this->createGoal($user, 'Goal');
        $this->createTask($goal, $today->copy()->addDays(3)->toDateString(), false);

        Sanctum::actingAs($user);

        $from = $today->copy()->addDay()->toDateString();
        $to = $today->copy()->addDays(9)->toDateString();

        $this->getJson("/api/reports/goal-ranking?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonCount(0, 'data.goals');
    }

    public function test_goal_ranking_rejects_a_malformed_range(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/goal-ranking?from=1404-01-01')
            ->assertStatus(422);

        $this->getJson('/api/reports/goal-ranking?from=not-a-date&to=2026-01-01')
            ->assertStatus(422);

        $this->getJson('/api/reports/goal-ranking?from=2026-05-01&to=2026-04-01')
            ->assertStatus(422);
    }

    public function test_goal_ranking_requires_authentication(): void
    {
        $this->getJson('/api/reports/goal-ranking?from=2026-01-01&to=2026-02-01')
            ->assertUnauthorized();
    }

    public function test_range_activity_report_returns_every_day_of_the_range(): void
    {
        $today = Carbon::today();
        $from = $today->copy()->subDays(3);

        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        $this->createTask($goal, $today->copy()->subDays(3)->toDateString(), true);
        $this->createTask($goal, $today->copy()->subDay()->toDateString(), false);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/reports/activity?from={$from->toDateString()}&to={$today->toDateString()}"
        )->assertOk();

        // چهار روز، شامل روزهای بدون تسک
        $this->assertCount(4, $response->json('data.days'));

        $firstKey = Jalalian::fromCarbon($from)->format('Y-n-j');
        $emptyKey = Jalalian::fromCarbon($today->copy()->subDays(2))->format('Y-n-j');

        $response
            ->assertJsonPath('data.from', $from->toDateString())
            ->assertJsonPath('data.to', $today->toDateString())
            ->assertJsonPath("data.days.{$firstKey}.total", 1)
            ->assertJsonPath("data.days.{$firstKey}.done", 1)
            ->assertJsonPath("data.days.{$emptyKey}.total", 0)
            ->assertJsonPath('data.summary.total_tasks', 2)
            ->assertJsonPath('data.summary.done_tasks', 1)
            ->assertJsonPath('data.summary.perfect_days', 1)
            ->assertJsonPath('data.summary.inactive_days', 2)
            ->assertJsonPath('data.summary.average_percent', 50);
    }

    /**
     * دلیل اصلی ساختن این مسیر: بازه‌ای که سال شمسی را قطع می‌کند.
     */
    public function test_range_activity_report_crosses_the_jalali_new_year(): void
    {
        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        // ۱۴۰۴-۱۲-۲۹ و ۱۴۰۵-۰۱-۰۱
        $lastDayOfYear = Jalalian::fromFormat('Y-m-d', '1404-12-29')->toCarbon()->startOfDay();
        $firstDayOfYear = Jalalian::fromFormat('Y-m-d', '1405-01-01')->toCarbon()->startOfDay();

        $this->createTask($goal, $lastDayOfYear->toDateString(), true);
        $this->createTask($goal, $firstDayOfYear->toDateString(), true);

        Sanctum::actingAs($user);

        $response = $this->getJson(
            "/api/reports/activity?from={$lastDayOfYear->toDateString()}&to={$firstDayOfYear->toDateString()}"
        )->assertOk();

        $response
            ->assertJsonPath('data.days.1404-12-29.total', 1)
            ->assertJsonPath('data.days.1405-1-1.total', 1);

        $this->assertCount(2, $response->json('data.days'));
    }

    public function test_range_activity_report_keeps_future_days_out_of_the_summary(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        $this->createTask($goal, $today->toDateString(), true);
        $this->createTask($goal, $today->copy()->addDays(2)->toDateString(), false);

        Sanctum::actingAs($user);

        $from = $today->toDateString();
        $to = $today->copy()->addDays(4)->toDateString();

        $response = $this->getJson("/api/reports/activity?from={$from}&to={$to}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_tasks', 1)
            ->assertJsonPath('data.summary.average_percent', 100)
            ->assertJsonPath('data.summary.inactive_days', 0);

        // روز آینده در داده روزانه هست، فقط از خلاصه بیرون است
        $this->assertCount(5, $response->json('data.days'));
    }

    public function test_range_activity_report_scopes_to_the_user_and_the_goal_filter(): void
    {
        $today = Carbon::today()->toDateString();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $firstGoal = $this->createGoal($owner, 'First goal');
        $secondGoal = $this->createGoal($owner, 'Second goal');
        $strangerGoal = $this->createGoal($stranger, 'Stranger goal');

        $this->createTask($firstGoal, $today, true);
        $this->createTask($secondGoal, $today, false);
        $this->createTask($strangerGoal, $today, false);

        Sanctum::actingAs($owner);

        $this->getJson("/api/reports/activity?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_tasks', 2);

        $this->getJson("/api/reports/activity?from={$today}&to={$today}&goal_id={$firstGoal->id}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_tasks', 1);

        $this->getJson("/api/reports/activity?from={$today}&to={$today}&goal_id={$strangerGoal->id}")
            ->assertForbidden();
    }

    public function test_range_activity_report_rejects_a_malformed_or_oversized_range(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/activity?from=2026-01-01')
            ->assertStatus(422);

        $this->getJson('/api/reports/activity?from=not-a-date&to=2026-01-01')
            ->assertStatus(422);

        $this->getJson('/api/reports/activity?from=2026-05-01&to=2026-04-01')
            ->assertStatus(422);

        $start = Carbon::today();
        $tooFar = $start->copy()->addDays(ActivityReportService::MAX_RANGE_DAYS);

        $this->getJson("/api/reports/activity?from={$start->toDateString()}&to={$tooFar->toDateString()}")
            ->assertStatus(422);
    }

    public function test_range_activity_report_accepts_a_range_at_the_size_limit(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $start = Carbon::today();
        $end = $start->copy()->addDays(ActivityReportService::MAX_RANGE_DAYS - 1);

        $response = $this->getJson(
            "/api/reports/activity?from={$start->toDateString()}&to={$end->toDateString()}"
        )->assertOk();

        $this->assertCount(ActivityReportService::MAX_RANGE_DAYS, $response->json('data.days'));
    }

    public function test_range_activity_report_requires_authentication(): void
    {
        $this->getJson('/api/reports/activity?from=2026-01-01&to=2026-02-01')
            ->assertUnauthorized();
    }

    /**
     * ستون day از نوع DATE است. اگر مرزهای پرس‌وجو با زمان بسته شوند،
     * تسک روی روز اول بازه گم می‌شود. این آزمون همان مرز را قفل می‌کند.
     */
    public function test_reports_include_a_task_sitting_exactly_on_the_first_day_of_the_range(): void
    {
        $today = Carbon::today();
        $from = $today->copy()->subDays(6);

        $user = User::factory()->create();
        $goal = $this->createGoal($user, 'Edge goal');

        $this->createTask($goal, $from->toDateString(), true);

        Sanctum::actingAs($user);

        $firstKey = Jalalian::fromCarbon($from)->format('Y-n-j');

        $this->getJson("/api/reports/activity?from={$from->toDateString()}&to={$today->toDateString()}")
            ->assertOk()
            ->assertJsonPath("data.days.{$firstKey}.total", 1)
            ->assertJsonPath('data.summary.total_tasks', 1);

        $this->getJson("/api/reports/goal-ranking?from={$from->toDateString()}&to={$today->toDateString()}")
            ->assertOk()
            ->assertJsonCount(1, 'data.goals')
            ->assertJsonPath('data.goals.0.total', 1);
    }

    /**
     * روز اول فروردین هم مرز شروع بازه سالانه است و نباید گم شود.
     */
    public function test_yearly_report_includes_the_first_day_of_the_jalali_year(): void
    {
        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        $firstDay = Jalalian::fromFormat('Y-m-d', '1404-01-01')->toCarbon()->startOfDay();

        $this->createTask($goal, $firstDay->toDateString(), true);

        Sanctum::actingAs($user);

        $this->getJson('/api/activities/1404')
            ->assertOk()
            ->assertJsonPath('data.1404-1-1.total', 1)
            ->assertJsonPath('data.1404-1-1.done', 1);
    }

    public function test_goal_activity_returns_a_dense_window_for_every_goal_with_tasks(): void
    {
        $today = Carbon::today();

        $user = User::factory()->create();
        $goal = $this->createGoal($user, 'Strip goal');

        $this->createTask($goal, $today->toDateString(), true);
        $this->createTask($goal, $today->copy()->subDays(29)->toDateString(), false);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/goal-activity')->assertOk();

        $this->assertCount(30, $response->json('data.days'));

        $response
            ->assertJsonPath('data.from', $today->copy()->subDays(29)->toDateString())
            ->assertJsonPath('data.to', $today->toDateString())
            ->assertJsonPath('data.days.0', $today->copy()->subDays(29)->toDateString())
            ->assertJsonPath('data.days.29', $today->toDateString())
            // اولین و آخرین روز پنجره، و یک روز خالی وسط آن
            ->assertJsonPath("data.goals.{$goal->id}.total.0", 1)
            ->assertJsonPath("data.goals.{$goal->id}.done.0", 0)
            ->assertJsonPath("data.goals.{$goal->id}.total.29", 1)
            ->assertJsonPath("data.goals.{$goal->id}.done.29", 1)
            ->assertJsonPath("data.goals.{$goal->id}.total.15", 0);

        $this->assertCount(30, $response->json("data.goals.{$goal->id}.total"));
        $this->assertCount(30, $response->json("data.goals.{$goal->id}.done"));
    }

    /**
     * هدف بدون تسک در پنجره اصلاً فرستاده نمی‌شود و سمت کاربر خالی کشیده می‌شود.
     */
    public function test_goal_activity_leaves_out_empty_goals_and_other_users(): void
    {
        $today = Carbon::today()->toDateString();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $active = $this->createGoal($owner, 'Active goal');
        $idle = $this->createGoal($owner, 'Idle goal');
        $strangerGoal = $this->createGoal($stranger, 'Stranger goal');

        $this->createTask($active, $today, true);
        $this->createTask($strangerGoal, $today, true);
        // تسک قدیمی‌تر از پنجره، پس هدفش خالی حساب می‌شود
        $this->createTask($idle, Carbon::today()->subDays(60)->toDateString(), true);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/reports/goal-activity')->assertOk();

        $this->assertSame([$active->id], array_keys($response->json('data.goals')));
    }

    public function test_goal_activity_accepts_a_custom_window_and_rejects_an_invalid_one(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/goal-activity?days=7')->assertOk();
        $this->assertCount(7, $response->json('data.days'));

        $this->getJson('/api/reports/goal-activity?days=1')->assertStatus(422);
        $this->getJson('/api/reports/goal-activity?days=365')->assertStatus(422);
        $this->getJson('/api/reports/goal-activity?days=abc')->assertStatus(422);
    }

    /**
     * کاربری که هیچ هدفی ندارد هم باید پاسخ درست بگیرد، نه خطا.
     */
    public function test_goal_activity_is_empty_for_a_user_without_goals(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/goal-activity')
            ->assertOk()
            ->assertJsonPath('data.goals', []);
    }

    public function test_goal_activity_requires_authentication(): void
    {
        $this->getJson('/api/reports/goal-activity')->assertUnauthorized();
    }

    /**
     * روز شمسی را به تاریخ میلادی همان روز تبدیل می‌کند.
     */
    private function jalaliDay(string $day): string
    {
        return Jalalian::fromFormat('Y-m-d', $day)->toCarbon()->toDateString();
    }

    /**
     * ۱۴۰۴ سالی است که کاملاً گذشته، پس هیچ روزش به‌عنوان آینده کنار نمی‌رود.
     */
    public function test_year_review_summarizes_a_whole_jalali_year(): void
    {
        $user = User::factory()->create();

        // هر هدف در هر روز حداکثر یک تسک دارد، پس روز پرکار یعنی روزی که
        // چند هدف با هم برنامه داشته‌اند.
        $sport = $this->createGoal($user, 'Sport');
        $study = $this->createGoal($user, 'Study');
        $read = $this->createGoal($user, 'Read');

        $this->createTask($sport, $this->jalaliDay('1404-01-01'), true);
        $this->createTask($study, $this->jalaliDay('1404-01-01'), true);
        $this->createTask($sport, $this->jalaliDay('1404-01-02'), true);
        // پرکارترین روز، و همان روزی که زنجیره را می‌شکند
        $this->createTask($sport, $this->jalaliDay('1404-01-04'), true);
        $this->createTask($study, $this->jalaliDay('1404-01-04'), false);
        $this->createTask($read, $this->jalaliDay('1404-01-04'), false);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reports/year-review?year=1404')->assertOk();

        $response
            ->assertJsonPath('data.year', 1404)
            ->assertJsonPath('data.summary.total_tasks', 6)
            ->assertJsonPath('data.summary.done_tasks', 4)
            ->assertJsonPath('data.summary.average_percent', 66.7)
            ->assertJsonPath('data.summary.perfect_days', 2)
            ->assertJsonPath('data.summary.active_days', 3)
            ->assertJsonPath('data.summary.recorded_days', 365)
            ->assertJsonPath('data.first_day', '1404-1-1')
            ->assertJsonPath('data.busiest_day.day', '1404-1-4')
            ->assertJsonPath('data.busiest_day.total', 3)
            ->assertJsonPath('data.busiest_day.done', 1)
            ->assertJsonPath('data.streak.length', 2)
            ->assertJsonPath('data.streak.start', '1404-1-1')
            ->assertJsonPath('data.streak.end', '1404-1-2');

        // فروردین همه تسک‌ها را دارد و بقیه ماه‌ها خالی‌اند
        $this->assertCount(12, $response->json('data.months'));
        $response
            ->assertJsonPath('data.months.0.month', 1)
            ->assertJsonPath('data.months.0.total', 6)
            ->assertJsonPath('data.months.0.percent', 66.7)
            ->assertJsonPath('data.months.1.total', 0)
            ->assertJsonPath('data.months.1.percent', 0);

        // اول فروردین ۱۴۰۴ جمعه بوده، دوم شنبه و چهارم دوشنبه
        $this->assertCount(7, $response->json('data.weekdays'));
        $response
            ->assertJsonPath('data.weekdays.6.total', 2)
            ->assertJsonPath('data.weekdays.6.percent', 100)
            ->assertJsonPath('data.weekdays.0.total', 1)
            ->assertJsonPath('data.weekdays.2.total', 3)
            ->assertJsonPath('data.weekdays.1.total', 0);

        // ترتیب اهداف از بیشترین بازدهی
        $response
            ->assertJsonCount(3, 'data.top_goals')
            ->assertJsonPath('data.top_goals.0.title', 'Sport')
            ->assertJsonPath('data.top_goals.0.percent', 100)
            ->assertJsonPath('data.top_goals.1.title', 'Study')
            ->assertJsonPath('data.top_goals.1.percent', 50)
            ->assertJsonPath('data.top_goals.2.title', 'Read')
            ->assertJsonPath('data.top_goals.2.percent', 0);
    }

    /**
     * روز بدون تسک زنجیره را نمی‌شکند، ولی به آن هم اضافه نمی‌کند.
     */
    public function test_year_review_streak_survives_a_day_without_tasks(): void
    {
        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        $this->createTask($goal, $this->jalaliDay('1404-01-01'), true);
        $this->createTask($goal, $this->jalaliDay('1404-01-02'), true);
        // سوم فروردین اصلاً تسکی ندارد
        $this->createTask($goal, $this->jalaliDay('1404-01-04'), true);

        Sanctum::actingAs($user);

        $this->getJson('/api/reports/year-review?year=1404')
            ->assertOk()
            ->assertJsonPath('data.streak.length', 3)
            ->assertJsonPath('data.streak.start', '1404-1-1')
            ->assertJsonPath('data.streak.end', '1404-1-4');
    }

    public function test_year_review_leaves_out_future_days_and_other_users(): void
    {
        $today = Carbon::today();
        $year = (int) Jalalian::fromCarbon($today)->getYear();

        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $ownerGoal = $this->createGoal($owner, 'Owner goal');
        $strangerGoal = $this->createGoal($stranger, 'Stranger goal');

        $this->createTask($ownerGoal, $today->toDateString(), true);
        $this->createTask($ownerGoal, $today->copy()->addDays(3)->toDateString(), false);
        $this->createTask($strangerGoal, $today->toDateString(), false);

        Sanctum::actingAs($owner);

        $this->getJson("/api/reports/year-review?year={$year}")
            ->assertOk()
            ->assertJsonPath('data.summary.total_tasks', 1)
            ->assertJsonPath('data.summary.done_tasks', 1)
            ->assertJsonPath('data.summary.active_days', 1)
            ->assertJsonCount(1, 'data.top_goals')
            ->assertJsonPath('data.top_goals.0.total', 1);
    }

    /**
     * مقایسه فقط وقتی معنا دارد که سال قبل واقعاً تسکی داشته باشد.
     */
    public function test_year_review_compares_with_the_previous_year_when_it_has_data(): void
    {
        $user = User::factory()->create();
        $goal = $this->createGoal($user);

        $this->createTask($goal, $this->jalaliDay('1404-01-01'), true);

        Sanctum::actingAs($user);

        $this->getJson('/api/reports/year-review?year=1404')
            ->assertOk()
            ->assertJsonPath('data.previous_year', null);

        $this->createTask($goal, $this->jalaliDay('1403-05-10'), true);
        $this->createTask($goal, $this->jalaliDay('1403-05-11'), false);

        $this->getJson('/api/reports/year-review?year=1404')
            ->assertOk()
            ->assertJsonPath('data.previous_year.year', 1403)
            ->assertJsonPath('data.previous_year.total_tasks', 2)
            ->assertJsonPath('data.previous_year.average_percent', 50);
    }

    public function test_year_review_defaults_to_the_current_year_and_rejects_an_invalid_one(): void
    {
        $year = (int) Jalalian::fromCarbon(Carbon::today())->getYear();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/reports/year-review')
            ->assertOk()
            ->assertJsonPath('data.year', $year)
            ->assertJsonPath('data.summary.total_tasks', 0)
            ->assertJsonPath('data.streak.length', 0)
            ->assertJsonPath('data.first_day', null)
            ->assertJsonPath('data.busiest_day', null)
            ->assertJsonPath('data.top_goals', []);

        $this->getJson('/api/reports/year-review?year=1200')->assertStatus(422);
        $this->getJson('/api/reports/year-review?year=abc')->assertStatus(422);
    }

    public function test_year_review_requires_authentication(): void
    {
        $this->getJson('/api/reports/year-review')->assertUnauthorized();
    }
}
