<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\GenericDatabaseNotification;
use App\Notifications\GenericWebPush;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminNotificationCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_queue_an_in_app_notification_for_selected_users(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $recipients = User::factory()->count(2)->create();
        $excluded = User::factory()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/notification-campaigns', [
            'title' => 'عنوان مهم',
            'body' => 'متن اعلان',
            'url' => '/app/goals',
            'channel' => 'database',
            'audience' => 'selected',
            'user_ids' => $recipients->modelKeys(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.recipient_count', 2);

        Notification::assertSentTo($recipients, GenericDatabaseNotification::class);
        Notification::assertNotSentTo($excluded, GenericDatabaseNotification::class);
        $this->assertDatabaseHas('admin_notification_campaigns', [
            'user_id' => $admin->id,
            'recipient_count' => 2,
            'channel' => 'database',
        ]);
    }

    public function test_administrator_can_queue_web_push_for_all_users(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(2)->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/notification-campaigns', [
            'title' => 'خبر تازه',
            'body' => 'برای مشاهده وارد برنامه شوید.',
            'channel' => 'webpush',
            'audience' => 'all',
        ])
            ->assertCreated()
            ->assertJsonPath('data.recipient_count', 3);

        Notification::assertCount(3);
        Notification::assertSentTo($admin, GenericWebPush::class);
    }

    public function test_payload_requires_an_internal_url_and_selected_recipients(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/admin/notification-campaigns', [
            'title' => 'عنوان',
            'body' => 'متن',
            'url' => 'https://example.com',
            'channel' => 'database',
            'audience' => 'selected',
            'user_ids' => [],
        ])
            ->assertUnprocessable()
            ->assertJsonCount(2, 'errors');
    }

    public function test_regular_user_cannot_send_or_read_campaigns(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/notification-campaigns')->assertForbidden();
        $this->postJson('/api/admin/notification-campaigns', [])->assertForbidden();
    }
}
