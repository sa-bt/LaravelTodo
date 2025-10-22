<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Carbon\Carbon;

class ProgressMessageService
{
    protected array $messages;

    public function __construct()
    {
        $path = resource_path('lang/fa/progress_messages.json');
        $this->messages = json_decode(file_get_contents($path), true) ?? [];
    }

    /**
     * محاسبه درصد پیشرفت کاربر برای یک تاریخ خاص
     */
    public function getUserProgressForDate(int $userId, string|Carbon $date): array
    {
        $date = $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString();

        $agg = DB::table('tasks')
            ->join('goals', 'tasks.goal_id', '=', 'goals.id')
            ->where('goals.user_id', $userId)
            ->whereDate('tasks.day', $date)
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN tasks.is_done = 1 THEN 1 ELSE 0 END) as done')
            ->first();

        $total = (int) ($agg->total ?? 0);
        $done  = (int) ($agg->done  ?? 0);

        $percent   = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        $remaining = max($total - $done, 0);

        return compact('total', 'done', 'percent', 'remaining') + ['date' => $date];
    }

    /**
     * ساخت پیام داینامیک بر اساس وضعیت کاربر و جهت تغییر
     * خروجی: ['text' => string, 'duration' => int]
     */
    public function buildMessage(
        int $percent,
        int $remaining,
        string $context = 'report',
        ?array $extras = []
    ): array {
        $direction = $extras['direction'] ?? 'forward'; // forward | backward

        // 🔹 حالت پسرفت (تسک لغو شده)
        if ($direction === 'backward') {
            $regressBank = $this->messages['regress'] ?? [];
            if (!empty($regressBank)) {
                $msg = Arr::random($regressBank);
                $msg = str_replace(['%{percent}', '%{remaining}'], [$percent, $remaining], $msg);
                return $this->formatMessage($msg);
            }
        }

        // 🔹 حالت پیشرفت (تسک انجام شده)
        if ($percent == 100) $key = 'full';
        elseif ($percent >= 70) $key = 'high';
        elseif ($percent >= 40) $key = 'mid';
        else $key = 'low';

        $bank = $this->messages[$key] ?? [];
        $message = str_replace(
            ['%{percent}', '%{remaining}'],
            [$percent, $remaining],
            Arr::random($bank)
        );

        // افزودن context (مثلاً report یا reminder)
        $contextBank = $this->messages[$context] ?? [];
        if (!empty($contextBank)) {
            $prefix = str_replace(
                ['%{percent}', '%{remaining}'],
                [$percent, $remaining],
                Arr::random($contextBank)
            );
            $message = $prefix . ' ' . $message;
        }

        // ساخت جمله نهایی با opener/closer
        $openers = ["آفرین 👏", "دمت گرم 💪", "ادامه بده 🌟", "هیچ‌چیز نمی‌تونه جلوتو بگیره 🚀"];
        $closers = ["تو قهرمان خودتی 👑", "به خودت افتخار کن 💫", "هر روز بهتر از دیروز 🌿"];

        $final = Arr::random($openers) . ' ' . $message . ' ' . Arr::random($closers);

        return $this->formatMessage($final);
    }

    /**
     * محاسبه مدت نمایش (duration) بر اساس طول پیام
     */
    protected function formatMessage(string $text): array
    {
        $base = 3000;
        $extraPerChar = 80;
        $length = mb_strlen($text);
        $duration = min(15000, max($base, $base + $length * $extraPerChar)); // بین 3 تا 15 ثانیه

        return [
            'text' => trim($text),
            'duration' => $duration,
        ];
    }
}
