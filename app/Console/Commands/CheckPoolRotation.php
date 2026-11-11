<?php

namespace App\Console\Commands;

use App\Models\MapMetadata;
use App\Models\Team;
use App\Services\MapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckPoolRotation extends Command
{
    protected $signature = 'maps:check-pool-rotation {--dry-run : Show changes without applying}';

    protected $description = 'Detect Active Duty pool changes, void locks and provision new maps';

    public function handle(MapService $mapService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Get current active duty state
        $currentDuty = MapMetadata::where('is_active_duty', 1)
            ->pluck('name')
            ->toArray();

        // Get last known state from cache
        $lastDuty = Cache::get('map_pool:active_duty', null);

        if ($lastDuty === null) {
            // First run — just store the state
            Cache::forever('map_pool:active_duty', $currentDuty);
            $this->info('First run — stored current Active Duty pool: ' . implode(', ', $currentDuty));
            return 0;
        }

        $added = array_diff($currentDuty, $lastDuty);
        $removed = array_diff($lastDuty, $currentDuty);

        if (empty($added) && empty($removed)) {
            $this->info('No pool changes detected.');
            return 0;
        }

        $this->info('=== Pool Rotation Detected ===');
        if (! empty($added)) {
            $this->info('Maps entering Active Duty: ' . implode(', ', $added));
        }
        if (! empty($removed)) {
            $this->info('Maps leaving Active Duty: ' . implode(', ', $removed));
        }

        // For removed maps: void locks for teams that have them selected
        if (! empty($removed)) {
            $removedMapIds = [];
            foreach ($removed as $mapName) {
                $mapId = $mapService->nameToId($mapName);
                if ($mapId) {
                    $removedMapIds[] = $mapId;
                }
            }

            if (! empty($removedMapIds)) {
                $affectedTeams = Team::where('entitlement_mode', 'map_based')
                    ->whereNull('deleted_at')
                    ->whereHas('selectedMaps', function ($q) use ($removedMapIds) {
                        $q->whereIn('maps.id', $removedMapIds);
                    })
                    ->get();

                $this->info("\nTeams with a removed map in their selection: {$affectedTeams->count()}");

                foreach ($affectedTeams as $team) {
                    $this->line("  Team {$team->id} ({$team->name}): voiding lock");
                    if (! $dryRun) {
                        $team->map_lock_expires_at = null;
                        $team->save();
                        Log::info('Pool rotation: voided lock for team', [
                            'team_id' => $team->id,
                            'removed_maps' => $removedMapIds,
                        ]);
                    }
                }
            }
        }

        // For added maps: provision stratbooks for all map_based teams
        if (! empty($added) && ! $dryRun) {
            $provisioner = app(\App\Services\MapProvisioner::class);
            foreach ($added as $mapName) {
                $mapId = $mapService->nameToId($mapName);
                if ($mapId) {
                    $this->line("  Provisioning stratbooks for new map: {$mapName}");
                    $provisioner->provisionNewMap($mapId);
                }
            }
        }

        // Store new state
        if (! $dryRun) {
            Cache::forever('map_pool:active_duty', $currentDuty);
        }

        if ($dryRun) {
            $this->warn('Dry run — no changes made.');
        }

        return 0;
    }
}
