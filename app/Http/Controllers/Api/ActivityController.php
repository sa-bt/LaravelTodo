<?php
// app/Http/Controllers/Api/GoalController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Repositories\GoalRepository;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\GoalResource;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Repositories\TaskRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

class ActivityController extends Controller
{
   public function index($year)
    {
        // ===========================================
        // بخش ۱: آماده‌سازی داده‌های خام روزانه
        // ===========================================
        $start = Jalalian::fromFormat('Y-m-d', $year . '-01-01')->toCarbon();

        // بررسی کبیسه بودن سال شمسی
        $isLeap = ((($year * 8) + 29) % 33) < 8;
        $endDay = $isLeap ? 30 : 29;

        // پایان سال (تاریخ شمسی)
        $end = Jalalian::fromFormat('Y-m-d', $year . "-12-{$endDay}")->toCarbon();

        // گرفتن تسک‌ها و گروه‌بندی بر اساس روز
        $tasks = Task::whereBetween('day', [$start, $end])
            ->selectRaw('day, COUNT(*) as total, SUM(is_done = 1) as done')
            ->groupBy('day')
            ->get()
            ->keyBy(function ($item) {
                return Jalalian::fromDateTime($item->day)->format('Y-n-j'); // کلید: 1404-1-1
            });

        // ساخت آرایه کامل روزهای سال
        $days = [];
        $cursor = $start->copy();
        while ($cursor <= $end) {
            $jalali = Jalalian::fromCarbon($cursor);
            $key = $jalali->format('Y-n-j');

            if ($tasks->has($key)) {
                $days[$key] = [
                    'total' => (int) $tasks[$key]->total,
                    'done'  => (int) $tasks[$key]->done,
                ];
            } else {
                $days[$key] = [
                    'total' => 0,
                    'done'  => 0,
                ];
            }

            $cursor->addDay();
        }

        // ===========================================
        // بخش ۲: محاسبه‌ی گزارش‌های سالانه
        // ===========================================
        
        $todayJalali = Jalalian::fromDateTime(Carbon::now());
        $todayNumeric = (int) $todayJalali->format('Ymd');
        
        $perfectDaysCount  = 0;
        $inactiveDaysCount = 0; // ⬅️ مقداردهی اولیه برای روزهای بدون فعالیت
        $totalDoneTasks    = 0;
        $totalAllTasks     = 0; // ⬅️ مقداردهی اولیه برای کل تسک‌های تعریف شده تا امروز
        
        foreach ($days as $key => $data) {
            $keyParts = explode('-', $key);
            $keyNumeric = (int) ($keyParts[0] . str_pad($keyParts[1], 2, '0', STR_PAD_LEFT) . str_pad($keyParts[2], 2, '0', STR_PAD_LEFT));

            // فقط روزهایی که گذشته‌اند در محاسبات آماری لحاظ می‌شوند
            if ($keyNumeric > $todayNumeric) {
                continue;
            }

            $total = $data['total'];
            $done  = $data['done'];

            // ۱. روزهای کمال (Perfect Days Count)
            $isPerfectDay = ($total > 0 && $done === $total);
            if ($isPerfectDay) {
                $perfectDaysCount++;
            }

            // ۲. تعداد روزهای بدون فعالیت (Total Inactive Days)
            $isInactiveDay = ($total === 0);
            if ($isInactiveDay) {
                $inactiveDaysCount++;
            }

            // ۳. محاسبه‌ی جمع کل تسک‌ها و تسک‌های انجام شده (برای میانگین و گزارش کل)
            $totalDoneTasks += $done;
            $totalAllTasks  += $total; // ⬅️ جمع کل تسک‌های تعریف شده تا امروز
        }
        
        // محاسبه‌ی میانگین درصد تکمیل کلی
        $averageCompletionPercentage = ($totalAllTasks > 0) 
            ? round(($totalDoneTasks / $totalAllTasks) * 100, 1) // با یک رقم اعشار
            : 0;
        
        // ===========================================
        // بخش ۳: بازگرداندن داده‌ها و گزارش‌ها
        // ===========================================
        return response()->json([
            'status' => true,
            'data'   => $days,
            // گزارش‌های جدید
            'perfect_days_count'            => $perfectDaysCount,
            'average_completion_percentage' => $averageCompletionPercentage,
            'inactive_days'                 => $inactiveDaysCount, // ⬅️ گزارش جدید
            'total_tasks_year_to_date'      => $totalAllTasks,     // ⬅️ گزارش جدید
        ]);
    }
}
