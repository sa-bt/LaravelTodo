<?php

namespace App\Services;

use App\Models\Week;


class WeekService
{
    public static function calculateResult(Week $week): int
    {
        $goals = $week->goalWeeks;

        if ($goals->count() === 0) {
            return 0;
        }

        $doneCount = $goals->where('status', 'done')->count();

        return round(($doneCount / $goals->count()) * 100);
    }
    public function mapResultToColor(?int $result): ?string
    {
        if ($result === null) {
            return null; // 👈 بدون رنگ برای هفته‌های خالی یا صفر
        }

        return match (true) {
            $result <= 15 => 'red-dark',
            $result <= 25 => 'red',
            $result <= 35 => 'red-light',
            $result <= 40 => 'yellow-dark',
            $result <= 45 => 'yellow',
            $result <= 50 => 'yellow-light',
            $result <= 57 => 'green-light',
            $result <= 66 => 'green',
            $result <= 75 => 'green-dark',
            $result <= 82 => 'blue-light',
            $result <= 90 => 'blue',
            $result <= 100 => 'blue-dark',
            default => null,
        };
    }
}
