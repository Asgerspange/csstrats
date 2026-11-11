<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Map;
use App\Models\MapSelectionAudit;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Team;
use App\Models\User;
use App\Services\MapProvisioner;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTeamController extends Controller
{
    public function __construct(
        private MapService $mapService,
        private MapProvisioner $provisioner
    ) {}

    /**
     * GET /api/admin/teams — list all teams with owner, stratbook count, mode.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Team::with(['owner:id,name,email', 'stratbooks' => function ($q) {
            $q->whereNull('deleted_at')->select('id', 'team_id', 'stratbook_name', 'map_id');
        }])
            ->whereNull('deleted_at')
            ->select('id', 'name', 'owned_by', 'entitlement_mode', 'map_lock_expires_at', 'created_at');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('owner', function ($q2) use ($search) {
                        $q2->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $teams = $query->orderBy('id', 'desc')->paginate(50);

        return new JsonResponse($teams, JsonResponse::HTTP_OK);
    }

    /**
     * GET /api/admin/teams/{id} — team detail with stratbooks + tactics.
     */
    public function show($id): JsonResponse
    {
        $team = Team::with([
            'owner:id,name,email',
            'stratbooks' => function ($q) { $q->whereNull('deleted_at')->withCount('tactics'); },
            'stratbooks.tactics' => function ($q) { $q->whereNull('deleted_at')->select(['id', 'stratbook_id', 'name', 'type', 'map', 'side', 'tactic_id', 'sort_order']); },
            'selectedMaps:id,name,display_name',
        ])->findOrFail($id);

        $maps = $this->mapService->activeMaps(['id', 'name', 'display_name']);

        return new JsonResponse([
            'team' => $team,
            'maps' => $maps,
        ], JsonResponse::HTTP_OK);
    }

    /**
     * POST /api/admin/teams/{id}/convert — convert legacy team to map_based.
     * Assigns each stratbook to a map based on its name (best match),
     * merges duplicates, provisions missing maps, sets default selection.
     */
    public function convertToMapBased(Request $request, $id): JsonResponse
    {
        $team = Team::findOrFail($id);
        if ($team->entitlement_mode === 'map_based') {
            return new JsonResponse(['message' => 'Team is already map_based.'], 400);
        }

        $team->entitlement_mode = 'map_based';
        $team->map_lock_expires_at = now()->addDays(30);
        $team->save();

        // Assign map_id to existing stratbooks by matching name
        $stratbooks = $team->stratbooks()->whereNull('deleted_at')->get();
        $activeMaps = Map::active()->get();
        $mapByName = [];
        foreach ($activeMaps as $map) {
            $mapByName[strtolower($map->display_name)] = $map->id;
        }

        foreach ($stratbooks as $sb) {
            $key = strtolower(trim($sb->stratbook_name));
            if (isset($mapByName[$key])) {
                $mapId = $mapByName[$key];

                // Check for existing map-based stratbook with same map_id
                $existing = $team->stratbooks()
                    ->where('map_id', $mapId)
                    ->where('id', '!=', $sb->id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    // Merge: move tactics from $sb to $existing, then soft-delete $sb
                    StratbookTactic::where('stratbook_id', $sb->id)
                        ->whereNull('deleted_at')
                        ->update(['stratbook_id' => $existing->id]);
                    $sb->delete();
                } else {
                    $sb->map_id = $mapId;
                    $sb->save();
                }
            }
            // Stratbooks with no map match are left as-is (map_id = null)
        }

        // Provision any missing map stratbooks
        $this->provisioner->provision($team);

        // Default selection: first 4 active maps by ID
        $defaultMapIds = $this->mapService->defaultMapIds(4);
        $team->selectedMaps()->sync($defaultMapIds);

        MapSelectionAudit::create([
            'team_id' => $team->id,
            'map_ids' => $defaultMapIds,
            'selected_by' => $request->user()->id,
            'reason' => 'initial',
        ]);

        return new JsonResponse([
            'message' => 'Team converted to map_based.',
            'team_id' => $team->id,
        ], JsonResponse::HTTP_OK);
    }

    /**
     * PATCH /api/admin/stratbooks/{id} — rename stratbook and/or assign map.
     */
    public function updateStratbook(Request $request, $id): JsonResponse
    {
        $stratbook = Stratbook::findOrFail($id);
        $validated = $request->validate([
            'stratbook_name' => 'sometimes|string|max:255',
            'map_id' => 'sometimes|nullable|integer|exists:maps,id',
        ]);

        if (isset($validated['stratbook_name'])) {
            $stratbook->stratbook_name = $validated['stratbook_name'];
        }
        if (array_key_exists('map_id', $validated)) {
            $stratbook->map_id = $validated['map_id'];
        }
        $stratbook->save();

        return new JsonResponse(['message' => 'Stratbook updated.'], JsonResponse::HTTP_OK);
    }

    /**
     * PATCH /api/admin/tactics/{id} — edit a stratbook tactic (move to another
     * stratbook, rename, change map/side/type).
     */
    public function updateTactic(Request $request, $id): JsonResponse
    {
        $tactic = StratbookTactic::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'map' => 'sometimes|string',
            'side' => 'sometimes|string|in:CT,T',
            'stratbook_id' => 'sometimes|integer|exists:stratbooks,id',
        ]);

        $tactic->fill($validated);
        $tactic->save();

        return new JsonResponse(['message' => 'Tactic updated.'], JsonResponse::HTTP_OK);
    }

    /**
     * POST /api/admin/tactics/{id}/duplicate — duplicate a tactic within
     * the same stratbook or into a different one.
     */
    public function duplicateTactic(Request $request, $id): JsonResponse
    {
        $tactic = StratbookTactic::findOrFail($id);
        $validated = $request->validate([
            'stratbook_id' => 'sometimes|integer|exists:stratbooks,id',
        ]);

        $targetStratbookId = $validated['stratbook_id'] ?? $tactic->stratbook_id;

        $copy = $tactic->replicate();
        $copy->stratbook_id = $targetStratbookId;
        $copy->name = ($tactic->name ?? 'Tactic') . ' (copy)';
        $copy->sort_order = 0;
        $copy->tactic_id = null;
        $copy->save();

        return new JsonResponse([
            'message' => 'Tactic duplicated.',
            'tactic_id' => $copy->id,
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * DELETE /api/admin/tactics/{id} — permanently delete a tactic (force delete).
     */
    public function deleteTactic($id): JsonResponse
    {
        $tactic = StratbookTactic::withTrashed()->findOrFail($id);
        $tactic->forceDelete();

        return new JsonResponse(['message' => 'Tactic deleted.'], JsonResponse::HTTP_OK);
    }

    /**
     * POST /api/admin/stratbooks/{id}/assign-map — assign a map to a stratbook
     * and optionally merge with existing map stratbook.
     */
    public function assignMap(Request $request, $id): JsonResponse
    {
        $stratbook = Stratbook::findOrFail($id);
        $validated = $request->validate([
            'map_id' => 'required|integer|exists:maps,id',
            'merge' => 'boolean',
        ]);

        $mapId = $validated['map_id'];
        $merge = $validated['merge'] ?? false;

        // Check if another stratbook already has this map_id
        $existing = $stratbook->team->stratbooks()
            ->where('map_id', $mapId)
            ->where('id', '!=', $stratbook->id)
            ->whereNull('deleted_at')
            ->first();

        if ($existing && $merge) {
            // Move tactics to the existing stratbook, delete this one
            StratbookTactic::where('stratbook_id', $stratbook->id)
                ->whereNull('deleted_at')
                ->update(['stratbook_id' => $existing->id]);
            $stratbook->delete();
            return new JsonResponse(['message' => 'Merged into existing stratbook for that map.'], JsonResponse::HTTP_OK);
        }

        if ($existing && ! $merge) {
            return new JsonResponse([
                'message' => 'Another stratbook already has this map. Set merge=true to combine.',
            ], 422);
        }

        $stratbook->map_id = $mapId;
        $map = Map::find($mapId);
        if ($map) {
            $stratbook->stratbook_name = $map->display_name;
        }
        $stratbook->save();

        return new JsonResponse(['message' => 'Map assigned.'], JsonResponse::HTTP_OK);
    }
}
