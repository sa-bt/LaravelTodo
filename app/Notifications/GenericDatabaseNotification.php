<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class GenericDatabaseNotification extends Notification implements ShouldQueue
{
    public function __construct(public string $title, public string $body, public ?string $url = null, public array $meta = [])
    {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => $this->meta['type'] ?? 'generic',
            'title'   => $this->title,
            'body'    => $this->body,
            'url'     => $this->url ?? url('/'),
            'meta'    => $this->meta,
            'sent_at' => now()->toISOString(),
        ];
    }
}
