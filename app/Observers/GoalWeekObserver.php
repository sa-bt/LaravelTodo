<?php

namespace App\Observers;

use App\Models\GoalWeek;
use App\Services\WeekService;

class GoalWeekObserver
{
    public function updated(GoalWeek $goalWeek): void
    {
        if ($goalWeek->wasChanged('status')) {
            $week = $goalWeek->week;

            if ($week) {
                $result = WeekService::calculateResult($week);
                $week->update(['result' => $result]);
            }
        }
    }

    public function created(GoalWeek $goalWeek): void
    {
        $week = $goalWeek->week;

        if ($week) {
            $result = WeekService::calculateResult($week);
            $week->update(['result' => $result]);
        }
    }

    public function deleted(GoalWeek $goalWeek): void
    {
        $week = $goalWeek->week;

        if ($week) {
            $result = WeekService::calculateResult($week);
            $week->update(['result' => $result]);
        }
    }
}
