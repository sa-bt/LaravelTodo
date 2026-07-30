<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * A progress report pushed to the user, daily or weekly.
 *
 * Was DailyReportNotification. Nothing in it was ever daily specific: the
 * caller supplies every field. The weekly report needed exactly the same
 * envelope, and a second near identical class would have been the wrong way to
 * get it.
 */
class ReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $type,
        public string $tag,
        public ?string $url = '/app/day',
        public ?int $percent = null,
        public ?int $remaining = null,
        public array $meta = [],
        public ?string $icon = '/pwa-192x192.png',
        public bool $persisted = true,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [
            WebPushChannel::class,
        ];

        if ($this->persisted) {
            $channels[] = 'database';
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url ?? '/app/day',
            'icon' => $this->icon,
            'tag' => $this->tag,
            'type' => $this->type,
            'meta' => $this->meta,
            'percent' => $this->percent,
            'remaining' => $this->remaining,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $data = array_merge([
            '__kind' => 'webpush',
            'persisted' => $this->persisted,
            'notification_id' => $this->persisted ? $this->id : null,
            'url' => $this->url ?? '/app/day',
            'type' => $this->type,
            'percent' => $this->percent,
            'remaining' => $this->remaining,
        ], $this->meta);

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon)
            ->tag($this->tag)
            ->data($data)
            ->vibrate([100, 50, 100])
            ->action('باز کردن', 'open')
            ->options([
                'dir' => 'rtl',
                'lang' => 'fa-IR',
                'renotify' => false,
                'requireInteraction' => false,
            ]);
    }
}
