<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskRequest;
use App\Http\Resources\User\TaskResource;
use App\Interfaces\TaskInterface;
use App\Models\Task;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TaskController extends Controller
{
    protected $repository;

    public function __construct(TaskInterface $repository)
    {
        $this->repository = $repository;
    }

    public function index(): \Illuminate\Http\JsonResponse
    {
        $tasks = auth()->user()->tasks;
        return $this->successResponse(TaskResource::collection($tasks), $this->fetched, Response::HTTP_OK);
    }

    public function store(TaskRequest $request)
    {
        $values = $request->all();
        try {
            $record = auth()->user()->tasks()->create($values);
            return $this->successResponse($record);

        } catch (\Exception $exception) {
            return $this->errorResponse('Tasks', $exception->getMessage());
        }
    }

    public function show(Task $task)
    {
        //
    }


    public function update(Request $request, Task $task)
    {
        $values = $request->all();
        try {
            $task->update($values);
            return $this->successResponse($task->refresh());

        } catch (\Exception $exception) {
            return $this->errorResponse('Tasks', $exception->getMessage());
        }
    }


    public function destroy(Task $task)
    {
        try {
            $task->delete();
            return $this->successResponse([], $this->deleted, Response::HTTP_OK);

        } catch (\Exception $exception) {
            return $this->errorResponse('Tasks', $exception->getMessage());
        }
    }
}
