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
use App\Models\Task;
use App\Models\Goal; // اضافه شد برای متدهای show/update/destroy و چک مالکیت
use App\Models\GoalWeek; // فرض بر وجود این مدل برای متد goalsByWeek
use App\Models\Week; // فرض بر وجود این مدل برای متد goalsByWeek
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
        $goal = Goal::where('user_id', auth()->id())
            ->findOrFail($id);

        // ✅ اصلاح: استفاده از تراکنش برای اطمینان از حذف کامل و ایمن
        DB::transaction(function () use ($goal) {
            // فرض بر اینکه Task ها به صورت Cascade حذف می‌شوند. اگر نه، باید اینجا tasks را حذف کرد.
            $this->goalRepo->delete($goal->id);
        });

        return $this->successResponse(null, 204); // پاسخ 204 No Content برای حذف موفق
    }

    /**
     * Retrieves goals associated with a specific Week ID (assuming GoalWeek and Week models exist).
     */
    public function goalsByWeek($weekId): JsonResponse
    {
        // GoalWeek باید یک مدل واسطه بین Goal و Week باشد.
        $goalWeeks = GoalWeek::where('week_id', $weekId)
            // ✅ اصلاح امنیتی: فیلتر کردن بر اساس مالکیت Goal
            ->whereHas('goal', function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->with(['goal', 'week'])
            ->get();

        if ($goalWeeks->isEmpty()) {
            // اگر برای این هفته و این کاربر هدفی پیدا نشد
            $week = Week::find($weekId); // تلاش برای بازیابی عنوان هفته حتی اگر هدف نباشد
            $title = $week ? $week->title : 'هفته نامشخص';

            return $this->successResponse([
                'week_id' => (int)$weekId,
                'title' => $title,
                'goals' => [],
            ]);
        }

        $data = $goalWeeks->map(function ($gw) {
            return [
                'id' => $gw->goal->id,
                'title' => $gw->goal->title,
                'status' => $gw->status,
                'note' => $gw->note,
            ];
        });

        // ✅ اطمینان از اینکه title به درستی بازیابی شود
        return $this->successResponse([
            'week_id' => (int)$weekId,
            'title' => optional($goalWeeks->first()->week)->title,
            'goals' => $data,
        ]);
    }

    /**
     * Creates tasks for a goal over a specified duration.
     */
    public function tasks(StoreGoalTasksRequest $request): JsonResponse
    {
        // امنیت: فقط صاحب هدف
        $goalId = $request->validated('goal_id');
        Goal::where('user_id', auth()->id())->findOrFail($goalId);

        $data       = $request->validated();
        $startDate  = \Morilog\Jalali\Jalalian::fromFormat('Y/m/d', $data['start_date'])->toCarbon();
        $duration   = (int) $data['duration'];

        // 🟢 مقداردهی پیش‌فرض (سازگاری عقب‌رو)
        $pattern = $data['pattern'] ?? 'daily';
        $step    = (int) ($data['step'] ?? 1);
        $offset  = (int) ($data['offset'] ?? 0);

        // اگر pattern یکی از alternate_* بود، مقادیر step/offset را همسان‌سازی کن
        if (in_array($pattern, ['alternate_odd', 'alternate_even'], true)) {
            $step   = 2;                   // یک‌روزدرمیان
            $offset = $pattern === 'alternate_even' ? 1 : 0; // even => 1 | odd => 0
        } else {
            // daily
            $step   = 1;
            $offset = 0;
        }

        // تولید تاریخ‌ها بر اساس duration، step، offset
        // duration = طول بازه به روز؛ i از 0 تا duration-1
        $allDates = [];
        for ($i = 0; $i < $duration; $i++) {
            if ($step === 1 || ($i % $step) === $offset) {
                $allDates[] = $startDate->copy()->addDays($i)->toDateString();
            }
        }

        // اگر به‌هر دلیل (مثلا duration=1 و offset=1) خالی شد، برای UX حداقلی، روز شروع را اضافه نکنیم؟ (اختیاری)
        // ترجیح: نه—همون منطق دقیق باقی بمونه. اگر خواستی، اینجا هندل کن.

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

        return $this->successResponse([
            'message'        => 'تسک‌ها با موفقیت ایجاد شدند و تکراری‌ها نادیده گرفته شدند.',
            'inserted_count' => count($tasksToInsert),
            'skipped_count'  => count($existingDates),
            // 🔎 برای دیباگ/شفافیت، ورودی‌های الگو را هم برگردان (اختیاری)
            'pattern'        => $pattern,
            'step'           => $step,
            'offset'         => $offset,
            'range_days'     => $duration,
            'generated_days' => count($allDates),
        ], 201);
    }
}
