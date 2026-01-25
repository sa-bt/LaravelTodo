<?php

// app/Notifications/NewContactNotification.php
namespace App\Notifications;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactNotification extends Notification
{
use Queueable;

public function __construct(
public readonly Contact $contact
) {}

public function via(object $notifiable): array
{
return ['mail'];
}

public function toMail(object $notifiable): MailMessage
{
return (new MailMessage)
->subject('پیام جدید از فرم تماس با ما')
->greeting("سلام {$notifiable->name}")
->line("یک پیام جدید از {$this->contact->name} دریافت کردید.")
->line("ایمیل: {$this->contact->email}")
->line("متن پیام:")
->line($this->contact->message)
->action('مشاهده پیام‌ها', url('/admin/contacts')) // لینک به پنل ادمین
->line('ممنون از اینکه از سایت ما استفاده می‌کنید.');
}
}
