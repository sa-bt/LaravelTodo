<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * به ادمین‌ها می‌گوید یک حسابِ تازه ایمیلش را تأیید کرده و منتظر اجازهٔ ورود است.
 */
class NewUserAwaitingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $newUser
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
            WebPushChannel::class,
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'کاربر جدید در انتظار تأیید',
            'body' => "{$this->newUser->name} ثبت‌نام کرد و منتظر اجازهٔ ورود است.",
            'url' => '/admin/users?status=pending',
            'icon' => '/admin-pwa-192x192.png',
            'tag' => $this->tag(),
            'type' => 'user_awaiting_approval',
            'meta' => [
                'user_id' => $this->newUser->getKey(),
                'user_email' => $this->newUser->email,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('کاربر جدید در انتظار تأیید')
            ->body("{$this->newUser->name} ثبت‌نام کرد و منتظر اجازهٔ ورود است.")
            ->icon('/admin-pwa-192x192.png')
            ->tag($this->tag())
            ->data([
                '__kind' => 'webpush',
                'persisted' => true,
                'notification_id' => $this->id,
                'url' => '/admin/users?status=pending',
                'type' => 'user_awaiting_approval',
                'user_id' => $this->newUser->getKey(),
            ])
            ->vibrate([100, 50, 100])
            ->action('بررسی', 'open')
            ->dir('rtl')
            ->lang('fa-IR')
            ->renotify(false)
            ->requireInteraction(false);
    }

    private function tag(): string
    {
        return "user-approval-{$this->newUser->getKey()}";
    }
}
