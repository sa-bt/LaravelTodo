<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Repositories\WeekRepository;
use App\Http\Resources\WeekResource;
use App\Traits\ApiResponseTrait;

class WeekController extends Controller
{

    protected $weekRepository;

    public function __construct(WeekRepository $weekRepository)
    {
        $this->weekRepository = $weekRepository;
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
}

