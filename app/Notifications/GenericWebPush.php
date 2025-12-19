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
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    // ذخیره در جدول notifications

    // پیام Web Push
    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        // کاراکتر کنترل برای شروع و پایان متن راست‌به‌چپ
        $rtlStart = "\u{202B}"; // Right-to-left embedding
        $rtlEnd   = "\u{202C}"; // Pop directional formatting

        $title = $rtlStart . $this->title . $rtlEnd;
        $body  = $rtlStart . $this->body  . $rtlEnd;

        $msg = (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/pwa-192x192.png') // 👈 حتماً این رو ست کن
            ->badge('/pwa-badge.png')  // 👈 این برای موبایل خیلی حیاتیه
            ->data(['url' => $this->url ?? url('/')] + $this->meta)
            ->vibrate([100, 50, 100])
            ->options(['renotify' => true, 'dir' => 'rtl', 'lang' => 'fa-IR'])
            ->action('باز کردن', 'open_app');

        if ($this->tag) {
            $msg->tag($this->tag);
        } else {
            $msg->tag(md5($this->title . $this->body . ($this->url ?? '')));
        }

        return $msg;
    }
}
