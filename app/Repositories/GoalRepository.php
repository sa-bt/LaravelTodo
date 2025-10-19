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

    public function all()
    {
        $today = now()->toDateString();

        return $this->model
            ->with([
                'parent',
                'children',
                // فقط تسک‌های تا امروز را بارگذاری کن
                'tasks' => fn($q) => $q->whereDate('day', '<=', $today)->orderBy('day', 'asc'),
            ])
            ->withCount('children')
            ->where('user_id', auth()->id())
            ->get();
    }

    public function allWithoutChildren()
    {
        return $this->model
            ->with('parent')
            ->withCount('children')
            ->doesntHave('children')
            ->where('user_id', auth()->id())
            ->get();
    }
}
