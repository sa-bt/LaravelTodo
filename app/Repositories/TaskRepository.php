<?php

namespace App\Repositories;


use App\Repositories\AbstractRepository;
use App\Models\Goal;
use App\Models\Task;
use Carbon\Carbon;

class TaskRepository extends AbstractRepository
{
    public function __construct(Task $task)
    {
        $this->model = $task;
    }
    public function allWithDate($goals, $start, $end)
    {
        return $this->model->whereIn('goal_id', $goals)
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('day', [$start, $end]);
            })
           
            ->get();
    }
    public function create(array $data)
    {
        $day = Carbon::parse($data['day']);
        if (isset($data['for']) && $data['for'] > 1) {
            $data['for'] = (int)$data['for'];
            for ($i = 0; $i < $data['for']; $i++) {
                $data['day'] = $day->copy()->addDays($i);
                $this->model->firstOrCreate($data);
            }
        } else {
            unset($data['for']);
            return $this->model->firstOrCreate($data);
        }
        return;
    }

    public function whereIn($id, $array)
    {
        return $this->model->with('goal')->whereIn($id, $array)->get();
    }
    // Add goal-specific methods here if needed
}
