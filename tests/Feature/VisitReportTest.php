<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use App\Services\VisitReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VisitReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pageView(string $visitor, string $session, string $at, array $attributes = []): PageView
    {
        return PageView::query()->create(array_merge([
            'visitor_id' => $visitor,
            'session_id' => $session,
            'path' => '/',
            'is_guest' => true,
            'referrer_group' => PageView::REFERRER_DIRECT,
            'device_type' => PageView::DEVICE_DESKTOP,
            'browser' => 'chrome',
            'platform' => 'windows',
            'is_bot' => false,
            'created_at' => Carbon::parse($at),
        ], $attributes));
    }

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_a_regular_user_cannot_read_visit_reports(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'user']));

        $this->getJson('/api/admin/analytics/daily')->assertForbidden();
        $this->getJson('/api/admin/analytics/weekly')->assertForbidden();
    }

    public function test_visit_reports_require_a_session(): void
    {
        $this->getJson('/api/admin/analytics/daily')->assertUnauthorized();
    }

    public function test_the_daily_report_counts_views_visitors_and_sessions(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:05:00', ['path' => '/vorod']);
        $this->pageView('visitor-b', 'session-2', '2026-07-30 14:00:00');

        // Another day, so it must not leak into the numbers below.
        $this->pageView('visitor-c', 'session-3', '2026-07-29 10:00:00');

        $response = $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('status', true);

        $response
            ->assertJsonPath('data.totals.views', 3)
            ->assertJsonPath('data.totals.unique_visitors', 2)
            ->assertJsonPath('data.totals.sessions', 2)
            ->assertJsonPath('data.period.from', '2026-07-30')
            ->assertJsonPath('data.period.to', '2026-07-30');
    }

    public function test_robot_traffic_never_reaches_the_report(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('crawler', 'session-bot', '2026-07-30 09:30:00', ['is_bot' => true]);

        $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.totals.views', 1)
            ->assertJsonPath('data.totals.unique_visitors', 1);
    }

    public function test_the_bounce_rate_counts_single_page_sessions(): void
    {
        $this->actingAsAdmin();

        // One session that moved on, one that stopped at the first page.
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:02:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-30 10:00:00');

        $response = $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.totals.bounced_sessions', 1);

        // Compared numerically: a whole rate arrives as 50 or 50.0 depending on
        // how the encoder felt about the trailing zero.
        $this->assertEqualsWithDelta(50.0, $response->json('data.totals.bounce_rate'), 0.001);
    }

    public function test_a_day_without_sessions_reports_no_bounce_rate_rather_than_zero(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.totals.views', 0)
            ->assertJsonPath('data.totals.bounce_rate', null);
    }

    public function test_guest_and_member_views_are_reported_apart(): void
    {
        $this->actingAsAdmin();
        $member = User::factory()->create();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-30 10:00:00', [
            'is_guest' => false,
            'user_id' => $member->id,
        ]);

        $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.totals.guest_views', 1)
            ->assertJsonPath('data.totals.member_views', 1)
            ->assertJsonPath('data.totals.active_members', 1);
    }

    public function test_the_previous_period_of_a_day_is_the_day_before(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-30 10:00:00');
        $this->pageView('visitor-c', 'session-3', '2026-07-29 10:00:00');

        $response = $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.previous.views', 1);

        $this->assertEqualsWithDelta(100.0, $response->json('data.change.views'), 0.001);
    }

    public function test_growth_from_nothing_is_reported_as_unknown_rather_than_a_percentage(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');

        $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.previous.views', 0)
            ->assertJsonPath('data.change.views', null);
    }

    public function test_the_daily_series_has_one_point_per_hour(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-30 09:40:00');

        $response = $this->getJson('/api/admin/analytics/daily?date=2026-07-30')->assertOk();

        $response->assertJsonPath('data.series.unit', 'hour');
        $this->assertCount(24, $response->json('data.series.points'));
        $this->assertSame(2, $response->json('data.series.points.9.views'));
        $this->assertSame(0, $response->json('data.series.points.10.views'));
    }

    public function test_the_weekly_report_runs_from_saturday_to_friday(): void
    {
        $this->actingAsAdmin();

        // 2026-07-30 is a Thursday, so its jalali week starts Saturday the 25th.
        $response = $this->getJson('/api/admin/analytics/weekly?date=2026-07-30')->assertOk();

        $response
            ->assertJsonPath('data.period.from', '2026-07-25')
            ->assertJsonPath('data.period.to', '2026-07-31')
            ->assertJsonPath('data.period.days', 7)
            ->assertJsonPath('data.series.unit', 'day');

        $this->assertCount(7, $response->json('data.series.points'));
    }

    public function test_the_weekly_report_is_compared_to_the_week_before(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-27 09:00:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-28 09:00:00');

        // Previous week: Saturday 18th through Friday 24th.
        $this->pageView('visitor-c', 'session-3', '2026-07-20 09:00:00');

        $this->getJson('/api/admin/analytics/weekly?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.totals.views', 2)
            ->assertJsonPath('data.previous.views', 1);
    }

    public function test_top_pages_are_ordered_by_views(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00', ['path' => '/']);
        $this->pageView('visitor-b', 'session-2', '2026-07-30 09:10:00', ['path' => '/']);
        $this->pageView('visitor-c', 'session-3', '2026-07-30 09:20:00', ['path' => '/vorod']);

        $response = $this->getJson('/api/admin/analytics/daily?date=2026-07-30')->assertOk();

        $response
            ->assertJsonPath('data.top_paths.0.path', '/')
            ->assertJsonPath('data.top_paths.0.views', 2)
            ->assertJsonPath('data.top_paths.0.unique_visitors', 2)
            ->assertJsonPath('data.top_paths.1.path', '/vorod');
    }

    public function test_new_visitors_exclude_anyone_seen_before_the_window(): void
    {
        $this->actingAsAdmin();

        // Returning: first seen a week earlier.
        $this->pageView('visitor-a', 'session-0', '2026-07-23 09:00:00');
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');

        // First time ever.
        $this->pageView('visitor-b', 'session-2', '2026-07-30 10:00:00');

        $this->getJson('/api/admin/analytics/daily?date=2026-07-30')
            ->assertOk()
            ->assertJsonPath('data.totals.unique_visitors', 2)
            ->assertJsonPath('data.totals.new_visitors', 1);
    }

    public function test_referrers_are_grouped_and_internal_traffic_is_left_out_of_the_named_list(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00', [
            'referrer_host' => 'google.com',
            'referrer_group' => PageView::REFERRER_SEARCH,
        ]);
        $this->pageView('visitor-b', 'session-2', '2026-07-30 09:10:00', [
            'referrer_host' => 'todo.local',
            'referrer_group' => PageView::REFERRER_INTERNAL,
        ]);

        $response = $this->getJson('/api/admin/analytics/daily?date=2026-07-30')->assertOk();

        $this->assertCount(1, $response->json('data.top_referrers'));
        $this->assertSame('google.com', $response->json('data.top_referrers.0.host'));
        $this->assertCount(2, $response->json('data.referrer_groups'));
    }

    public function test_a_free_range_is_rejected_when_it_is_backwards_or_too_long(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/admin/analytics/overview?from=2026-07-30&to=2026-07-01')
            ->assertStatus(422);

        $tooLong = Carbon::parse('2026-07-30')
            ->addDays(VisitReportService::MAX_RANGE_DAYS)
            ->toDateString();

        $this->getJson("/api/admin/analytics/overview?from=2026-07-30&to={$tooLong}")
            ->assertStatus(422);
    }

    public function test_a_free_range_reports_one_point_per_day_and_no_trend(): void
    {
        $this->actingAsAdmin();

        $this->pageView('visitor-a', 'session-1', '2026-07-28 09:00:00');

        $response = $this->getJson('/api/admin/analytics/overview?from=2026-07-27&to=2026-07-30')
            ->assertOk();

        $response
            ->assertJsonPath('data.period.days', 4)
            ->assertJsonPath('data.trend', null);

        $this->assertCount(4, $response->json('data.series.points'));
    }
}
