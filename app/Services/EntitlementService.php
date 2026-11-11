<?php

namespace App\Services;

use App\Enums\EntitlementMode;
use App\Models\Map;
use App\Models\MapSelectionAudit;
use App\Models\Team;
use App\Models\User;

/**
 * The single place that answers "can this team access/edit this map".
 *
 * Legacy teams: all maps, no gating (old stratbook model).
 * Map-based teams: Starter (4 maps) and Team (6 maps) check the selection;
 * Pro (max_maps = null) has all maps.
 *
 * Read access (canAccessMap): view stratbook tactics, view demos, view boards.
 * Write access (canEditMap): add tactics, create/edit custom tactics, upload
 * demos, save boards, save round plans.
 *
 * Downgraded teams: unselected maps go view-only (canAccessMap = true,
 * canEditMap = false) — content survives, editing is disabled.
 */
class EntitlementService
{
    /** @var array<int, bool>|null Per-request memo: team_id => cached */
    private ?array $accessCache = [];

    /** @var array<int, array<int, bool>>|null team_id => [map_id => bool] */
    private ?array $editCache = [];

    public function __construct(
        private PlanService $planService,
        private MapService $mapService
    ) {}

    /**
     * Can this team read content on this map?
     */
    public function canAccessMap(Team $team, int $mapId): bool
    {
        if ($team->entitlement_mode === EntitlementMode::Legacy->value) {
            return true;
        }

        $maxMaps = $this->planService->forUser($team->owner)['max_maps'] ?? null;

        // Pro (null = all maps)
        if ($maxMaps === null) {
            return true;
        }

        // Starter (4) or Team (6): in selection = accessible
        $selectedIds = $this->selectedMapIds($team);

        return in_array($mapId, $selectedIds);
    }

    /**
     * Can this team create/edit content on this map?
     * Downgraded teams: unselected maps are view-only (access true, edit false).
     */
    public function canEditMap(Team $team, int $mapId): bool
    {
        if (! $this->canAccessMap($team, $mapId)) {
            return false;
        }

        if ($team->entitlement_mode === EntitlementMode::Legacy->value) {
            return true;
        }

        $maxMaps = $this->planService->forUser($team->owner)['max_maps'] ?? null;

        // Pro: edit all
        if ($maxMaps === null) {
            return true;
        }

        // Starter/Team: only selected maps are editable
        $selectedIds = $this->selectedMapIds($team);

        return in_array($mapId, $selectedIds);
    }

    /**
     * The list of map IDs this team can access. For legacy and Pro, all active
     * maps. For Starter/Team, the selection.
     *
     * @return int[]
     */
    public function accessibleMapIds(Team $team): array
    {
        if ($team->entitlement_mode === EntitlementMode::Legacy->value) {
            return $this->mapService->activeMaps()->pluck('id')->toArray();
        }

        $maxMaps = $this->planService->forUser($team->owner)['max_maps'] ?? null;

        if ($maxMaps === null) {
            return $this->mapService->activeMaps()->pluck('id')->toArray();
        }

        return $this->selectedMapIds($team);
    }

    /**
     * The list of map IDs this team can edit.
     *
     * @return int[]
     */
    public function editableMapIds(Team $team): array
    {
        return $this->accessibleMapIds($team);
    }

    /**
     * Resolve a map string (display_name or console name) to a map ID.
     */
    public function mapNameToId(?string $name): ?int
    {
        return $this->mapService->nameToId($name);
    }

    /**
     * Adjust map selections for all of a user's teams to match their current
     * plan's max_maps. Expands the selection on upgrade (adding maps from the
     * active pool), truncates on downgrade. Called after plan changes (Paddle
     * webhook, admin override) so the selection matches the new tier without
     * requiring manual user action.
     */
    public function syncMapSelectionsForUser(User $user): void
    {
        $maxMaps = $this->planService->forUser($user)['max_maps'] ?? null;

        $teams = Team::where('owned_by', $user->id)->whereNull('deleted_at')->get();

        foreach ($teams as $team) {
            if ($team->entitlement_mode !== EntitlementMode::MapBased->value) {
                continue;
            }

            $currentIds = $team->selectedMaps()->orderBy('map_id')->pluck('maps.id')->toArray();

            if ($maxMaps === null) {
                $newIds = $this->mapService->activeMaps()->pluck('id')->toArray();
            } elseif (count($currentIds) < $maxMaps) {
                $availableIds = $this->mapService->activeMaps()->pluck('id')->toArray();
                $toAdd = array_slice(array_diff($availableIds, $currentIds), 0, $maxMaps - count($currentIds));
                $newIds = array_merge($currentIds, $toAdd);
            } elseif (count($currentIds) > $maxMaps) {
                $newIds = array_slice($currentIds, 0, $maxMaps);
            } else {
                continue;
            }

            if ($newIds === $currentIds) {
                continue;
            }

            $team->selectedMaps()->sync($newIds);

            MapSelectionAudit::create([
                'team_id' => $team->id,
                'map_ids' => $newIds,
                'selected_by' => $user->id,
                'reason' => 'upgrade',
            ]);
        }
    }

    /**
     * Get the selected map IDs for a team from the pivot table.
     *
     * @return int[]
     */
    private function selectedMapIds(Team $team): array
    {
        return $team->selectedMaps()
            ->orderBy('map_id')
            ->pluck('maps.id')
            ->toArray();
    }
}
