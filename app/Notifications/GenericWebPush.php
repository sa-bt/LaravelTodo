<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class GenericWebPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public array $meta = [],
        public ?string $icon = '/pwa-192x192.png',
        public ?string $tag = null,
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
            'url' => $this->url ?? '/',
            'icon' => $this->icon,
            'tag' => $this->resolvedTag(),
            'type' => $this->type(),
            'meta' => $this->meta,
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
            'url' => $this->url ?? '/',
            'type' => $this->type(),
        ], $this->meta);

        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon)
            ->tag($this->resolvedTag())
            ->data($data)
            ->vibrate([100, 50, 100])
            ->action('باز کردن', 'open')
            ->options([
                'dir' => 'rtl',
                'lang' => 'fa-IR',
                'renotify' => true,
                'requireInteraction' => false,
            ]);
    }

    private function type(): string
    {
        return $this->meta['type'] ?? 'generic';
    }

    private function resolvedTag(): string
    {
        return $this->tag ?: str(config('app.name'))->slug()->toString() . '-notification';
    }
}
