<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Repositories\GoalWeekRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalWeekResource;
        use App\Services\WeekService;


class GoalWeekController extends Controller
{
    public function __construct(private GoalWeekRepository $goalRepo) {}

    public function index(): JsonResponse
    {
        $goals = $this->goalRepo->all();

        return $this->successResponse(GoalWeekResource::collection($goals));
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

$week = $this->weekRepository->find($goalWeek->week_id);
$result = WeekService::calculateResult($week);
$this->weekRepository->update($week->id, ['result' => $result]);

        $goal = $this->goalRepo->update($id, $request->validated());
        return response()->json($goal);
    }

    public function destroy($id): JsonResponse
    {
        $this->goalRepo->delete($id);
        return response()->json(null, 204);
    }
}

