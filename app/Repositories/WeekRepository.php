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

    // Add goal-specific methods here if needed
}
