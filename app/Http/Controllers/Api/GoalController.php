<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\StoreGoalTasksRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalResource;
use App\Http\Resources\TaskResource;
use Morilog\Jalali\Jalalian;
use App\Models\Task;
use App\Models\Goal; // اضافه شد برای متدهای show/update/destroy و چک مالکیت
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // اضافه شد برای مدیریت تراکنش

class GoalController extends Controller
{
    // فرض بر این است که یک BaseController متد successResponse را تعریف کرده است.
    public function __construct(private GoalRepository $goalRepo) {}

    /**
     * Retrieves all goals for the authenticated user, optionally without children.
     */
    public function index(Request $request): JsonResponse
    {
        // در GoalRepository باید فیلتر auth()->id() اعمال شود.
        if ($request->has('without_children') && $request->get('without_children')) {
            $goals = $this->goalRepo->allWithoutChildren();
        } else {
            // بارگذاری رابطه children برای نمایش سلسله مراتبی
            $goals = $this->goalRepo->all();
        }
        return $this->successResponse(GoalResource::collection($goals));
    }

    /**
     * Creates a new goal for the authenticated user.
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        $goal = $this->goalRepo->create($data);
        $goal->loadCount(['children', 'tasks']);
        // ✅ اصلاح: اطمینان از بازگرداندن GoalResource پس از ساخت
        return $this->successResponse(new GoalResource($goal), 201);
    }

    /**
     * Retrieves a single goal by ID, with strict authorization check.
     */
    public function show($id): JsonResponse
    {
        // ✅ اصلاح امنیتی: اطمینان از اینکه کاربر فقط به هدف خود دسترسی دارد.
        $goal = Goal::where('user_id', auth()->id())
            ->findOrFail($id);

        // ✅ اصلاح: بازگرداندن GoalResource برای فرمت‌بندی استاندارد
        return $this->successResponse(new GoalResource($goal));
    }

    /**
     * Updates an existing goal by ID, with strict authorization check.
     */
    public function update(UpdateGoalRequest $request, $id): JsonResponse
    {
        // ✅ اصلاح امنیتی: اطمینان از اینکه کاربر فقط هدف خود را به‌روز می‌کند.
        $goal = Goal::where('user_id', auth()->id())
            ->findOrFail($id);

        $this->goalRepo->update($goal->id, $request->validated());

        // بازیابی هدف به‌روز شده و بازگرداندن GoalResource
        return $this->successResponse(new GoalResource($goal->refresh()));
    }

    /**
     * Deletes a goal by ID, with strict authorization check.
     */
    public function destroy($id): JsonResponse
    {

        // ✅ اصلاح امنیتی: اطمینان از اینکه کاربر فقط هدف خود را حذف می‌کند.
        $goal = Goal::with('children')->where('user_id', auth()->id())
            ->findOrFail($id);
        if ($goal->children()->exists()) {
            return $this->errorResponse([
                'message' => 'نمی‌توان هدف والد را حذف کرد. ابتدا زیرهدف‌های آن را حذف کنید.'
            ], code: 422);
        }
        // ✅ اصلاح: استفاده از تراکنش برای اطمینان از حذف کامل و ایمن
        DB::transaction(function () use ($goal) {
            // فرض بر اینکه Task ها به صورت Cascade حذف می‌شوند. اگر نه، باید اینجا tasks را حذف کرد.
            $this->goalRepo->delete($goal->id);
        });

        return $this->successResponse(null, 204); // پاسخ 204 No Content برای حذف موفق
    }



    public function tasks(StoreGoalTasksRequest $request): JsonResponse
    {
        // 1. امنیت: فقط صاحب هدف
        $data = $request->validated();
        $goalId = $data['goal_id'];
        
        // ✅ بازیابی هدف برای چک مالکیت و استفاده بعدی
        $goal = Goal::where('user_id', auth()->id())->findOrFail($goalId);
        
        // 2. آماده سازی پارامترها
        $startDate = Jalalian::fromFormat('Y/m/d', $data['start_date'])->toCarbon();
        $duration = (int) $data['duration'];
        $pattern  = $data['pattern'] ?? 'daily';
        
        $daysOfWeek = $data['days_of_week'] ?? [];
        $isValidWeekly = $pattern === 'weekly' && !empty($daysOfWeek);

        $step = 1;
        $offset = 0;

        if (in_array($pattern, ['alternate_odd', 'alternate_even'], true)) {
            $step = 2; // یک‌روزدرمیان
            $offset = $pattern === 'alternate_even' ? 1 : 0;
        }

        // 3. تولید تاریخ‌ها (منطق بدون تغییر)
        $allDates = [];
        $dayMapToCarbon = [
            'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6
        ];
        $carbonDaysFilter = array_map(fn($d) => $dayMapToCarbon[$d], $daysOfWeek);

        for ($i = 0; $i < $duration; $i++) {
            $currentDate = $startDate->copy()->addDays($i);
            $shouldCreate = false;

            if ($isValidWeekly) {
                if (in_array($currentDate->dayOfWeek, $carbonDaysFilter)) {
                    $shouldCreate = true;
                }
            } else {
                if (($i % $step) === $offset) {
                    $shouldCreate = true;
                }
            }

            if ($shouldCreate) {
                $allDates[] = $currentDate->toDateString();
            }
        }

        // 4. مدیریت درج و تکراری‌ها (بدون تغییر)
        $tasksToInsert = [];
        $existingDates = [];

        DB::transaction(function () use ($goalId, $allDates, &$tasksToInsert, &$existingDates) {
            $existingDates = Task::where('goal_id', $goalId)
                ->whereIn('day', $allDates)
                ->pluck('day')
                ->toArray();

            $newDates = array_diff($allDates, $existingDates);

            foreach ($newDates as $date) {
                $tasksToInsert[] = [
                    'goal_id'    => $goalId,
                    'title'      => 'تسک روزانه',
                    'is_done'    => false,
                    'day'        => $date,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($tasksToInsert)) {
                Task::insert($tasksToInsert);
            }
        });

      $goal = Goal::query()
    ->where('user_id', auth()->id())
    ->where('id', $goalId)
    ->with(['tasks']) // ⬅️ بارگذاری رابطه 'tasks' که توسط Accessor 'stats' استفاده می‌شود.
    ->firstOrFail();
        
        // 5b. پاسخ نهایی شامل هدف
        return $this->successResponse([
            'message'        => 'تسک‌ها با موفقیت ایجاد شدند و آمار هدف بروزرسانی شد.',
            'inserted_count' => count($tasksToInsert),
            'skipped_count'  => count($existingDates),
            // ✅ فیلد کلیدی: هدف به‌روز شده برای Store Pinia
            'goal'           => new GoalResource($goal), 
        ], 201);
    }
    public function getParentableGoals(): JsonResponse
    {
        $goals = Goal::query()
            ->where('user_id', auth()->id())
            ->where(function ($q) {
                $q->whereHas('children')        // اهداف والد واقعی
                    ->orWhereDoesntHave('tasks'); // یا اهداف بدون تسک
            })
            ->withCount(['children', 'tasks'])
            ->orderBy('title')
            ->get(['id', 'title']);

        return $this->successResponse($goals);
    }
}
