<?php

namespace App\Services;

/**
 * Text of the report notifications, daily and weekly.
 *
 * Kept apart from the jobs so both reports count and phrase things the same
 * way, and so the wording can be changed without touching queue code.
 *
 * Every number the user reads is written in persian digits. The rest of the
 * product does that everywhere; a notification with latin digits reads like it
 * came from a different app.
 */
class ReportMessageService
{
    /** Below this, a difference is noise rather than a trend. */
    public const MIN_GAP = 5;

    private const DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    public function digits(int|string $value): string
    {
        return str_replace(range(0, 9), self::DIGITS, (string) $value);
    }

    public function percent(int $value): string
    {
        return $this->digits($value) . '٪';
    }

    /**
     * Body of the daily report.
     *
     * @param  array{total: int, done: int, remaining: int, percent: int}  $today
     * @param  int|null  $yesterdayPercent  null when yesterday had no tasks
     * @param  int  $backlog  tasks whose day already passed while not done
     */
    public function dailyBody(array $today, ?int $yesterdayPercent = null, int $backlog = 0): string
    {
        $total = $this->digits($today['total']);

        if ($today['remaining'] === 0) {
            $lines = ["🎉 عالی! هر {$total} تسک امروز را تمام کردی."];
        } elseif ($today['done'] === 0) {
            $lines = ["🎯 هنوز شروع نکردی. {$total} تسک امروز منتظرند."];
        } else {
            $done = $this->digits($today['done']);
            $lines = ["{$done} از {$total} تسک امروز انجام شد ({$this->percent($today['percent'])})."];
        }

        // دو روز کامل پشت سر هم ارزش گفتن دارد. «تقریباً مثل دیروز» اینجا
        // جمله سردی است برای کسی که دو روز است چیزی جا نینداخته.
        if ($today['remaining'] === 0 && $yesterdayPercent === 100) {
            $lines[] = 'دیروز هم کامل بود. 🔥';
        } else {
            $comparison = $this->comparison($today['percent'], $yesterdayPercent, 'دیروز');

            if ($comparison) {
                $lines[] = $comparison;
            }
        }

        if ($backlog > 0) {
            $lines[] = "{$this->digits($backlog)} کار عقب‌افتاده هم داری.";
        }

        return implode(' ', $lines);
    }

    /**
     * Body of the weekly report.
     *
     * @param  array{total: int, done: int, percent: int, perfectDays: int}  $week
     * @param  int|null  $previousPercent  null when last week had no tasks
     */
    public function weeklyBody(array $week, ?int $previousPercent = null, int $backlog = 0): string
    {
        if ($week['total'] === 0) {
            return 'این هفته هیچ تسکی برنامه‌ریزی نکردی. هفته تازه فرصت خوبی است برای شروع دوباره. 🌱';
        }

        $lines = [sprintf(
            'این هفته %s از %s تسک را انجام دادی (%s).',
            $this->digits($week['done']),
            $this->digits($week['total']),
            $this->percent($week['percent'])
        )];

        $comparison = $this->comparison($week['percent'], $previousPercent, 'هفته قبل');

        if ($comparison) {
            $lines[] = $comparison;
        }

        if ($week['perfectDays'] > 0) {
            $lines[] = "{$this->digits($week['perfectDays'])} روز کامل داشتی. 🌟";
        }

        if ($backlog > 0) {
            $lines[] = "{$this->digits($backlog)} کار عقب‌افتاده هم روی دستت مانده.";
        }

        return implode(' ', $lines);
    }

    /**
     * Body of the drop alert.
     *
     * The tone is the whole design of this message. Someone whose completion
     * rate fell two weeks running already knows it went badly; being told so
     * again in a stern voice is a reason to switch notifications off. So the
     * message states the three numbers plainly, blames nothing, and asks for
     * the smallest possible next step.
     *
     * @param  array<int, int>  $percents  oldest week first, three of them
     */
    public function dropAlertBody(array $percents, int $backlog = 0): string
    {
        // با فلش نوشته نمی‌شود. جهت فلش در متن راست‌به‌چپ خوانده نمی‌شود.
        $first = $this->percent($percents[0]);
        $middle = $this->percent($percents[1]);
        $last = $this->percent($percents[count($percents) - 1]);

        $lines = [
            "دو هفته پیاپی بازدهی‌ات پایین آمده: از {$first} به {$middle} و حالا {$last}.",
            'لازم نیست هفته را بزرگ شروع کنی. یکی دو تسک کوچک برای امروز کافی است. 🌱',
        ];

        if ($backlog > 0) {
            $lines[] = "{$this->digits($backlog)} کار عقب‌افتاده هم داری؛ از قدیمی‌ترینش شروع کن.";
        }

        return implode(' ', $lines);
    }

    /**
     * Comparison with an earlier period.
     *
     * Returns null when there is nothing to compare against, so the caller
     * never prints a sentence built on a missing number. A gap under MIN_GAP is
     * called out as "about the same" rather than dressed up as progress.
     */
    private function comparison(int $current, ?int $previous, string $label): ?string
    {
        if ($previous === null) {
            return null;
        }

        $diff = $current - $previous;

        if (abs($diff) < self::MIN_GAP) {
            return "تقریباً مثل {$label}.";
        }

        return $diff > 0
            ? "نسبت به {$label} {$this->percent($diff)} بهتر شدی. 📈"
            : "نسبت به {$label} {$this->percent(-$diff)} افت کردی. 📉";
    }
}
