<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * The drop alert: a gentle nudge after two weeks of falling
             * completion rate.
             *
             * Off by default, like the weekly report. An alert nobody asked for
             * is the kind of notification people turn the whole app off over.
             *
             * It carries no day or time of its own on purpose. The alert is
             * rare and always lands on the first morning of the week, when the
             * user can still do something about it, so a second pair of
             * scheduling knobs would be more settings than value.
             */
            $table->boolean('drop_alert')->default(false)->after('weekly_report_time');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('drop_alert');
        });
    }
};
