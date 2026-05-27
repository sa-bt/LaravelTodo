<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Repositories\GoalRepository;
use App\Repositories\TaskRepository;
use App\Services\ProgressMessageService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function __construct(
        private TaskRepository $repository,
        private GoalRepository $goalRepository
    ) {}

    /**
     * دریافت تسک‌ها بر اساس بازه تاریخ میلادی
     *
     * قرارداد تاریخ:
     * Frontend UI: شمسی
     * API / Backend / Database: میلادی با فرمت Y-m-d
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $goals = $user->goals()->pluck('id')->toArray();

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        if (!$startDate || !$endDate) {
            return $this->errorResponse(
                errors: ['start_date and end_date are required.'],
                messageKey: 'validation_error',
                code: 422
            );
        }

        $start = Carbon::createFromFormat('Y-m-d', $startDate)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $endDate)->endOfDay();

        $tasks = $this->repository->allWithDate($goals, $start, $end);

        return $this->successResponse(TaskResource::collection($tasks));
    }

    /**
     * ایجاد تسک جدید
     *
     * day باید از فرانت به صورت میلادی Y-m-d ارسال شود.
     */
    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $goalId = $data['goal_id'] ?? null;

        if (!$goalId || auth()->user()->goals()->where('id', $goalId)->doesntExist()) {
            return $this->errorResponse(
                errors: ['Goal not found or unauthorized.'],
                messageKey: 'forbidden',
                code: 403
            );
        }

        if (array_key_exists('day', $data)) {
            $data['day'] = Carbon::createFromFormat('Y-m-d', $data['day'])->toDateString();
        }

        if (array_key_exists('is_done', $data)) {
            $data['is_done'] = (bool) $data['is_done'];
        }

        $task = $this->repository->create($data);

        return $this->successResponse(
            data: new TaskResource($task),
            messageKey: 'success',
            code: 201
        );
    }

    public function show($id): JsonResponse
    {
        $task = $this->repository->find($id);
        $user = auth()->user();

        if (!$task || !$task->goal || $task->goal->user_id !== $user->id) {
            return $this->errorResponse(
                errors: ['Task not found or unauthorized.'],
                messageKey: 'forbidden',
                code: 403
            );
        }

        return $this->successResponse(
            data: new TaskResource($task)
        );
    }

    /**
     * آپدیت partial تسک
     *
     * نکته مهم:
     * - برای toggle فقط is_done ارسال شود.
     * - برای تغییر تاریخ فقط day ارسال شود.
     * - برای ویرایش عنوان فقط title ارسال شود.
     * - هیچ‌وقت از فرانت کل task با ...task ارسال نشود.
     */
    public function update(UpdateTaskRequest $request, $id): JsonResponse
    {
        $data = $request->validated();

        $task = $this->repository->find($id);
        $user = auth()->user();

        if (!$task || !$task->goal || $task->goal->user_id !== $user->id) {
            return $this->errorResponse(
                errors: ['Task not found or unauthorized.'],
                messageKey: 'forbidden',
                code: 403
            );
        }

        $oldStatus = (bool) $task->is_done;
        $oldDay = Carbon::parse($task->day)->toDateString();

        $hasStatusUpdate = array_key_exists('is_done', $data);
        $hasDayUpdate = array_key_exists('day', $data);

        if ($hasStatusUpdate) {
            $data['is_done'] = (bool) $data['is_done'];
        }

        if ($hasDayUpdate) {
            $data['day'] = Carbon::createFromFormat('Y-m-d', $data['day'])->toDateString();
        }

        $newStatusFromRequest = $hasStatusUpdate ? (bool) $data['is_done'] : $oldStatus;
        $newDayFromRequest = $hasDayUpdate ? $data['day'] : $oldDay;

        $statusChanged = $hasStatusUpdate && $oldStatus !== $newStatusFromRequest;

        /*
        |--------------------------------------------------------------------------
        | تاریخ مبنای محاسبه پیشرفت
        |--------------------------------------------------------------------------
        | اگر وضعیت انجام‌شدن تغییر کند، پیام پیشرفت برای همان روز مؤثر محاسبه می‌شود.
        | اگر فقط day تغییر کند، پیام پیشرفت ساخته نمی‌شود.
        */
        $progressDay = $newDayFromRequest;

        $progressMessage = null;
        $displayDuration = 4000;

        $service = new ProgressMessageService();

        $beforePercent = null;

        if ($statusChanged) {
            $progressBefore = $service->getUserProgressForDate($user->id, $progressDay);
            $beforePercent = $progressBefore['percent'];
        }

        $task = $this->repository->update($id, $data);

        if ($statusChanged) {
            $progressAfter = $service->getUserProgressForDate($user->id, $progressDay);

            $afterPercent = $progressAfter['percent'];
            $remaining = $progressAfter['remaining'];

            $direction = $newStatusFromRequest ? 'forward' : 'backward';
            $context = $direction === 'forward' ? 'report' : 'regress';

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
                Log::error('❌ Failed to generate progress message', [
                    'user_id' => $user->id,
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->successResponse(
            data: [
                'task' => new TaskResource($task),
                'message' => $progressMessage,
                'duration' => $displayDuration,
            ]
        );
    }

    public function destroy($id): JsonResponse
    {
        $task = $this->repository->find($id);
        $user = auth()->user();

        if (!$task || !$task->goal || $task->goal->user_id !== $user->id) {
            return $this->errorResponse(
                errors: ['Task not found or unauthorized.'],
                messageKey: 'forbidden',
                code: 403
            );
        }

        $this->repository->delete($id);

        return response()->json(null, 204);
    }

 }
