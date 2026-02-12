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
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        // ✅ title: بررسی انگلیسی بودن
        $title = $this->title;
        if (preg_match('/[a-zA-Z]/', $title)) {
            // شامل انگلیسی → LRI
            $title = "\u{2066}" . $title . "\u{2069}";
        } else {
            // فقط فارسی → RLI
            $title = "\u{2067}" . $title . "\u{2069}";
        }

        // ✅ body: فقط فارسی → RLI
        $body = "\u{2067}" . $this->body . "\u{2069}";

        $data = array_merge([
            'url' => $this->url ?? url('/'),
            'type' => 'generic',
        ], $this->meta);

        $msg = (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon($this->icon)
            ->tag($this->tag ?? 'todo-notification')
            ->data($data)
            ->vibrate([100, 50, 100])
            ->options([
                'dir' => 'rtl',
                'lang' => 'fa-IR',
                'renotify' => true,
                'requireInteraction' => false,
            ]);

        return $msg;
    }
}
