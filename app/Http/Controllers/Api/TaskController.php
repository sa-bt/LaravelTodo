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

        // 1️⃣ دریافت تسک و کاربر
        $task = $this->repository->find($id);
        $user = auth()->user();

        if (!$task || $task->goal->user_id !== $user->id) {
            return $this->errorResponse(
                errors: ['Task not found or unauthorized.'],
                messageKey: 'forbidden',
                code: 403
            );
        }

        // 2️⃣ تبدیل تاریخ شمسی به میلادی
        $data['day'] = \Morilog\Jalali\Jalalian::fromFormat('Y-m-d', $data['day'])->toCarbon();

        // 3️⃣ گرفتن وضعیت پیشرفت قبل از تغییر
        $service = new \App\Services\ProgressMessageService();
        $progressBefore = $service->getUserProgressForDate($user->id, $data['day']);
        $beforePercent  = $progressBefore['percent'];

        // 4️⃣ انجام آپدیت تسک
        $oldStatus = $task->is_done;
        $task = $this->repository->update($id, $data);
        $newStatus = $task->is_done;

        // 5️⃣ گرفتن وضعیت بعد از تغییر
        $progressAfter = $service->getUserProgressForDate($user->id, $data['day']);
        $afterPercent  = $progressAfter['percent'];
        $remaining     = $progressAfter['remaining'];

        // 6️⃣ تشخیص جهت تغییر (forward/backward)
        $direction = $afterPercent > $beforePercent ? 'forward' : 'backward';
        $context   = $direction === 'forward' ? 'report' : 'regress';

        // 7️⃣ ساخت پیام داینامیک بر اساس وضعیت
        try {
            $result = $service->buildMessage(
                $afterPercent,
                $remaining,
                $context,
                ['direction' => $direction]
            );

            $progressMessage = $result['text'];
            $displayDuration = $result['duration'];


        } catch (\Throwable $e) {
            \Log::error("❌ Failed to generate progress message", [
                'user_id' => $user->id,
                'task_id' => $task->id,
                'error'   => $e->getMessage(),
            ]);
            $progressMessage = null;
            $displayDuration = 4000; // پیش‌فرض
        }

        // 8️⃣ بازگشت پاسخ استاندارد
        return $this->successResponse(
            data: [
                'task'     => new \App\Http\Resources\TaskResource($task),
                'message'  => $progressMessage,
                'duration' => $displayDuration,
            ],
        );
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
