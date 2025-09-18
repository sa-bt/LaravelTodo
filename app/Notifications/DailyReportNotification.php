<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\WebPush\WebPushMessage;
use App\Models\User;
class DailyReportNotification extends Notification
{
    

    protected $type;
    protected $user;

    public function __construct(User $user, string $type)
    {
        $this->user = $user;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['mail', 'webpush'];
    }

    public function toMail($notifiable)
    {
        if ($this->type === 'report') {
            return (new MailMessage)
                ->subject('گزارش روزانه')
                ->line('گزارش روزانه اهداف شما آماده است.')
                ->action('مشاهده اهداف', url('/goals'));
        } else {
            return (new MailMessage)
                ->subject('یادآوری انجام تسک‌ها')
                ->line('یادآوری انجام تسک‌های امروز شما')
                ->action('مشاهده تسک‌ها', url('/tasks/today'));
        }
    }

    public function toWebPush($notifiable, $notification)
    {
        $title = $this->type === 'report' ? 'گزارش روزانه' : 'یادآوری تسک‌ها';
        $body = $this->type === 'report'
            ? 'گزارش روزانه اهداف شما آماده است.'
            : 'یادآوری انجام تسک‌های امروز شما';

        return (new WebPushMessage)
            ->title($title)
            ->body($body)
            ->icon('/icon.png')
            ->action('مشاهده', $this->type === 'report' ? url('/goals') : url('/tasks/today'));
    }
}
