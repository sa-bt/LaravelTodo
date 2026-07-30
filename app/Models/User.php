<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasPushSubscriptions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'daily_report',
        'report_time',
        'weekly_report',
        'weekly_report_day',
        'weekly_report_time',
        'drop_alert',
        'verification_code',
        'verification_code_expires_at',
        'task_reminder',
        'task_reminder_time',
        'per_task_progress',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        // هش کد تأیید هیچ‌جا لازم نیست بیرون برود؛ فهرست کاربرانِ پنل ادمین
        // کل ردیف را برمی‌گرداند و این ستون هم با آن می‌رفت.
        'verification_code',
        'verification_code_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'password' => 'hashed',
            'role' => 'string',
        ];
    }

    /*
     * ستون‌های تأیید عمداً در $fillable نیستند. تنها راه تغییرشان متدهای زیر
     * است، تا هیچ update انبوهی نتواند حسابی را از صف انتظار بیرون بکشد.
     */
    public function isApproved(): bool
    {
        return $this->approved_at !== null && $this->rejected_at === null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function isPendingApproval(): bool
    {
        return $this->approved_at === null && $this->rejected_at === null;
    }

    public function approveBy(self $admin): void
    {
        $this->forceFill([
            'approved_at' => now(),
            'rejected_at' => null,
            'approved_by' => $admin->getKey(),
        ])->save();
    }

    public function rejectBy(self $admin): void
    {
        $this->forceFill([
            'approved_at' => null,
            'rejected_at' => now(),
            'approved_by' => $admin->getKey(),
        ])->save();

        // حسابِ ردشده نباید با توکن قدیمی‌اش سرِ پا بماند.
        $this->tokens()->delete();
    }

    public function goals()
    {
        return $this->hasMany(Goal::class);
    }

    public function contents()
    {
        return $this->hasMany(Content::class);
    }
}
