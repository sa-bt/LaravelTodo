<?php

namespace Tests\Unit;

use App\Notifications\GenericWebPush;
use App\Notifications\ReportNotification;
use App\Notifications\TaskNotification;
use NotificationChannels\WebPush\WebPushMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class WebPushNotificationDirectionTest extends TestCase
{
    public static function notificationProvider(): array
    {
        return [
            'generic' => [new GenericWebPush('عنوان', 'متن')],
            'report' => [new ReportNotification('عنوان', 'متن', 'daily', 'daily-report')],
            'task' => [new TaskNotification],
        ];
    }

    #[DataProvider('notificationProvider')]
    public function test_every_web_push_notification_uses_the_persian_direction_contract(object $notification): void
    {
        $message = $notification->toWebPush(new stdClass, new stdClass);

        $this->assertInstanceOf(WebPushMessage::class, $message);
        $this->assertSame('rtl', $message->toArray()['dir']);
        $this->assertSame('fa-IR', $message->toArray()['lang']);
        $this->assertSame([100, 50, 100], $message->toArray()['vibrate']);
        $this->assertSame([], $message->getOptions());
    }
}
