<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class UserSettingController extends Controller
{
    /**
     * Retrieves the user's notification settings (GET /api/settings/notifications).
     */
    public function getSetting(Request $request)
    {
        $user = $request->user();

        // بازگرداندن تمام فیلدهای تنظیمات اعلان
        return response()->json([
            'daily_report' => $user->daily_report,
            'report_time' => $user->report_time,
            'task_reminder' => $user->task_reminder,
            'task_reminder_time' => $user->task_reminder_time,
            'per_task_progress' => $user->per_task_progress, // فیلد جدید
        ]);
    }

    /**
     * Saves the user's notification settings (POST /api/settings/notifications).
     */
    public function saveSetting(Request $request)
    {
        try {
            // اعتبارسنجی: فرمت زمان H:i (HH:MM) را می‌پذیرد
            $data = $request->validate([
                'daily_report' => 'required|boolean',
                'report_time' => 'required|date_format:H:i',
                'task_reminder' => 'required|boolean',
                'task_reminder_time' => 'required|date_format:H:i',
                'per_task_progress' => 'required|boolean', // فیلد جدید
            ]);

            // ذخیره‌سازی داده‌ها در مدل کاربر
            $request->user()->update($data);

            // پاکسازی کش‌های مربوط به اعلان‌ها
            Cache::forget("daily_report_sent:{$request->user()->id}:" . now()->toDateString());
            Cache::forget("task_reminder_sent:{$request->user()->id}:" . now()->toDateString());

            return response()->json(['success' => true, 'setting' => $data]);

        } catch (ValidationException $e) {
            // اگر خطای اعتبارسنجی رخ داد، خطاها را با کد 422 برگردان
            return response()->json([
                'message' => 'Validation Errors',
                'status' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'An unexpected error occurred.'], 500);
        }
    }
}
