<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('report_time')->default('08:00:00')->after('remember_token');
            $table->boolean('daily_report')->default(false)->after('remember_token');
            $table->time('task_reminder_time')->default('09:00:00')->after('remember_token');
            $table->boolean('task_reminder')->default(false)->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['daily_report', 'report_time', 'task_reminder', 'task_reminder_time']);
        });
    }
};
