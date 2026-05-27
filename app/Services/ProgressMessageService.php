<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ProgressMessageService
{
    protected array $messages;

    public function __construct()
    {
        $path = resource_path('lang/fa/progress_messages.json');

        $this->messages = File::exists($path)
            ? json_decode(File::get($path), true) ?? []
            : [];
    }

    public function getUserProgressForDate(int $userId, string|Carbon $date): array
    {
        $date = $date instanceof Carbon
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();

        $aggregate = DB::table('tasks')
            ->join('goals', 'tasks.goal_id', '=', 'goals.id')
            ->where('goals.user_id', $userId)
            ->whereDate('tasks.day', $date)
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('goals as child_goals')
                    ->whereColumn('child_goals.parent_id', 'goals.id');
            })
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN tasks.is_done = 1 THEN 1 ELSE 0 END) as done')
            ->first();

        $total = (int) ($aggregate->total ?? 0);
        $done = (int) ($aggregate->done ?? 0);
        $remaining = max($total - $done, 0);
        $percent = $total > 0 ? (int) round(($done / $total) * 100) : 0;

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $percent,
            'remaining' => $remaining,
            'date' => $date,
        ];
    }

    public function buildMessage(
        int $percent,
        int $remaining,
        string $context = 'report',
        ?array $extras = []
    ): array {
        $direction = $extras['direction'] ?? 'forward';

        if ($direction === 'backward') {
            $message = $this->randomMessage('regress', $percent, $remaining);

            if ($message) {
                return $this->formatMessage($message);
            }
        }

        $levelKey = $this->resolveLevelKey($percent);

        $message = $this->randomMessage($levelKey, $percent, $remaining)
            ?: $this->fallbackProgressMessage($percent, $remaining);

        $contextMessage = $this->randomMessage($context, $percent, $remaining);

        if ($contextMessage) {
            $message = $contextMessage . ' ' . $message;
        }

        $final = $this->randomOpener()
            . ' '
            . $message
            . ' '
            . $this->randomCloser();

        return $this->formatMessage($final);
    }

    protected function resolveLevelKey(int $percent): string
    {
        if ($percent >= 100) {
            return 'full';
        }

        if ($percent >= 70) {
            return 'high';
        }

        if ($percent >= 40) {
            return 'mid';
        }

        return 'low';
    }

    protected function randomMessage(string $key, int $percent, int $remaining): ?string
    {
        $bank = $this->messages[$key] ?? [];

        if (empty($bank)) {
            return null;
        }

        return str_replace(
            ['%{percent}', '%{remaining}'],
            [$percent, $remaining],
            Arr::random($bank)
        );
    }

    protected function fallbackProgressMessage(int $percent, int $remaining): string
    {
        return "تا الان {$percent}٪ پیش رفتی و {$remaining} تسک باقی مونده.";
    }

    protected function randomOpener(): string
    {
        return Arr::random([
            'آفرین 👏',
            'دمت گرم 💪',
            'ادامه بده 🌟',
            'هیچ‌چیز نمی‌تونه جلوتو بگیره 🚀',
        ]);
    }

    protected function randomCloser(): string
    {
        return Arr::random([
            'تو قهرمان خودتی 👑',
            'به خودت افتخار کن 💫',
            'هر روز بهتر از دیروز 🌿',
        ]);
    }

    protected function formatMessage(string $text): array
    {
        $base = 3000;
        $extraPerChar = 80;
        $length = mb_strlen($text);
        $duration = min(15000, max($base, $base + $length * $extraPerChar));

        return [
            'text' => trim($text),
            'duration' => $duration,
        ];
    }
}
