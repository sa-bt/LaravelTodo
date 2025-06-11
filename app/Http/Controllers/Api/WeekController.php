<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\WeekRepository;
use App\Http\Resources\WeekResource;
use App\Http\Resources\GoalWithStatusResource;
use App\Repositories\GoalWeekRepository;
use App\Services\WeekService;

class WeekController extends Controller
{

    protected $weekRepository;
    protected $goalWeekRepository;
    protected $weekService;

    public function __construct(WeekRepository $weekRepository, GoalWeekRepository $goalWeekRepository, WeekService $weekService)
    {
        $this->weekRepository = $weekRepository;
        $this->goalWeekRepository = $goalWeekRepository;
        $this->weekService = $weekService;
    }

    public function index()
    {
        $weeks = $this->weekRepository->all();
        return $this->successResponse(WeekResource::collection($weeks));
    }

    public function show($id)
    {
        $week = $this->weekRepository->find($id);
        if (!$week) {
            return $this->errorResponse(trans('messages.not_found'), 404);
        }

        return $this->successResponse(new WeekResource($week));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $week = $this->weekRepository->create($data);
        return $this->successResponse(new WeekResource($week), trans('messages.created'), 201);
    }

    public function update(Request $request, $id)
    {
        $week = $this->weekRepository->find($id);
        if (!$week) {
            return $this->errorResponse(trans('messages.not_found'), 404);
        }

        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after_or_equal:start_date',
        ]);

        $this->weekRepository->update($id, $data);
        return $this->successResponse(new WeekResource($week), trans('messages.updated'));
    }

    public function destroy($id)
    {
        $week = $this->weekRepository->find($id);
        if (!$week) {
            return $this->errorResponse(trans('messages.not_found'), 404);
        }

        $this->weekRepository->delete($id);
        return $this->successResponse(null, trans('messages.deleted'));
    }
    public function goals($id)
    {
        $goals = $this->weekRepository->getGoalsWithStatus($id);
        return $this->successResponse([
            'goals' => GoalWithStatusResource::collection($goals),
        ]);
    }
    public function updateGoalStatuses(Request $request, $weekId)
    {
        $request->validate([
            'statuses' => 'required|array',
            'statuses.*' => 'in:done,missed',
        ]);

        $this->goalWeekRepository->updateStatusesForWeek($weekId, $request->statuses);

        $week = $this->weekRepository->find($weekId);
        $this->weekService->updateWeekResultAndColor($week);

        return $this->successResponse(['message' => trans('messages.updated')]);
    }
}
