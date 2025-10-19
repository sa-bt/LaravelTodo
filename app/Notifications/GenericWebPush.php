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
        public ?string $icon = '/icons/notification.png',
        public ?string $tag  = null,
        public array $channels = ['database', WebPushChannel::class], // 👈 پیش‌فرض دوکاناله
    ) {}

    public function via(object $notifiable): array
    {
        // اگر کاربر subscription ندارد، همچنان رکورد دیتابیسی ثبت شود
        if (empty($this->channels)) {
            return ['database', WebPushChannel::class];
        }
        return $this->channels;
    }

    // ذخیره در جدول notifications
    public function toArray(object $notifiable): array
    {
        return [
            'type'    => $this->meta['type'] ?? 'generic',
            'title'   => $this->title,
            'body'    => $this->body,
            'url'     => $this->url ?? url('/'),
            'meta'    => $this->meta,
            'icon'    => $this->icon,
            'tag'     => $this->tag,
            'sent_at' => now()->toISOString(),
        ];
    }

    // پیام Web Push
    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $msg = (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon($this->icon ?? '/icons/notification.png')
            ->data(['url' => $this->url ?? url('/')] + $this->meta)
            ->vibrate([100, 50, 100])
            ->options(['renotify' => true])
            ->action('باز کردن', 'open_app');

        if ($this->tag) {
            $msg->tag($this->tag);
        } else {
            $msg->tag(md5($this->title.$this->body.($this->url ?? '')));
        }

        return $msg;
    }
}
