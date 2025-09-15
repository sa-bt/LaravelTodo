<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\StoreGoalTasksRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

class GoalController extends Controller
{
    public function __construct(private GoalRepository $goalRepo) {}

    public function index(Request $request): JsonResponse
    {
        if ($request->has('without_children') && $request->get('without_children')) {
            $goals = $this->goalRepo->allWithoutChildren();
        } else {
            $goals = $this->goalRepo->all();
        }
        return $this->successResponse(GoalResource::collection($goals));
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $goal = $this->goalRepo->create($data);
        return $this->successResponse($goal, 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json($this->goalRepo->find($id));
    }

    public function update(UpdateGoalRequest $request, $id): JsonResponse
    {
        $goal = $this->goalRepo->update($id, $request->validated());
        return $this->successResponse($goal);
    }

    public function destroy($id): JsonResponse
    {
        $this->goalRepo->delete($id);
        return response()->json(null, 204);
    }
    public function goalsByWeek($weekId)
    {
        $goalWeeks = GoalWeek::where('week_id', $weekId)->with('goal')->get();

        $data = $goalWeeks->map(function ($gw) {
            return [
                'id' => $gw->goal->id,
                'title' => $gw->goal->title,
                'status' => $gw->status,
                'note' => $gw->note,
            ];
        });

        return $this->successResponse([
            'week_id' => $weekId,
            'title' => optional($goalWeeks->first()->week)->title,
            'goals' => $data,
        ]);
    }

    public function tasks(StoreGoalTasksRequest $request): JsonResponse
    {
        $data = $request->validated();
        $goalId = $data['goal_id'];
        $startDate = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $data['start_date'])
            ->toCarbon();
        $duration = $data['duration'];
        $allDates = [];
        for ($i = 0; $i < $duration; $i++) {
            $allDates[] = $startDate->copy()->addDays($i)->toDateString();
        }

        // گرفتن تاریخ‌هایی که قبلا وجود دارند
        $existingDates = Task::where('goal_id', $goalId)
            ->whereIn('day', $allDates)
            ->pluck('day')
            ->toArray();

        // فیلتر کردن فقط تاریخ‌های جدید
        $newDates = array_diff($allDates, $existingDates);

        // آماده‌سازی برای bulk insert
        $tasksToInsert = [];
        foreach ($newDates as $date) {
            $tasksToInsert[] = [
                'goal_id' => $goalId,
                'day' => $date,
                'is_done' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // درج در دیتابیس فقط اگر تاریخ جدید وجود داشته باشد
        if (!empty($tasksToInsert)) {
            Task::insert($tasksToInsert);
        }

        return $this->successResponse([
            'message' => 'تسک‌ها ایجاد شدند',
            'inserted_count' => count($tasksToInsert),
            'skipped_count' => count($existingDates),
            'all_dates' => $allDates,
            'existing_dates' => $existingDates,
            'new_dates' => $newDates,
        ], 201);
    }
}
