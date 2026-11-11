<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tactic;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;


class ProcessScheduledTacticReleases extends Command
{

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tactics:process-scheduled-releases';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled tactic releases';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $cacheService = new CacheService();
            
            // Use onlyUnreleased() instead of withUnreleased()->where('is_released', false)
            $tacticsToRelease = Tactic::onlyUnreleased()
                ->where('release_date', '<=', now('Europe/Amsterdam'))
                ->whereNotNull('release_date')
                ->get();

            Log::info("Found {$tacticsToRelease->count()} tactics to potentially release.");
            Log::info("Current Amsterdam time: " . now('Europe/Amsterdam')->format('Y-m-d H:i:s'));
            
            $count = 0;
            foreach ($tacticsToRelease as $tactic) {
                Log::info("Releasing tactic ID: {$tactic->id}, release_date: {$tactic->release_date}");
                $tactic->update(['is_released' => true]);
                $count++;
            }

            $cacheService->refreshTacticsCache();
            if ($count > 0) {
                Log::info("Released {$count} tactics.");
            } else {
                Log::info("No tactics to release at this time.");
            }

            return 0;
        }
        catch (\Exception $e) {
            Log::error('Error processing scheduled tactic releases: ' . $e->getMessage());
            return 1;
        }
    }
}