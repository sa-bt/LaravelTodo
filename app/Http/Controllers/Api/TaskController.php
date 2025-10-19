<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\TaskResource;
use App\Repositories\TaskRepository;
use Illuminate\Http\Request;
use Morilog\Jalali\Jalalian;
use App\Models\Task;
use App\Models\User;
use App\Jobs\SendProgressNotificationJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log; // Added back for Log in Job logic

class TaskController extends Controller
{
    public function __construct(private TaskRepository $repository, private GoalRepository $goalRepository) {}

    /**
     * ✅ متد Index به حالت اولیه برگردانده شد.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $goals = $user->goals->pluck('id')->toArray();
        $start = Jalalian::fromFormat('Y-m-d', $request->input('start_date'))
            ->toCarbon();

        $end = Jalalian::fromFormat('Y-m-d', $request->input('end_date'))
            ->toCarbon();

        // استفاده مجدد از متد Repository شما
        $tasks = $this->repository->allWithDate($goals, $start, $end);

        return $this->successResponse(TaskResource::collection($tasks));
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Authorization Check: Ensure goal belongs to the user
        $goalId = $data['goal_id'] ?? null;
        if ($goalId && auth()->user()->goals()->where('id', $goalId)->doesntExist()) {
            return response()->json(['message' => 'Goal not found or unauthorized.'], 403);
        }

        $data['day'] = Jalalian::fromFormat('Y-m-d', $data['day'])
            ->toCarbon();
        $task = $this->repository->create($data);
        return $this->successResponse(new TaskResource($task), 'success', 201);
    }

    public function show($id): JsonResponse
    {
        // 1. Fetch Task and authorize via Goal
        $task = $this->repository->find($id);
        if ($task && $task->goal->user_id !== auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized access.'], 403);
        }

        // Returning Goal details for consistency with original code
        return response()->json($this->goalRepository->find($task->goal_id));
    }

    public function update(UpdateTaskRequest $request, $id): JsonResponse
    {
        $data = $request->validated();

        // 1. Fetch Task and Authorize
        $task = $this->repository->find($id);

        if (!$task || $task->goal->user_id !== auth()->user()->id) {
            return response()->json(['message' => 'Task not found or unauthorized.'], 403);
        }

        // 2. Convert Jalaali Date
        $data['day'] = Jalalian::fromFormat('Y-m-d', $data['day'])
            ->toCarbon();

        // Check if the task is being marked as done
        $isBeingCompleted = isset($data['is_done']) && $data['is_done'] == true && $task->is_done == false;

        // 3. Perform the Update
        $task = $this->repository->update($id, $data);

        // --------------------------------------------------------
        // 4. Per-Task Progress Notification Logic (via Queue)
        // --------------------------------------------------------
        $user = auth()->user();

        // Assumes 'per_task_progress' is available on the User model/relation
        if ($isBeingCompleted && $user->per_task_progress) {

            // a. Calculate User's Daily Progress
            $today = $task->day->toDateString();

            $userGoalIds = $user->goals->pluck('id');

            // Find all tasks related to the user's goals for that day
            $allTasksToday = Task::whereIn('goal_id', $userGoalIds)
                ->whereDate('day', $today);

            $totalTasks = $allTasksToday->count();
            // Important: We need a fresh count of completed tasks based on the updated state
            $completedTasks = Task::whereIn('goal_id', $userGoalIds)
                ->whereDate('day', $today)
                ->where('is_done', true)
                ->count();

            // Calculate progress percentage
            $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 100;

            // b. Define Notification Content
            $notificationTitle = '🎉 پیشرفت روزانه به‌روز شد';
            $notificationBody = "تسک «{$task->title}» کامل شد! اکنون {$progress}% از اهداف تاریخ {$task->day->toDateString()} خود را تکمیل کرده‌اید.";

            // c. Send Web Push Notification via Job (Non-blocking)
            SendProgressNotificationJob::dispatch(
                $user,
                $notificationTitle,
                $notificationBody,
                $progress
            );

            // ✅ لاگ تایید Dispatch
            Log::info("SUCCESS: Notification Job Dispatched for User {$user->id}.", [
                'task_id' => $task->id,
                'progress' => $progress,
            ]);

        } else {
            // ❌ لاگ عدم Dispatch
            Log::warning("FAILURE: Notification Job NOT Dispatched.", [
                'task_id' => $task->id,
                'isBeingCompleted' => $isBeingCompleted,
                'user_setting' => $user->per_task_progress ?? 'UNDEFINED/FALSE',
            ]);
        }

        return $this->successResponse(new TaskResource($task), 'success', 200);
    }

    public function destroy($id): JsonResponse
    {
        $task = $this->repository->find($id);

        if (!$task || $task->goal->user_id !== auth()->user()->id) {
            return response()->json(['message' => 'Task not found or unauthorized.'], 403);
        }

        $this->repository->delete($id);
        return response()->json(null, 204);
    }

    // متد goalsByWeek شما دست نخورده باقی می‌ماند.
    public function goalsByWeek($weekId)
    {
        // ... (منطق شما) ...
    }
}
