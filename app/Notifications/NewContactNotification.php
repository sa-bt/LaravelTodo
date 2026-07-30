<?php

namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewContactNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Contact $contact
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'mail',
            'database',
            WebPushChannel::class,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('پیام جدید از فرم تماس با ما')
            ->greeting("سلام {$notifiable->name}")
            ->line("یک پیام جدید از {$this->contact->name} دریافت کردید.")
            ->line("ایمیل: {$this->contact->email}")
            ->line('متن پیام:')
            ->line($this->contact->message)
            ->action('ورود به پنل مدیریت', url('/admin'))
            ->line('ممنون از اینکه از سایت ما استفاده می‌کنید.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'پیام جدید از فرم تماس',
            'body' => "پیام جدیدی از {$this->contact->name} ثبت شد.",
            'url' => '/admin',
            'icon' => '/admin-pwa-192x192.png',
            'tag' => $this->tag(),
            'type' => 'new_contact',
            'meta' => [
                'contact_id' => $this->contact->getKey(),
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
            ->title('پیام جدید از فرم تماس')
            ->body("پیام جدیدی از {$this->contact->name} ثبت شد.")
            ->icon('/admin-pwa-192x192.png')
            ->tag($this->tag())
            ->data([
                '__kind' => 'webpush',
                'persisted' => true,
                'notification_id' => $this->id,
                'url' => '/admin',
                'type' => 'new_contact',
                'contact_id' => $this->contact->getKey(),
            ])
            ->vibrate([100, 50, 100])
            ->action('باز کردن', 'open')
            ->dir('rtl')
            ->lang('fa-IR')
            ->renotify(false)
            ->requireInteraction(false);
    }

    private function tag(): string
    {
        return "new-contact-{$this->contact->getKey()}";
    }
}
