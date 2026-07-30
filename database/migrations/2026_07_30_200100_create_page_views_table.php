<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     * One row per page view, sent by the browser.
     *
     * The server never renders a page, so a request counter on this side would
     * only see API calls and would miss every anonymous visit to the landing
     * page. The client reports the view instead.
     *
     * There is deliberately no ip column. Unique visitors are counted from a
     * random id the browser keeps, so the address is never needed and never
     * stored.
     */
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();

            // Random ids generated in the browser, not derived from anything.
            $table->string('visitor_id', 64);
            $table->string('session_id', 64);

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Query string and fragment are stripped before this is stored.
            $table->string('path', 191);
            $table->string('route_name', 64)->nullable();

            // Snapshot at view time: a visitor who logs in mid session has both.
            $table->boolean('is_guest')->default(true);

            $table->string('referrer_host', 191)->nullable();
            $table->string('referrer_group', 20)->default('direct');

            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();

            $table->string('device_type', 10)->default('desktop');
            $table->string('browser', 30)->default('other');
            $table->string('platform', 30)->default('other');

            // Robots are stored but flagged, and every report filters them out.
            $table->boolean('is_bot')->default(false);

            $table->timestamp('created_at')->nullable();

            /*
             * Every report scans a time window and skips robots, so that pair
             * leads. The other two serve the unique visitor and session counts
             * inside such a window.
             */
            $table->index(['is_bot', 'created_at']);
            $table->index(['visitor_id', 'created_at']);
            $table->index(['session_id', 'created_at']);
            $table->index(['path', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
