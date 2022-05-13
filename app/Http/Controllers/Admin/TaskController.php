<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TaskResource;
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
    public function index()
    {
        $tasks = $this->repository->all();
        return $this->successResponse(TaskResource::collection($tasks), $this->fetched, Response::HTTP_OK);
    }


    public function create()
    {
        //
    }


    public function store(Request $request)
    {
        //
    }

    public function show(Task $task)
    {
        //
    }

    public function edit(Task $task)
    {
        //
    }

    public function update(Request $request, Task $task)
    {
        //
    }

    public function destroy(Task $task)
    {
        //
    }

    public function deleredTasks()
    {

        $tasks=$this->repository->getAllTrashed();
        return $this->successResponse(TaskResource::collection($tasks), $this->fetched, Response::HTTP_OK);
    }
}
