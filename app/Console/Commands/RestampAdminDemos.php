<?php

namespace App\Console\Commands;

use App\Enums\QueuedDemoStatus;
use App\Models\Demo;
use App\Models\DemoEnrichmentJob;
use App\Models\DemoRound;
use Illuminate\Console\Command;

class RestampAdminDemos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demos:restamp-admin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enqueue callout re-stamping for every curated round of every admin demo, converging existing (backfilled) admin demos onto the map_callout_zones callout system. Run once after the backfill, and only after the relevant maps have zones defined in /admin/callouts.';

    public function handle(): int
    {
        $demoIds = Demo::where('is_admin', true)->pluck('id');
        $this->info("Found {$demoIds->count()} admin demo(s).");

        $queued = 0;
        foreach ($demoIds as $demoId) {
            $rounds = DemoRound::where('demo_id', $demoId)
                ->where('curated', true)
                ->pluck('round_index')
                ->values()
                ->all();

            if (empty($rounds)) {
                continue;
            }

            DemoEnrichmentJob::create([
                'demo_id'       => $demoId,
                'round_indices' => $rounds,
                'status'        => QueuedDemoStatus::Pending->value,
            ]);
            $queued++;
        }

        $this->info("Queued {$queued} enrichment job(s). The demoparser service will process them.");

        return self::SUCCESS;
    }
}
