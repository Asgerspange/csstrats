<?php

namespace App\Services;

use App\Models\Map;
use App\Models\Stratbook;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

/**
 * Auto-provisions stratbooks for map_based teams. One stratbook per active
 * map, linked via map_id. Idempotent: safe to re-run as a repair.
 *
 * Provisioning happens:
 *   - On team creation (for new map_based teams)
 *   - When a new map is activated (via maps:provision --new-map={id})
 *   - Via the maps:provision artisan command (repair/backfill)
 */
class MapProvisioner
{
    public function __construct(
        private MapService $mapService
    ) {}

    /**
     * Provision all active maps for a team. Creates one stratbook per map
     * with map_id set. Idempotent — skips existing (team_id, map_id) pairs.
     */
    public function provision(Team $team): void
    {
        $activeMaps = $this->mapService->activeMaps();

        foreach ($activeMaps as $map) {
            Stratbook::firstOrCreate(
                ['team_id' => $team->id, 'map_id' => $map->id],
                [
                    'stratbook_name' => $map->display_name,
                    'created_by' => $team->owned_by,
                ]
            );
        }
    }

    /**
     * Provision a single new map for all map_based teams.
     * Called when a map is activated (maps.active = true).
     */
    public function provisionNewMap(int $mapId): void
    {
        $map = Map::find($mapId);
        if (! $map) {
            Log::warning("MapProvisioner: map {$mapId} not found");

            return;
        }

        $teams = Team::where('entitlement_mode', 'map_based')
            ->whereNull('deleted_at')
            ->get();

        foreach ($teams as $team) {
            Stratbook::firstOrCreate(
                ['team_id' => $team->id, 'map_id' => $map->id],
                [
                    'stratbook_name' => $map->display_name,
                    'created_by' => $team->owned_by,
                ]
            );
        }

        Log::info("MapProvisioner: provisioned map {$map->display_name} for {$teams->count()} teams");
    }
}
