<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * One permanent row per day, built nightly from page_views.
     *
     * Recent windows are still computed live from the raw rows so today's
     * numbers stay fresh. This table exists so the raw rows can be pruned
     * without losing history.
     */
    public function up(): void
    {
        Schema::create('visit_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('sessions')->default(0);

            // Sessions with a single page view; the bounce rate comes from this.
            $table->unsignedInteger('bounced_sessions')->default(0);

            $table->unsignedInteger('guest_views')->default(0);
            $table->unsignedInteger('member_views')->default(0);

            // Distinct signed in users seen that day: the daily active count.
            $table->unsignedInteger('active_members')->default(0);

            // Visitors whose very first view ever fell on this day.
            $table->unsignedInteger('new_visitors')->default(0);

            $table->unsignedInteger('avg_session_seconds')->default(0);

            $table->json('top_paths')->nullable();
            $table->json('referrer_groups')->nullable();
            $table->json('device_types')->nullable();
            $table->json('browsers')->nullable();

            // Twenty four numbers, hour zero first, in the app timezone.
            $table->json('hourly')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_daily_stats');
    }
};
