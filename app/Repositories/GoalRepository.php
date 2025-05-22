<?php

namespace App\Repositories;


use App\Repositories\AbstractRepository;
use App\Models\Goal;

class GoalRepository extends AbstractRepository
{
    public function __construct(Goal $goal)
    {
        $this->model = $goal;
    }

    // Add goal-specific methods here if needed
}
