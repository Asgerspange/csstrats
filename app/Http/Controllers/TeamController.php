<?php

namespace App\Http\Controllers;

use App\Core\Repositories\TeamRepository;
use App\Enums\EntitlementMode;
use App\Models\Map;
use App\Models\MapSelectionAudit;
use App\Models\Team;
use App\Services\EntitlementService;
use App\Services\MapService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamController extends Controller
{
    public function __construct(
        private TeamRepository $teamRepository
    ) {
        $this->middleware(['auth', 'web']);
    }

    public function index()
    {
        return new JsonResponse($this->teamRepository->index(Auth::id()), JsonResponse::HTTP_OK);
    }

    public function show(Team $team)
    {
        return new JsonResponse($this->teamRepository->show($team), JsonResponse::HTTP_OK);
    }

    /**
     * Cross-stratbook tactic overview for the Team page: all tactic rows
     * across the team's stratbooks, in one request. Auth via TeamPolicy::view
     * (route param is {team}, model-bound).
     */
    public function tactics(Team $team)
    {
        return new JsonResponse($this->teamRepository->tactics($team), JsonResponse::HTTP_OK);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->teamRepository->store($request->team_name);
    }

    public function updateName(Request $request, $teamId)
    {
        return $this->teamRepository->update($request->team_name, $teamId);
    }

    public function leaveTeam(Request $request)
    {
        return $this->teamRepository->leave(Auth::id(), $request->id);
    }

    public function destroy($id)
    {
        return $this->teamRepository->delete($id, Auth::id());
    }

    public function storeProTeam(Request $request)
    {
        return $this->teamRepository->storeProTeam($request);
    }

    public function updateProTeam(Request $request, $id)
    {
        return $this->teamRepository->updateProTeam($request, $id);
    }

    public function destroyProTeam($id)
    {
        return $this->teamRepository->destroyProTeam($id);
    }

    public function updateTags(Request $request, $teamId)
    {
        return $this->teamRepository->updateTags($teamId, $request->tags);
    }

    public function updateTypes(Request $request, $teamId)
    {
        return $this->teamRepository->updateTypes($teamId, $request->types);
    }

    public function updatePermissions(Request $request, $teamId)
    {
        return $this->teamRepository->updatePermissions($teamId, $request->user_id, $request->perm);
    }

    public function updateRoles(Request $request, $teamId)
    {
        return $this->teamRepository->updateRoles($teamId, $request->user_id, $request->role);
    }

    public function addMember(Request $request, $teamId)
    {
        return $this->teamRepository->addMember($teamId, $request->email);
    }

    public function removeMember(Request $request, $teamId)
    {
        return $this->teamRepository->removeMember($teamId, $request->user_id);
    }

    /**
     * GET /api/team/{id}/maps — all active maps with access state, tactic
     * counts, and lock info for the team's Maps page.
     */
    public function maps($id): JsonResponse
    {
        $team = Team::find($id);
        if (! $team) {
            return new JsonResponse(['message' => 'Team not found.'], 404);
        }

        $entitlement = app(EntitlementService::class);
        $mapService = app(MapService::class);

        $selectedIds = $team->entitlement_mode === EntitlementMode::MapBased->value
            ? $team->selectedMaps()->pluck('maps.id')->toArray()
            : [];

        $maps = $mapService->activeMaps()->map(function (Map $map) use ($team, $entitlement, $selectedIds) {
            $tacticCount = $team->stratbooks()
                ->where('map_id', $map->id)
                ->withCount('tactics')
                ->first()?->tactics_count ?? 0;

            if ($team->entitlement_mode === EntitlementMode::Legacy->value) {
                $accessState = 'accessible';
            } elseif ($entitlement->canAccessMap($team, $map->id)) {
                $accessState = $entitlement->canEditMap($team, $map->id) ? 'accessible' : 'view-only';
            } else {
                $accessState = 'locked';
            }

            return [
                'id' => $map->id,
                'name' => $map->name,
                'display_name' => $map->display_name,
                'image_path' => $map->image_path,
                'tactic_count' => $tacticCount,
                'access_state' => $accessState,
                'selected' => in_array($map->id, $selectedIds),
            ];
        });

        return new JsonResponse([
            'maps' => $maps,
            'lock_expires_at' => $team->map_lock_expires_at?->toIso8601String(),
            'entitlement_mode' => $team->entitlement_mode,
            'max_maps' => app(PlanService::class)->forUser($team->owner)['max_maps'] ?? null,
        ], JsonResponse::HTTP_OK);
    }

    /**
     * POST /api/team/{id}/maps/select — change the team's map selection.
     * Validates count against plan, checks lock, writes audit.
     */
    public function selectMaps(Request $request, $id): JsonResponse
    {
        $team = Team::find($id);
        if (! $team) {
            return new JsonResponse(['message' => 'Team not found.'], 404);
        }

        if ($team->entitlement_mode !== EntitlementMode::MapBased->value) {
            return new JsonResponse(['message' => 'Map selection is not available for this team.'], 403);
        }

        $owner = $team->owner;
        if (! $owner || Auth::id() !== $owner->id) {
            $userRole = $team->roles()->where('user_id', Auth::id())->first();
            if (! $userRole || ! in_array($userRole->role, ['Owner', 'Coach', 'Captain'])) {
                return new JsonResponse(['message' => 'Only the team owner, coach, or captain can change maps.'], 403);
            }
        }

        $validated = $request->validate([
            'map_ids' => 'required|array',
            'map_ids.*' => 'integer|exists:maps,id',
        ]);

        $plan = app(PlanService::class)->forUser($team->owner);
        $maxMaps = $plan['max_maps'] ?? null;

        if ($maxMaps === null) {
            return new JsonResponse(['message' => 'Your plan includes all maps — no selection needed.'], 400);
        }

        if (count($validated['map_ids']) > $maxMaps) {
            return new JsonResponse([
                'message' => "You can select at most {$maxMaps} maps.",
                'max_maps' => $maxMaps,
            ], 422);
        }

        // Check lock — but the first change is free (the default wasn't user-chosen)
        $hasUserChanged = MapSelectionAudit::where('team_id', $team->id)
            ->where('reason', '!=', 'initial')
            ->exists();

        if ($hasUserChanged && $team->map_lock_expires_at && $team->map_lock_expires_at->isFuture()) {
            return new JsonResponse([
                'message' => 'Your map selection is locked until '.$team->map_lock_expires_at->format('M j').'.',
                'lock_expires_at' => $team->map_lock_expires_at->toIso8601String(),
            ], 423);
        }

        $mapIds = $validated['map_ids'];
        $team->selectedMaps()->sync($mapIds);
        $team->map_lock_expires_at = now()->addDays(30);
        $team->save();

        MapSelectionAudit::create([
            'team_id' => $team->id,
            'map_ids' => $mapIds,
            'selected_by' => Auth::id(),
            'reason' => 'change',
        ]);

        return new JsonResponse([
            'message' => 'Map selection updated.',
            'lock_expires_at' => $team->map_lock_expires_at->toIso8601String(),
        ], JsonResponse::HTTP_OK);
    }
}
