<?php

namespace Tests\Feature;

use App\Jobs\SendDropAlertNotificationJob;
use App\Jobs\SendWeeklyReportNotificationJob;
use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use App\Notifications\ReportNotification;
use App\Services\ActivityReportService;
use App\Services\ReportMessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DropAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, Goal>
     */
    private function createGoals(User $user, int $count = 1): array
    {
        return collect(range(1, $count))
            ->map(fn ($index) => Goal::query()->create([
                'user_id' => $user->id,
                'title' => "Goal {$index}",
            ]))
            ->all();
    }

    /**
     * پر کردن یک هفته با تعداد مشخصی تسک، که بخشی از آن‌ها انجام شده‌اند.
     *
     * هفته صفر هفته جاری است. چون هر هدف در هر روز حداکثر یک تسک دارد، برای
     * هفته پرتسک باید چند هدف داشت.
     *
     * @param  array<int, Goal>  $goals
     */
    private function fillWeek(array $goals, int $weekIndex, int $total, int $done): void
    {
        $today = Carbon::today();
        $created = 0;

        foreach ($goals as $goal) {
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                if ($created >= $total) {
                    return;
                }

                Task::query()->create([
                    'goal_id' => $goal->id,
                    'title' => 'Task',
                    'day' => $today->copy()->subDays($weekIndex * 7 + $dayOffset)->toDateString(),
                    'is_done' => $created < $done,
                ]);

                $created++;
            }
        }
    }

    private function runJob(User $user): void
    {
        (new SendDropAlertNotificationJob($user->id))->handle(
            app(ReportMessageService::class),
            app(ActivityReportService::class)
        );
    }

    /**
     * سه هفته: صد، پنجاه، بیست و پنج درصد.
     */
    private function createSlidingUser(): User
    {
        $user = User::factory()->create(['drop_alert' => true]);
        $goals = $this->createGoals($user);

        $this->fillWeek($goals, 2, 4, 4);
        $this->fillWeek($goals, 1, 4, 2);
        $this->fillWeek($goals, 0, 4, 1);

        return $user;
    }

    public function test_drop_alert_is_sent_after_two_consecutive_declines(): void
    {
        Notification::fake();

        $user = $this->createSlidingUser();

        $this->runJob($user);

        Notification::assertSentTo($user, ReportNotification::class, function ($notification) {
            $this->assertSame('drop_alert', $notification->type);
            $this->assertSame([100, 50, 25], $notification->meta['percents']);
            $this->assertSame(25, $notification->meta['percent']);
            $this->assertStringContainsString('دو هفته پیاپی', $notification->body);
            // لحن پیام باید پیشنهاد کوچک بدهد، نه سرزنش
            $this->assertStringContainsString('یکی دو تسک کوچک', $notification->body);
            // هیچ رقم لاتینی نباید در متن اعلان بماند
            $this->assertDoesNotMatchRegularExpression('/[0-9]/', $notification->body);

            return true;
        });

        // کلید کم‌حرفی نوشته می‌شود تا هفته بعد دوباره همین پیام نرود.
        $this->assertTrue(Cache::has(SendDropAlertNotificationJob::dedupKey($user->id)));
    }

    /**
     * افت کمتر از پنج درصد نوسان است، نه سراشیبی.
     */
    public function test_drop_alert_is_skipped_when_the_fall_is_noise(): void
    {
        Notification::fake();

        $user = User::factory()->create(['drop_alert' => true]);
        $goals = $this->createGoals($user, 8);

        $this->fillWeek($goals, 2, 20, 20); // صد درصد
        $this->fillWeek($goals, 1, 50, 49); // نود و هشت درصد
        $this->fillWeek($goals, 0, 25, 24); // نود و شش درصد

        $this->runJob($user);

        Notification::assertNothingSent();
    }

    /**
     * هفته‌ای که برایش برنامه‌ای نبوده، هفته بدی نیست. همان قاعده روند در
     * صفحه گزارش.
     */
    public function test_drop_alert_is_skipped_when_a_week_has_no_tasks(): void
    {
        Notification::fake();

        $user = User::factory()->create(['drop_alert' => true]);
        $goals = $this->createGoals($user);

        $this->fillWeek($goals, 2, 4, 4);
        // هفته میانی خالی است
        $this->fillWeek($goals, 0, 4, 1);

        $this->runJob($user);

        Notification::assertNothingSent();
    }

    /**
     * بالا رفتن و بعد افتادن، یک هفته بد است نه دو افت پیاپی.
     */
    public function test_drop_alert_is_skipped_when_the_trend_is_not_a_slide(): void
    {
        Notification::fake();

        $user = User::factory()->create(['drop_alert' => true]);
        $goals = $this->createGoals($user);

        $this->fillWeek($goals, 2, 4, 2);
        $this->fillWeek($goals, 1, 4, 3);
        $this->fillWeek($goals, 0, 4, 1);

        $this->runJob($user);

        Notification::assertNothingSent();
    }

    public function test_drop_alert_is_skipped_when_disabled(): void
    {
        Notification::fake();

        $user = $this->createSlidingUser();
        $user->update(['drop_alert' => false]);

        $this->runJob($user);

        Notification::assertNothingSent();
    }

    /**
     * کسی که چند هفته در سراشیبی است نباید هر هفته همین جمله را بگیرد.
     */
    public function test_drop_alert_is_skipped_when_one_was_sent_recently(): void
    {
        Notification::fake();

        $user = $this->createSlidingUser();

        Cache::put(SendDropAlertNotificationJob::dedupKey($user->id), true, 600);

        $this->runJob($user);

        Notification::assertNothingSent();
    }

    /**
     * گزارش هفتگی خودش مقایسه با هفته قبل دارد. دو پیام درباره یک عدد در یک
     * روز، ایراد به نظر می‌رسد.
     */
    public function test_drop_alert_is_skipped_when_the_weekly_report_went_out_today(): void
    {
        Notification::fake();

        $user = $this->createSlidingUser();

        Cache::put(
            SendWeeklyReportNotificationJob::dedupKey($user->id, Carbon::today()->toDateString()),
            true,
            600
        );

        $this->runJob($user);

        Notification::assertNothingSent();
    }

    public function test_command_dispatches_only_for_users_who_asked_for_the_alert(): void
    {
        Bus::fake();

        $wants = User::factory()->create(['drop_alert' => true]);
        $doesNot = User::factory()->create(['drop_alert' => false]);

        $this->artisan('reports:send-drop-alerts-due')->assertSuccessful();

        Bus::assertDispatched(SendDropAlertNotificationJob::class, fn ($job) => $job->userId === $wants->id);
        Bus::assertNotDispatched(SendDropAlertNotificationJob::class, fn ($job) => $job->userId === $doesNot->id);
    }

    public function test_settings_endpoint_stores_the_drop_alert_flag_and_clears_its_key(): void
    {
        $user = User::factory()->create();

        Cache::put(SendDropAlertNotificationJob::dedupKey($user->id), true, 600);

        Sanctum::actingAs($user);

        $this->postJson('/api/user-setting', [
            'daily_report' => true,
            'report_time' => '08:00',
            'task_reminder' => false,
            'task_reminder_time' => '09:00',
            'per_task_progress' => false,
            'drop_alert' => true,
        ])->assertOk();

        $this->getJson('/api/user-setting')
            ->assertOk()
            ->assertJsonPath('drop_alert', true);

        // روشن کردن هشدار نباید پشت کلید کم‌حرفی قبلی بماند.
        $this->assertFalse(Cache::has(SendDropAlertNotificationJob::dedupKey($user->id)));
    }
}
