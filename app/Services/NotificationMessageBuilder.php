<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Arr;

class NotificationMessageBuilder
{
    public static function build(Task $task): string
    {
        $goal = $task->goal;

        // اگر آمار نداشت یا فرزند داشت، پیام عمومی بده
        if (!$goal->stats) {
            return Arr::random([
                "وقتشه یه گام به هدفت نزدیک‌تر بشی 🚀",
                "امروز رو با یک تیک خوب شروع کن ✅",
                "یادته قول دادی این هدف رو انجام بدی؟ امروز وقتشه! 🎯",
                "بی‌خیال تعویق — الان بهترین موقع برای شروعه ⏳",
                "هدف‌ها با انجام کارهای کوچیک ساخته می‌شن. همین امروز 💡",
            ]);
        }

        $successStreak = $goal->stats['max_streak_success']['length'] ?? 0;
        $failStreak = $goal->stats['max_streak_fail']['length'] ?? 0;

        // داینامیک: streak موفقیت
        if ($successStreak >= 2) {
            return Arr::random([
                "{$successStreak} روز پیاپی موفق بودی — ادامه بده 🔥",
                "{$successStreak} روزه روون پیش میری 👏 نذار زنجیره قطع شه",
                "تا اینجا {$successStreak} روزه عالی بودی — امروز هم همینطور ادامه بده 💪",
                "زنجیره موفقیت {$successStreak} روزه‌ت رو حفظ کن 🌟",
                "{$successStreak} روز پشت هم تیک زدی — بیا امروز رو هم بزنی ✅",
                "تو مسیر {$successStreak} روزه‌ای! نذار خراب شه 🛤️",
                "{$successStreak} روز موفقیت؟ یعنی داری عالی پیش میری 💯",
                "تو قهرمان {$successStreak} روزه‌ای 😎 امروز رو هم محکم ادامه بده",
                "{$successStreak} روزه که عقب‌نشینی نکردی — تسلیم نشو 🔥",
            ]);
        }

        // داینامیک: streak شکست
        if ($failStreak >= 2) {
            return Arr::random([
                "{$failStreak} روز عقب افتادی — نذار امروز هم بهش اضافه شه ⛔",
                "{$failStreak} روزه تسک‌هات مونده — وقتشه ورق رو برگردونی 🔁",
                "{$failStreak} روزه انجام ندادی — همین امروز دوباره شروع کن 🌅",
                "{$failStreak} روز رو از دست دادی — نذار امروز هم از دست بره 🕘",
                "{$failStreak} روز سکوت کردی — امروز حرف بزن با یه تیک! ✅",
                "{$failStreak} روزه تسک‌هات خاک می‌خورن 😬 بیا امروز یه تیک بزن",
                "{$failStreak} روزه فاصله گرفتی — برگرد به مسیر خودت ⤴️",
                "{$failStreak} روز فاصله افتاده — الان وقت برگشتنه 💪",
            ]);
        }

        // حالت عمومی (نه success و نه fail streak)
        return Arr::random([
            "یه تیک ساده می‌تونه مسیرتو تغییر بده ✅",
            "همه چیز از یه تسک کوچیک شروع میشه — امروز انجامش بده 🔰",
            "هدف‌ها با تداوم ساخته می‌شن — امروز هم تلاش کن 🛠️",
            "یک کار کوچیک برای رسیدن به چیزای بزرگ 🚀",
            "امروز یه فرصت جدیده — همین الان شروع کن 🌞",
            "دست دست نکن — وقت انجامشه ⏱️",
        ]);
    }
}
