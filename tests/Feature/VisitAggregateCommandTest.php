<?php

namespace Tests\Feature;

use App\Models\PageView;
use App\Models\User;
use App\Models\VisitDailyStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitAggregateCommandTest extends TestCase
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

    public function test_a_day_is_rolled_into_a_single_row(): void
    {
        $member = User::factory()->create();

        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:05:00', ['path' => '/vorod']);
        $this->pageView('visitor-b', 'session-2', '2026-07-30 14:00:00', [
            'is_guest' => false,
            'user_id' => $member->id,
        ]);
        $this->pageView('crawler', 'session-bot', '2026-07-30 15:00:00', ['is_bot' => true]);

        $this->artisan('visits:aggregate', ['--date' => '2026-07-30'])
            ->assertSuccessful();

        $stat = VisitDailyStat::query()->sole();

        $this->assertSame('2026-07-30', $stat->date->toDateString());
        $this->assertSame(3, $stat->views);
        $this->assertSame(2, $stat->unique_visitors);
        $this->assertSame(2, $stat->sessions);
        $this->assertSame(1, $stat->bounced_sessions);
        $this->assertSame(2, $stat->guest_views);
        $this->assertSame(1, $stat->member_views);
        $this->assertSame(1, $stat->active_members);
        $this->assertSame(2, $stat->new_visitors);
    }

    public function test_the_hourly_curve_has_twenty_four_numbers(): void
    {
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-30 09:30:00');

        $this->artisan('visits:aggregate', ['--date' => '2026-07-30'])->assertSuccessful();

        $hourly = VisitDailyStat::query()->sole()->hourly;

        $this->assertCount(24, $hourly);
        $this->assertSame(2, $hourly[9]);
        $this->assertSame(0, $hourly[10]);
    }

    public function test_running_the_aggregation_twice_does_not_duplicate_or_double_count(): void
    {
        $this->pageView('visitor-a', 'session-1', '2026-07-30 09:00:00');

        $this->artisan('visits:aggregate', ['--date' => '2026-07-30'])->assertSuccessful();
        $this->artisan('visits:aggregate', ['--date' => '2026-07-30'])->assertSuccessful();

        $this->assertSame(1, VisitDailyStat::query()->count());
        $this->assertSame(1, VisitDailyStat::query()->sole()->views);
    }

    public function test_without_a_date_the_recent_days_are_rebuilt_but_never_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-30 08:00:00'));

        $this->pageView('visitor-a', 'session-1', '2026-07-29 09:00:00');
        $this->pageView('visitor-b', 'session-2', '2026-07-30 07:00:00');

        $this->artisan('visits:aggregate', ['--days' => 3])->assertSuccessful();

        $dates = VisitDailyStat::query()->orderBy('date')->pluck('date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $this->assertSame(['2026-07-27', '2026-07-28', '2026-07-29'], $dates);

        Carbon::setTestNow();
    }

    public function test_pruning_removes_old_raw_views_and_keeps_recent_ones(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-30 08:00:00'));

        $this->pageView('visitor-old', 'session-old', '2025-12-01 09:00:00');
        $this->pageView('visitor-new', 'session-new', '2026-07-29 09:00:00');

        $this->artisan('visits:prune', ['--days' => 180])->assertSuccessful();

        $this->assertSame(1, PageView::query()->count());
        $this->assertSame('visitor-new', PageView::query()->sole()->visitor_id);

        Carbon::setTestNow();
    }
}
