<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Repositories\TaskRepository;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class ActivityController extends Controller
{
    public function index($year)
{
    $start = Jalalian::fromFormat('Y-m-d', $year . '-01-01')->toCarbon();

    // بررسی کبیسه بودن سال شمسی
    $isLeap = ((($year * 8) + 29) % 33) < 8;
    $endDay = $isLeap ? 30 : 29;

    // پایان سال
    $end = Jalalian::fromFormat('Y-m-d', $year . "-12-{$endDay}")->toCarbon();

    // گرفتن تسک‌ها و گروه‌بندی بر اساس روز
    $tasks = Task::whereBetween('day', [$start, $end])
        ->selectRaw('day, COUNT(*) as total, SUM(is_done = 1) as done')
        ->groupBy('day')
        ->get()
        ->keyBy(function ($item) {
            return Jalalian::fromDateTime($item->day)->format('Y-n-j'); // کلید: 1404-1-1
        });

    // حالا کل روزهای سال رو بسازیم
    $days = [];
    $cursor = $start->copy();
    while ($cursor <= $end) {
        $jalali = Jalalian::fromCarbon($cursor);
        $key = $jalali->format('Y-n-j');

        if ($tasks->has($key)) {
            $days[$key] = [
                'total' => (int) $tasks[$key]->total,
                'done'  => (int) $tasks[$key]->done,
            ];
        } else {
            $days[$key] = [
                'total' => 0,
                'done'  => 0,
            ];
        }

        $cursor->addDay();
    }

    return response()->json([
        'status' => true,
        'data'   => $days,
    ]);
}
}
