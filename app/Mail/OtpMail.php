<?php
// App/Mail/OtpMail.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User; // مطمئن شوید مدل User ایمپورت شده است

class OtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $otpCode;
    public $expiresInMinutes;

    public function __construct(User $user, string $otpCode, int $expiresInMinutes = 2)
    {
        $this->user = $user;
        $this->otpCode = $otpCode;
        $this->expiresInMinutes = $expiresInMinutes;
    }

    public function build(): self
    {
        // 💡 استفاده از قالب HTML فارسی‌سازی شده
        $styles = [
        'primary' => '#10b981',
        'accent' => '#14b8a6',
        'text' => '#0f172a',
        'secondary' => '#475569', // 👈 متغیر مد نظر شما
        'border' => '#e2e8f0',
    ];

    return $this->subject('کد تأیید ایمیل حساب کاربری شما'. time())
                ->to($this->user->email)
                ->markdown('emails.otp', [
                    'user' => $this->user,
                    'otpCode' => $this->otpCode,
                    'expiresInMinutes' => $this->expiresInMinutes,
                    'styles' => $styles, // 👈 ارسال آرایه استایل
                ]);
    }
}