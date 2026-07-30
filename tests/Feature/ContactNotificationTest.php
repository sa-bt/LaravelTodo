<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use App\Notifications\NewContactNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class ContactNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_contact_submission_notifies_every_administrator(): void
    {
        Notification::fake();

        $regularUser = User::factory()->create(['role' => 'user']);
        $firstAdministrator = User::factory()->create(['role' => 'admin']);
        $secondAdministrator = User::factory()->create(['role' => 'admin']);

        $captchaId = 'contact-notification-test';
        $captchaAnswer = 'AB12';
        $pepper = 'test-pepper';

        config()->set('app.captcha_pepper', $pepper);
        Cache::put(
            "captcha:{$captchaId}",
            hash('sha256', $captchaAnswer.$pepper),
            now()->addMinutes(5),
        );

        $response = $this->postJson('/api/contact', [
            'name' => 'فرستنده آزمایشی',
            'email' => 'sender@example.com',
            'message' => 'این یک پیام آزمایشی معتبر است.',
            'captcha_id' => $captchaId,
            'captcha_answer' => strtolower($captchaAnswer),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'contact.success');

        $this->assertDatabaseHas('contacts', [
            'name' => 'فرستنده آزمایشی',
            'email' => 'sender@example.com',
        ]);

        Notification::assertSentTo(
            [$firstAdministrator, $secondAdministrator],
            NewContactNotification::class,
            function (NewContactNotification $notification, array $channels): bool {
                $payload = $notification->toDatabase(new User);
                $webPush = $notification->toWebPush(new User, $notification)->toArray();

                $this->assertSame(
                    ['mail', 'database', WebPushChannel::class],
                    $channels,
                );
                $this->assertSame('new_contact', $payload['type']);
                $this->assertSame('/admin', $payload['url']);
                $this->assertSame('rtl', $webPush['dir']);
                $this->assertSame('fa-IR', $webPush['lang']);
                $this->assertSame('/admin', $webPush['data']['url']);

                return true;
            },
        );

        Notification::assertNotSentTo($regularUser, NewContactNotification::class);
        $this->assertSame(1, Contact::query()->count());
    }

    public function test_invalid_captcha_does_not_store_contact_or_notify_administrators(): void
    {
        Notification::fake();

        $administrator = User::factory()->create(['role' => 'admin']);

        $response = $this->postJson('/api/contact', [
            'name' => 'فرستنده آزمایشی',
            'email' => 'sender@example.com',
            'message' => 'این یک پیام آزمایشی معتبر است.',
            'captcha_id' => 'missing-captcha',
            'captcha_answer' => 'wrong',
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseCount('contacts', 0);
        Notification::assertNotSentTo($administrator, NewContactNotification::class);
    }
}
