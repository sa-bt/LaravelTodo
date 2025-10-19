<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 💡 ما از Schema::table برای اضافه کردن فیلدها استفاده می‌کنیم، اما
        // بهتر است آنها را پشت سر هم اضافه کنیم تا ترتیب بعد از 'remember_token' حفظ شود.
        Schema::table('users', function (Blueprint $table) {

            // گزارش روزانه
            $table->boolean('daily_report')->default(false)->after('remember_token');
            $table->time('report_time')->default('08:00:00')->after('daily_report');

            // یادآوری تسک‌ها
            $table->boolean('task_reminder')->default(false)->after('report_time');
            $table->time('task_reminder_time')->default('09:00:00')->after('task_reminder');

            // ✅ اعلان پیشرفت لحظه‌ای (فیلد جدید)
            $table->boolean('per_task_progress')->default(false)->after('task_reminder_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // برای بازگشت، همه فیلدهای اضافه شده را حذف می‌کنیم.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'daily_report',
                'report_time',
                'task_reminder',
                'task_reminder_time',
                'per_task_progress' // فیلد جدید
            ]);
        });
    }
};
