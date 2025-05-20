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
    public static function mapResultToColor($result): string
    {
        if ($result < 15) return 'red-dark';
        if ($result < 25) return 'red';
        if ($result < 35) return 'red-light';
        if ($result < 40) return 'yellow-dark';
        if ($result < 45) return 'yellow';
        if ($result < 50) return 'yellow-light';
        if ($result < 57) return 'green-light';
        if ($result < 66) return 'green';
        if ($result < 75) return 'green-dark';
        if ($result < 82) return 'blue-light';
        if ($result < 90) return 'blue';
        return 'blue-dark';
    }
}
