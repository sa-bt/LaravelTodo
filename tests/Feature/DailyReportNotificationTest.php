<?php

namespace Tests\Feature;

use App\Jobs\SendDailyReportNotificationJob;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ReportNotification;
use App\Services\ActivityReportService;
use App\Services\ReportMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DailyReportNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function createGoal(User $user): Goal
    {
        return Goal::query()->create([
            'user_id' => $user->id,
            'title' => 'Goal',
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

    private function sendReport(User $user): void
    {
        (new SendDailyReportNotificationJob($user->id))->handle(
            app(ReportMessageService::class),
            app(ActivityReportService::class)
        );
    }

    public function test_daily_report_body_uses_persian_digits_only(): void
    {
        Notification::fake();

        $user = User::factory()->create(['daily_report' => true]);
        $goal = $this->createGoal($user);

        $today = Carbon::today()->toDateString();

        // هر هدف در هر روز فقط یک تسک می‌گیرد، پس تسک دوم هدف دوم می‌خواهد.
        $this->createTask($goal, $today, true);
        $this->createTask($this->createGoal($user), $today, false);

        $this->sendReport($user);

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $notification->body);
            $this->assertStringContainsString('۱ از ۲', $notification->body);
            $this->assertSame('daily_report', $notification->type);

            return true;
        });
    }

    public function test_daily_report_compares_with_yesterday_when_yesterday_had_tasks(): void
    {
        Notification::fake();

        $user = User::factory()->create(['daily_report' => true]);
        $goal = $this->createGoal($user);

        $this->createTask($goal, Carbon::today()->toDateString(), true);
        $this->createTask($goal, Carbon::yesterday()->toDateString(), false);

        $this->sendReport($user);

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertSame(0, $notification->meta['yesterday_percent']);
            $this->assertStringContainsString('نسبت به دیروز', $notification->body);

            return true;
        });
    }

    /**
     * روزی که برایش برنامه‌ای نبوده نباید سقوط نشان داده شود.
     */
    public function test_daily_report_leaves_yesterday_out_when_it_had_no_tasks(): void
    {
        Notification::fake();

        $user = User::factory()->create(['daily_report' => true]);
        $goal = $this->createGoal($user);

        $this->createTask($goal, Carbon::today()->toDateString(), true);

        $this->sendReport($user);

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertNull($notification->meta['yesterday_percent']);
            $this->assertStringNotContainsString('نسبت به دیروز', $notification->body);

            return true;
        });
    }

    public function test_daily_report_celebrates_two_complete_days_in_a_row(): void
    {
        Notification::fake();

        $user = User::factory()->create(['daily_report' => true]);
        $goal = $this->createGoal($user);

        $this->createTask($goal, Carbon::today()->toDateString(), true);
        $this->createTask($goal, Carbon::yesterday()->toDateString(), true);

        $this->sendReport($user);

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertStringContainsString('دیروز هم کامل بود', $notification->body);
            $this->assertStringNotContainsString('تقریباً مثل دیروز', $notification->body);

            return true;
        });
    }

    public function test_daily_report_mentions_backlog_when_something_is_late(): void
    {
        Notification::fake();

        $user = User::factory()->create(['daily_report' => true]);
        $goal = $this->createGoal($user);

        $this->createTask($goal, Carbon::today()->toDateString(), true);
        $this->createTask($goal, Carbon::today()->subDays(4)->toDateString(), false);
        $this->createTask($goal, Carbon::today()->subDays(9)->toDateString(), false);

        $this->sendReport($user);

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertSame(2, $notification->meta['backlog']);
            $this->assertStringContainsString('۲ کار عقب‌افتاده', $notification->body);

            return true;
        });
    }

    public function test_daily_report_is_skipped_without_tasks_or_when_disabled(): void
    {
        Notification::fake();

        $empty = User::factory()->create(['daily_report' => true]);
        $this->createGoal($empty);

        $this->sendReport($empty);

        $disabled = User::factory()->create(['daily_report' => false]);
        $goal = $this->createGoal($disabled);
        $this->createTask($goal, Carbon::today()->toDateString(), true);

        $this->sendReport($disabled);

        Notification::assertNothingSent();
    }
}
