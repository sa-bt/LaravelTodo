<?php

namespace Tests\Feature;

use App\Console\Commands\SendWeeklyReportsDue;
use App\Jobs\SendWeeklyReportNotificationJob;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ReportNotification;
use App\Services\ReportMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WeeklyReportNotificationTest extends TestCase
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

    /**
     * کاربری با هفته جاری بهتر از هفته قبل.
     */
    private function createUserWithTwoWeeks(): User
    {
        $user = User::factory()->create(['weekly_report' => true]);
        $goal = $this->createGoal($user);

        $today = Carbon::today();

        // هفته جاری: چهار از چهار
        for ($i = 0; $i < 4; $i++) {
            $this->createTask($goal, $today->copy()->subDays($i)->toDateString(), true);
        }

        // هفته قبل: یک از چهار
        for ($i = 7; $i < 11; $i++) {
            $this->createTask($goal, $today->copy()->subDays($i)->toDateString(), $i === 7);
        }

        return $user;
    }

    public function test_weekly_report_compares_this_week_with_the_previous_one(): void
    {
        Notification::fake();

        $user = $this->createUserWithTwoWeeks();

        (new SendWeeklyReportNotificationJob($user->id))->handle(
            app(ReportMessageService::class),
            app(\App\Services\ActivityReportService::class)
        );

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertSame('weekly_report', $notification->type);
            $this->assertSame(100, $notification->meta['percent']);
            $this->assertSame(25, $notification->meta['previous_percent']);
            $this->assertStringContainsString('بهتر شدی', $notification->body);
            // هیچ رقم لاتینی نباید در متن اعلان بماند
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $notification->body);

            return true;
        });
    }

    public function test_weekly_report_is_skipped_when_both_weeks_are_empty(): void
    {
        Notification::fake();

        $user = User::factory()->create(['weekly_report' => true]);
        $this->createGoal($user);

        (new SendWeeklyReportNotificationJob($user->id))->handle(
            app(ReportMessageService::class),
            app(\App\Services\ActivityReportService::class)
        );

        Notification::assertNothingSent();
    }

    /**
     * هفته خالی بعد از هفته‌ای پرکار، یادآوری است نه سکوت.
     */
    public function test_weekly_report_is_sent_when_only_the_previous_week_had_tasks(): void
    {
        Notification::fake();

        $user = User::factory()->create(['weekly_report' => true]);
        $goal = $this->createGoal($user);

        $this->createTask($goal, Carbon::today()->subDays(9)->toDateString(), true);

        (new SendWeeklyReportNotificationJob($user->id))->handle(
            app(ReportMessageService::class),
            app(\App\Services\ActivityReportService::class)
        );

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertSame(0, $notification->meta['total']);
            $this->assertStringContainsString('برنامه‌ریزی نکردی', $notification->body);

            return true;
        });
    }

    public function test_weekly_report_is_skipped_when_disabled_or_already_sent(): void
    {
        Notification::fake();

        $disabled = User::factory()->create(['weekly_report' => false]);
        $goal = $this->createGoal($disabled);
        $this->createTask($goal, Carbon::today()->toDateString(), true);

        (new SendWeeklyReportNotificationJob($disabled->id))->handle(
            app(ReportMessageService::class),
            app(\App\Services\ActivityReportService::class)
        );

        Notification::assertNothingSent();

        $user = $this->createUserWithTwoWeeks();
        Cache::put(SendWeeklyReportNotificationJob::dedupKey($user->id, Carbon::today()->toDateString()), true, 60);

        (new SendWeeklyReportNotificationJob($user->id))->handle(
            app(ReportMessageService::class),
            app(\App\Services\ActivityReportService::class)
        );

        Notification::assertNothingSent();
    }

    public function test_command_dispatches_only_for_users_due_on_this_weekday_and_time(): void
    {
        Bus::fake();

        $now = Carbon::now(config('app.timezone', 'UTC'));
        $weekday = SendWeeklyReportsDue::jalaliWeekday($now);

        $due = User::factory()->create([
            'weekly_report' => true,
            'weekly_report_day' => $weekday,
            'weekly_report_time' => $now->format('H:i:s'),
        ]);

        $wrongDay = User::factory()->create([
            'weekly_report' => true,
            'weekly_report_day' => ($weekday + 3) % 7,
            'weekly_report_time' => $now->format('H:i:s'),
        ]);

        $wrongTime = User::factory()->create([
            'weekly_report' => true,
            'weekly_report_day' => $weekday,
            'weekly_report_time' => $now->copy()->addHours(3)->format('H:i:s'),
        ]);

        $disabled = User::factory()->create([
            'weekly_report' => false,
            'weekly_report_day' => $weekday,
            'weekly_report_time' => $now->format('H:i:s'),
        ]);

        $this->artisan('reports:send-weekly-due')->assertSuccessful();

        Bus::assertDispatched(SendWeeklyReportNotificationJob::class, fn ($job) => $job->userId === $due->id);

        foreach ([$wrongDay, $wrongTime, $disabled] as $user) {
            Bus::assertNotDispatched(SendWeeklyReportNotificationJob::class, fn ($job) => $job->userId === $user->id);
        }
    }

    /**
     * شنبه صفر است و جمعه شش، همان شماره‌گذاری نمودار روزهای هفته.
     */
    public function test_jalali_weekday_numbering_starts_at_saturday(): void
    {
        $this->assertSame(0, SendWeeklyReportsDue::jalaliWeekday(Carbon::parse('2026-07-25'))); // شنبه
        $this->assertSame(6, SendWeeklyReportsDue::jalaliWeekday(Carbon::parse('2026-07-31'))); // جمعه
    }

    public function test_settings_endpoint_stores_the_weekly_report_fields(): void
    {
        $user = User::factory()->create();

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->postJson('/api/user-setting', [
            'daily_report' => true,
            'report_time' => '08:00',
            'task_reminder' => false,
            'task_reminder_time' => '09:00',
            'per_task_progress' => false,
            'weekly_report' => true,
            'weekly_report_day' => 6,
            'weekly_report_time' => '20:30',
        ])->assertOk();

        $this->getJson('/api/user-setting')
            ->assertOk()
            ->assertJsonPath('weekly_report', true)
            ->assertJsonPath('weekly_report_day', 6)
            ->assertJsonPath('weekly_report_time', '20:30');
    }

    /**
     * قبلاً کلیدی پاک می‌شد که هیچ‌جا نوشته نمی‌شد، پس روشن کردن گزارش بعد از
     * ساعتش هیچ اثری نداشت.
     */
    public function test_saving_settings_clears_the_keys_the_jobs_actually_write(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today()->toDateString();

        Cache::put(\App\Jobs\SendDailyReportNotificationJob::dedupKey($user->id, $today), true, 600);
        Cache::put(SendWeeklyReportNotificationJob::dedupKey($user->id, $today), true, 600);

        \Laravel\Sanctum\Sanctum::actingAs($user);

        $this->postJson('/api/user-setting', [
            'daily_report' => true,
            'report_time' => '08:00',
            'task_reminder' => false,
            'task_reminder_time' => '09:00',
            'per_task_progress' => false,
        ])->assertOk();

        $this->assertFalse(Cache::has(\App\Jobs\SendDailyReportNotificationJob::dedupKey($user->id, $today)));
        $this->assertFalse(Cache::has(SendWeeklyReportNotificationJob::dedupKey($user->id, $today)));
    }
}
