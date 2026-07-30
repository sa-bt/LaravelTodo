<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * نتیجهٔ تصمیم ادمین را به خود کاربر می‌گوید.
 *
 * ایمیل اینجا برخلاف اعلان ادمین لازم است: کاربرِ در انتظار هنوز وارد سیستم
 * نشده، پس نه اعلان درون‌برنامه‌ای را می‌بیند و نه اشتراک وب‌پوش دارد.
 */
class AccountApprovalDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly bool $approved
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
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting("سلام {$notifiable->name}");

        return $this->approved
            ? $mail->line('حساب شما تأیید شد و از این پس می‌توانید وارد شوید.')
                ->action('ورود به حساب', url('/vorod'))
            : $mail->line('متأسفانه درخواست عضویت شما پذیرفته نشد.')
                ->line('اگر فکر می‌کنید اشتباهی رخ داده، از فرم تماس با ما پیام بگذارید.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->approved ? '/vorod' : '/',
            'icon' => '/pwa-192x192.png',
            'tag' => $this->tag($notifiable),
            'type' => 'account_approval',
            'meta' => [
                'approved' => $this->approved,
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
            ->title($this->title())
            ->body($this->body())
            ->icon('/pwa-192x192.png')
            ->tag($this->tag($notifiable))
            ->data([
                '__kind' => 'webpush',
                'persisted' => true,
                'notification_id' => $this->id,
                'url' => $this->approved ? '/vorod' : '/',
                'type' => 'account_approval',
            ])
            ->vibrate([100, 50, 100])
            ->action('باز کردن', 'open')
            ->dir('rtl')
            ->lang('fa-IR')
            ->renotify(false)
            ->requireInteraction(false);
    }

    private function title(): string
    {
        return $this->approved ? 'حساب شما تأیید شد' : 'درخواست عضویت پذیرفته نشد';
    }

    private function body(): string
    {
        return $this->approved
            ? 'از این پس می‌توانید وارد حساب خود شوید.'
            : 'مدیر سامانه درخواست عضویت شما را نپذیرفت.';
    }

    private function tag(object $notifiable): string
    {
        return "account-approval-{$notifiable->getKey()}";
    }
}
