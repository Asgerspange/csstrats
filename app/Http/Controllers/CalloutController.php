<?php

namespace App\Http\Controllers;

use App\Models\Map;
use App\Models\MapCalloutZone;
use App\Models\CalloutLayer;
use App\Models\TeamCalloutName;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalloutController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // ADMIN — Zone Editor
    // ─────────────────────────────────────────────────────────────

    /**
     * Get all active maps (admin) — used by the Vue router callout editor.
     * GET /api/admin/callouts/maps
     */
    public function adminMaps(): JsonResponse
    {
        $maps = Map::where('active', true)->get(['id', 'name', 'display_name', 'image_path']);
        return response()->json($maps);
    }

    /**
     * Get all zones for a map (admin).
     * GET /api/admin/callouts/{map}
     */
    /**
     * Get layers for a map (admin).
     * GET /api/admin/callouts/{map}/layers
     */
    public function adminLayers(Map $map): JsonResponse
    {
        $layers = CalloutLayer::where('map_id', $map->id)
            ->orderBy('order')
            ->get();
        return response()->json($layers);
    }

    /**
     * Create a layer (admin).
     * POST /api/admin/callouts/{map}/layers
     */
    public function storeLayer(Request $request, Map $map): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:80',
            'order' => 'nullable|integer',
            'color' => 'nullable|string|max:7',
        ]);
        $layer = CalloutLayer::create(array_merge($validated, [
            'map_id' => $map->id,
            'order'  => $validated['order'] ?? CalloutLayer::where('map_id', $map->id)->max('order') + 1,
        ]));
        return response()->json($layer, 201);
    }

    /**
     * Update a layer (admin).
     * PUT /api/admin/callouts/layers/{layer}
     */
    public function updateLayer(Request $request, CalloutLayer $layer): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'sometimes|string|max:80',
            'order' => 'nullable|integer',
            'color' => 'nullable|string|max:7',
        ]);
        $layer->update($validated);
        return response()->json($layer);
    }

    /**
     * Delete a layer (admin). Zones in this layer become unassigned (layer_id = null).
     * DELETE /api/admin/callouts/layers/{layer}
     */
    public function deleteLayer(CalloutLayer $layer): JsonResponse
    {
        MapCalloutZone::where('layer_id', $layer->id)->update(['layer_id' => null]);
        $layer->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function adminZones(Map $map): JsonResponse
    {
        $zones = MapCalloutZone::where('map_id', $map->id)
            ->with('layer:id,name,order,color')
            ->orderBy('sort_order')
            ->get();

        return response()->json($zones);
    }

    /**
     * Create a zone (admin).
     * POST /api/admin/callouts/{map}/zones
     */
    public function storeZone(Request $request, Map $map): JsonResponse
    {
        $validated = $request->validate([
            'canonical_name' => 'required|string|max:80',
            'short_code'     => 'nullable|string|max:20',
            'layer_id'       => 'nullable|integer|exists:callout_layers,id',
            'zone_type'      => 'nullable|in:area,zone,point',
            'polygon'        => 'required|array|min:3',
            'polygon.*'      => 'array',
            'color'          => 'nullable|string|max:7',
            'sort_order'     => 'nullable|integer',
        ]);

        $zone = MapCalloutZone::create(array_merge($validated, [
            'map_id'     => $map->id,
            'color'      => $validated['color'] ?? '#f97316',
            'zone_type'  => $validated['zone_type'] ?? 'zone',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]));

        return response()->json($zone->load('layer:id,name,order,color'), 201);
    }

    /**
     * Update a zone (admin).
     * PUT /api/admin/callouts/zones/{zone}
     */
    public function updateZone(Request $request, MapCalloutZone $zone): JsonResponse
    {
        $validated = $request->validate([
            'canonical_name' => 'sometimes|string|max:80',
            'short_code'     => 'nullable|string|max:20',
            'layer_id'       => 'nullable|integer|exists:callout_layers,id',
            'zone_type'      => 'nullable|in:area,zone,point',
            'polygon'        => 'sometimes|array|min:3',
            'color'          => 'nullable|string|max:7',
            'sort_order'     => 'nullable|integer',
        ]);

        $zone->update($validated);

        return response()->json($zone->load('layer:id,name,order,color'));
    }

    /**
     * Delete a zone (admin).
     * DELETE /api/admin/callouts/zones/{zone}
     */
    public function deleteZone(MapCalloutZone $zone): JsonResponse
    {
        $zone->delete();
        return response()->json(['message' => 'Deleted']);
    }

    // ─────────────────────────────────────────────────────────────
    // PUBLIC API — Team callout names
    // ─────────────────────────────────────────────────────────────

    /**
     * Get all zones for a map with team's custom names applied.
     * Returns the team's custom name if set, otherwise canonical name.
     *
     * GET /api/callouts/{map}?team_id={id}
     */
    public function zonesForTeam(Request $request, Map $map): JsonResponse
    {
        $teamId = $request->query('team_id');

        $zones = MapCalloutZone::where('map_id', $map->id)
            ->orderBy('sort_order')
            ->get();

        if ($teamId) {
            $customNames = TeamCalloutName::where('team_id', $teamId)
                ->whereIn('zone_id', $zones->pluck('id'))
                ->pluck('custom_name', 'zone_id');

            $zones = $zones->map(function ($zone) use ($customNames) {
                $zone->display_name = $customNames[$zone->id] ?? $zone->canonical_name;
                return $zone;
            });
        } else {
            $zones = $zones->map(function ($zone) {
                $zone->display_name = $zone->canonical_name;
                return $zone;
            });
        }

        return response()->json($zones);
    }

    /**
     * Get zones for all maps at once (for the / picker in tactic descriptions).
     * Returns map_name => [zone_id => display_name] lookup.
     *
     * GET /api/callouts/all?team_id={id}
     */
    public function allZonesForTeam(Request $request): JsonResponse
    {
        $teamId = $request->query('team_id');

        $zones = MapCalloutZone::with(['map:id,name,display_name,image_path', 'layer:id,name,order'])
            ->orderBy('sort_order')
            ->get();

        $customNames = [];
        if ($teamId) {
            $customNames = TeamCalloutName::where('team_id', $teamId)
                ->pluck('custom_name', 'zone_id')
                ->toArray();
        }

        $result = $zones->map(function ($zone) use ($customNames) {
            return [
                'id'             => $zone->id,
                'map_id'         => $zone->map_id,
                'map_name'       => $zone->map->display_name ?? '',
                'map_key'        => $zone->map->name ?? '',
                'layer_id'       => $zone->layer_id,
                'layer_name'     => $zone->layer?->name ?? null,
                'layer_order'    => $zone->layer?->order ?? 0,
                'zone_type'      => $zone->zone_type,
                'map_image_path' => $zone->map->image_path ?? null,
                'canonical_name' => $zone->canonical_name,
                'display_name'   => $customNames[$zone->id] ?? $zone->canonical_name,
                'short_code'     => $zone->short_code,
                'polygon'        => $zone->polygon,
                'color'          => $zone->color,
            ];
        });

        return response()->json($result);
    }

    /**
     * Save team's custom callout names for a map.
     * Teams can only rename — not add or delete zones.
     *
     * POST /api/callouts/{map}/team/{team}
     */
    public function saveTeamNames(Request $request, Map $map, Team $team): JsonResponse
    {
        // Verify user is admin/coach of this team
        $user = Auth::user();
        $role = $user->userRole->firstWhere('team_id', $team->id)?->role;

        if ($team->owned_by !== $user->id && !in_array($role, ['Owner', 'Coach', 'IGL'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'names'           => 'required|array',
            'names.*.zone_id' => 'required|integer|exists:map_callout_zones,id',
            'names.*.name'    => 'required|string|max:80',
        ]);

        foreach ($validated['names'] as $entry) {
            TeamCalloutName::updateOrCreate(
                ['team_id' => $team->id, 'zone_id' => $entry['zone_id']],
                ['custom_name' => $entry['name']]
            );
        }

        return response()->json(['message' => 'Saved']);
    }
}