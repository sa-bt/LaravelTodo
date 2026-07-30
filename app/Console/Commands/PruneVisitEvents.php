<?php

namespace App\Console\Commands;

use App\Models\PageView;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Deletes raw page views once the daily roll up has taken their place.
 *
 * The raw table grows with every single view and nothing above six months back
 * is ever read from it, so leaving it to grow forever would cost storage for
 * data no report asks for.
 */
class PruneVisitEvents extends Command
{
    protected $signature = 'visits:prune {--days=180 : Keep raw views newer than this many days}';

    protected $description = 'Delete raw page views older than the retention window';

    /**
     * Rows go in chunks so a first run on a long lived table cannot hold the
     * table in one enormous delete.
     */
    private const CHUNK = 5000;

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = Carbon::today()->subDays($days);

        $deleted = 0;

        do {
            $removed = PageView::query()
                ->where('created_at', '<', $cutoff)
                ->limit(self::CHUNK)
                ->delete();

            $deleted += $removed;
        } while ($removed > 0);

        $this->info("Pruned {$deleted} page view(s) older than {$cutoff->toDateString()}.");

        Log::info('VisitEvents pruning completed', [
            'deleted' => $deleted,
            'cutoff' => $cutoff->toDateString(),
        ]);

        return self::SUCCESS;
    }
}
