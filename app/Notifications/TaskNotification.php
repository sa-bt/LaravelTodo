<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('یادآوری تسک')
            ->icon('/pwa-192x192.png')
            ->body('یک تسک جدید برات اومده!')
            ->vibrate([100, 50, 100])
            ->action('مشاهده', 'view_task')
            ->dir('rtl')
            ->lang('fa-IR')
            ->renotify(false)
            ->requireInteraction(false);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
