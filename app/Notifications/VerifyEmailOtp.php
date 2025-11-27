<?php
// App/Notifications/VerifyEmailOtp.php
namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailOtp extends Notification implements ShouldQueue // implements ShouldQueue برای کارایی بهتر
{
    use Queueable;

    public $code;

    /**
     * کد را از طریق سازنده (Constructor) دریافت می‌کنیم.
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('کد تأیید ایمیل حساب کاربری شما')
            ->greeting('سلام، ' . $notifiable->name)
            ->line('از اینکه در سیستم تودو ثبت‌نام کردید متشکریم.')
            ->line('لطفاً کد تأیید ۶ رقمی زیر را برای تکمیل ثبت‌نام وارد کنید:')
            // این کد را در یک Block بزرگ و قابل مشاهده قرار می‌دهیم
            ->line(
                (new \Illuminate\Support\HtmlString('<div style="font-size: 24px; font-weight: bold; text-align: center; padding: 10px; background-color: #f3f4f6; border-radius: 8px;">' . $this->code . '</div>'))
            )
            ->line('این کد تا ۵ دقیقه دیگر منقضی می‌شود. اگر خودتان درخواست نداده‌اید، می‌توانید این ایمیل را نادیده بگیرید.')
            ->action('ورود به سیستم (اختیاری)', url('/login'))
            ->salutation('با آرزوی موفقیت');
    }

    // اگر Queue را فعال کردید، در صورت شکست، ایمیل به این کاربر اطلاع داده شود
    // public function failed(Exception $exception) {} 
}