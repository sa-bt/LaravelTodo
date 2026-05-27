<?php

namespace App\Repositories;

use App\Models\Task;
use Carbon\Carbon;

class TaskRepository extends AbstractRepository
{
    public function __construct(Task $task)
    {
        $this->model = $task;
    }

    public function allWithDate($goals, $start = null, $end = null)
    {
        return $this->model
            ->with('goal')
            ->whereIn('goal_id', $goals)
            ->when($start && $end, function ($q) use ($start, $end) {
                $q->whereBetween('day', [
                    Carbon::parse($start)->startOfDay(),
                    Carbon::parse($end)->endOfDay(),
                ]);
            })
            ->orderBy('day')
            ->get();
    }

    public function find($id)
    {
        return $this->model
            ->with('goal')
            ->find($id);
    }

    public function create(array $data)
    {
        $day = Carbon::parse($data['day'])->toDateString();

        if (isset($data['for']) && (int) $data['for'] > 1) {
            $createdTasks = collect();

            $duration = (int) $data['for'];
            unset($data['for']);

            for ($i = 0; $i < $duration; $i++) {
                $payload = $data;
                $payload['day'] = Carbon::parse($day)->addDays($i)->toDateString();

                $task = $this->model->firstOrCreate(
                    [
                        'goal_id' => $payload['goal_id'],
                        'day' => $payload['day'],
                    ],
                    $payload
                );

                $createdTasks->push($task->load('goal'));
            }

            return $createdTasks;
        }

        unset($data['for']);

        $data['day'] = $day;

        $task = $this->model->firstOrCreate(
            [
                'goal_id' => $data['goal_id'],
                'day' => $data['day'],
            ],
            $data
        );

        return $task->load('goal');
    }

    public function update($id, array $data)
    {
        $task = $this->model->findOrFail($id);

        $task->update($data);

        return $task->fresh('goal');
    }

    public function delete($id)
    {
        return $this->model->whereKey($id)->delete();
    }

    public function whereIn($id, $array)
    {
        return $this->model
            ->with('goal')
            ->whereIn($id, $array)
            ->get();
    }
}
