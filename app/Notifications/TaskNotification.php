<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class TaskNotification extends Notification
{
    

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class]; // فقط WebPush
    }

    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('یادآوری تسک')
            ->icon('/pwa-192x192.png') // 👈 حتماً این رو ست کن
            ->badge('/pwa-badge.png')  // 👈 این برای موبایل خیلی حیاتیه
            ->body('یک تسک جدید برات اومده!')
            ->action('مشاهده', 'view_task');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
