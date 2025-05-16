<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;

use Illuminate\Http\Request;

class GoalController extends Controller
{
    use ApiResponse;

    public function __construct(private GoalRepositoryInterface $goalRepo) {}

    public function index()
    {
        $goals = $this->goalRepo->getUserGoalsWithChildren(Auth::id());
        return $this->successResponse($goals);
    }

    public function store(StoreGoalRequest $request)
    {
        $goal = $this->goalRepo->create(array_merge(
            $request->validated(),
            ['user_id' => Auth::id()]
        ));

        return $this->successResponse($goal, __('messages.created'), 201);
    }

    public function update(UpdateGoalRequest $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $updated = $this->goalRepo->update($goal, $request->validated());
        return $this->successResponse($updated, __('messages.updated'));
    }

    public function destroy(Goal $goal)
    {
        $this->authorize('delete', $goal);
        $this->goalRepo->delete($goal);
        return $this->successResponse(null, __('messages.deleted'));
    }
}
