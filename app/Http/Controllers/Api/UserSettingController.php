<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendDailyReportNotificationJob;
use App\Jobs\SendDropAlertNotificationJob;
use App\Jobs\SendWeeklyReportNotificationJob;
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
            'task_reminder' => $user->task_reminder,
            'per_task_progress' => $user->per_task_progress, // فیلد جدید
            'weekly_report' => (bool) $user->weekly_report,
            'weekly_report_day' => (int) $user->weekly_report_day,
            'report_time' => $user->report_time ? substr($user->report_time, 0, 5) : null,
            'task_reminder_time' => $user->task_reminder_time ? substr($user->task_reminder_time, 0, 5) : null,
            'weekly_report_time' => $user->weekly_report_time ? substr($user->weekly_report_time, 0, 5) : null,
            'drop_alert' => (bool) $user->drop_alert,
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
                'report_time' => 'required',
                'task_reminder' => 'required|boolean',
                'task_reminder_time' => 'required',
                'per_task_progress' => 'required|boolean', // فیلد جدید
                // گزارش هفتگی بعداً اضافه شد، پس نبودنش در درخواست خطا نیست.
                'weekly_report' => 'sometimes|boolean',
                'weekly_report_day' => 'sometimes|integer|min:0|max:6',
                'weekly_report_time' => 'sometimes',
                // هشدار افت هم بعداً اضافه شد، پس نبودنش در درخواست خطا نیست.
                'drop_alert' => 'sometimes|boolean',
            ]);

            // ذخیره‌سازی داده‌ها در مدل کاربر
            $user = $request->user();
            $user->update($data);

            /*
             * پاک کردن کلید تکرارنشدن ارسال.
             *
             * قبلاً کلیدی پاک می‌شد که هیچ‌جا نوشته نمی‌شد. یعنی کاربری که گزارش
             * را بعد از گذشتن ساعتش روشن می‌کرد، بی‌آنکه بداند تا فردا منتظر
             * می‌ماند. حالا همان کلیدی پاک می‌شود که کار ارسال می‌نویسد.
             */
            Cache::forget(SendDailyReportNotificationJob::dedupKey($user->id, now()->toDateString()));
            Cache::forget(SendWeeklyReportNotificationJob::dedupKey($user->id, now()->toDateString()));
            Cache::forget(SendDropAlertNotificationJob::dedupKey($user->id));
            Cache::forget("task_reminder_sent:{$user->id}:" . now()->toDateString());

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
