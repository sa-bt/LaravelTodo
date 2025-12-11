<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class DailyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?int $percent = null,
        public ?int $remaining = null,
        public array $meta = [],
        public ?string $icon = '/webpush-icons/report.png',
        public ?string $tag = 'daily-report',
    ) {}

    /**
     * 🔹 کانال‌های ارسالی
     */
    public function via($notifiable): array
    {
        return [WebPushChannel::class, 'database', 'mail'];
    }

    /**
     * 🔹 ذخیره در جدول notifications
     */
    public function toDatabase($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'url'   => $this->url,
            'icon'  => $this->icon,
            'tag'   => $this->tag,
            'meta'  => $this->meta,
            'percent' => $this->percent,
            'remaining' => $this->remaining,
        ];
    }

    /**
     * 🔹 ارسال ایمیل با قالب فارسی
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->view('emails.daily_report', [
                'user' => $notifiable,
                'title' => $this->title,
                'body' => $this->body,
                'url' => $this->url,
                'percent' => $this->percent,
                'remaining' => $this->remaining,
            ])
            ->subject('📊 گزارش پیشرفت روزانه')
            ->from(config('mail.from.address'), config('mail.from.name'));
    }

    /**
     * 🔹 اعلان وب‌پوش
     */
    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            // ->icon($this->icon)
            ->tag($this->tag)
            ->data([
                'url' => $this->url,
                'meta' => $this->meta,
                'percent' => $this->percent,
                'remaining' => $this->remaining,
            ]);
    }
}
