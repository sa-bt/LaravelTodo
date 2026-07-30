<?php

namespace App\Console\Commands;

use App\Jobs\SendDropAlertNotificationJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Weekly scan for the drop alert.
 *
 * Unlike the weekly report, this one has no per user day or time to match. The
 * schedule fires it once a week, on saturday morning, and every decision about
 * whether a given user hears anything belongs to the job itself.
 */
class SendDropAlertsDue extends Command
{
    protected $signature = 'reports:send-drop-alerts-due';

    protected $description = 'Dispatch a drop alert check for every user who asked for one';

    public function handle(): int
    {
        $dispatched = 0;

        User::query()
            ->where('drop_alert', true)
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($users) use (&$dispatched) {
                foreach ($users as $user) {
                    SendDropAlertNotificationJob::dispatch($user->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} drop alert checks");

        Log::info('DropAlert scan completed', ['dispatched' => $dispatched]);

        return self::SUCCESS;
    }
}
