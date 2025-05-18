<?php

namespace App\Repositories;


use App\Repositories\AbstractRepository;
use App\Models\GoalWeek;

class GoalWeekRepository extends AbstractRepository
{
    public function __construct(GoalWeek $goal_week)
    {
        $this->model = $goal_week;
    }
     public function all()
    {
        return GoalWeek::with(['goal', 'week'])->get();
    }

    public function find($id)
    {
        return GoalWeek::with(['goal', 'week'])->find($id);
    }



    // Add goal-specific methods here if needed
}
