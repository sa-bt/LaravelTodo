<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('weekly_report')->default(false)->after('report_time');

            /*
             * Day of the jalali week the summary is sent on: 0 is Saturday and
             * 6 is Friday, the same numbering the report page uses for its
             * weekday chart. Friday evening is the default: the week is ending
             * and there is still a weekend left to react to a bad one.
             */
            $table->unsignedTinyInteger('weekly_report_day')->default(6)->after('weekly_report');
            $table->time('weekly_report_time')->default('20:00:00')->after('weekly_report_day');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['weekly_report', 'weekly_report_day', 'weekly_report_time']);
        });
    }
};
