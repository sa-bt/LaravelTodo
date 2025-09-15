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

    // گرفتن همه Goalها با parent، children و tasks (تا امروز)
    $goals = $this->model
        ->with([
            'parent',
            'children',
            'tasks' => fn($query) => $query->whereDate('day', '<=', $today)->orderBy('day', 'asc')
        ])
        ->where('user_id', auth()->id())
        ->get();

    // محاسبه stats برای هر Goal
    $goals->transform(function($goal) {
        $goalTasks = $goal->tasks ?? collect(); // اگر تسکی وجود نداشت، Collection خالی
        $goal->stats = $this->calculateStatsFromTasks($goalTasks);
        return $goal;
    });

    return $goals;
}

protected function calculateStatsFromTasks($tasks)
{
    if ($tasks->isEmpty()) {
        return [
            'total' => 0,
            'done' => 0,
            'max_streak_success' => ['length' => 0, 'start' => null, 'end' => null],
            'max_streak_fail' => ['length' => 0, 'start' => null, 'end' => null],
        ];
    }

    $totalTasks = $tasks->count();
    $doneTasks = $tasks->where('is_done', true)->count();

    $maxDoneStreak = ['length' => 0, 'start' => null, 'end' => null];
    $maxFailStreak = ['length' => 0, 'start' => null, 'end' => null];

    $currentStreak = ['status' => null, 'length' => 0, 'start' => null, 'end' => null];

    foreach ($tasks as $task) {
        $status = $task->is_done ? 'done' : 'fail';

        if ($currentStreak['status'] === $status) {
            $currentStreak['length']++;
            $currentStreak['end'] = $task->day;
        } else {
            $currentStreak['status'] = $status;
            $currentStreak['length'] = 1;
            $currentStreak['start'] = $task->day;
            $currentStreak['end'] = $task->day;
        }

        if ($status === 'done' && $currentStreak['length'] > $maxDoneStreak['length']) {
            $maxDoneStreak = $currentStreak;
        } elseif ($status === 'fail' && $currentStreak['length'] > $maxFailStreak['length']) {
            $maxFailStreak = $currentStreak;
        }
    }

    return [
        'total' => $totalTasks,
        'done' => $doneTasks,
        'max_streak_success' => $maxDoneStreak,
        'max_streak_fail' => $maxFailStreak,
    ];
}

    public function allWithoutChildren()
    {
        return $this->model->with('parent')->doesntHave('children')->where('user_id', auth()->id())->get();
    }
    // Add goal-specific methods here if needed
}
