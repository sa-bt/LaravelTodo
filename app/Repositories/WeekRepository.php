<?php

namespace App\Repositories;


use App\Repositories\AbstractRepository;
use App\Models\Week;

class WeekRepository extends AbstractRepository
{
    public function __construct(Week $week)
    {
        $this->model = $week;
    }
    public function getGoalsWithStatus($weekId)
    {
        return $this->model->with('goals')->findOrFail($weekId)->goals;
    }

    // Add goal-specific methods here if needed
}
