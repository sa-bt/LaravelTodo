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
use App\Repositories\TaskRepository;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;

class TaskController extends Controller
{
    public function __construct(private TaskRepository $repository, private GoalRepository $goalRepository) {}

    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $goals = $user->goals->pluck('id')->toArray();;
        $start = Jalalian::fromFormat('Y-m-d', $request->input('start_date'))
            ->toCarbon();

        $end = Jalalian::fromFormat('Y-m-d', $request->input('end_date'))
            ->toCarbon();
        // dd($start, $end);
        // $start = $request->input('start_date');
        $tasks = $this->repository->allWithDate($goals, $start, $end);
        return $this->successResponse(TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['day'] = Jalalian::fromFormat('Y-m-d', $data['day'])
            ->toCarbon();
        $task = $this->repository->create($data);
        return $this->successResponse(new TaskResource($task), 'success', 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json($this->goalRepo->find($id));
    }

    public function update(UpdateTaskRequest $request, $id): JsonResponse
    {
        $data=$request->validated();
        $data['day'] = Jalalian::fromFormat('Y-m-d', $data['day'])
            ->toCarbon();
        $task = $this->repository->update($id, $data);
        return $this->successResponse(new TaskResource($task), 'success', 200);
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
}
