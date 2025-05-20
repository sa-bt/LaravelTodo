<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalResource;


class GoalController extends Controller
{
    public function __construct(private GoalRepository $goalRepo) {}

    public function index(): JsonResponse
    {
        $goals = $this->goalRepo->all();

        return $this->successResponse(GoalResource::collection($goals));
    }

    public function store(StoreGoalRequest $request): JsonResponse
    {
        $goal = $this->goalRepo->create($request->validated());
        return response()->json($goal, 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json($this->goalRepo->find($id));
    }

    public function update(UpdateGoalRequest $request, $id): JsonResponse
    {
        $goal = $this->goalRepo->update($id, $request->validated());
        return response()->json($goal);
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

