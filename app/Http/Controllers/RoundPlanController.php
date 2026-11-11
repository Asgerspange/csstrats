<?php

namespace App\Http\Controllers;

use App\Constants\AppErrorCode;
use App\Models\RoundPlan;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Services\EntitlementService;
use App\Services\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class RoundPlanController extends Controller
{
    private string $storageZoneName;

    private string $storagePassword;

    private string $cdnUrl;

    private string $storageBaseUrl;

    public function __construct()
    {
        $this->storageZoneName = config('services.bunny.storage_zone');
        $this->storagePassword = config('services.bunny.storage_password');
        $this->cdnUrl = config('services.bunny.cdn_url');
        $this->storageBaseUrl = config('services.bunny.storage_url', 'https://storage.bunnycdn.com');

        if (app()->environment('local', 'development') || ! env('VERIFY_SSL', true)) {
            Http::globalOptions(['verify' => false]);
        }
    }

    private function uploadSnapshotToBunny($file, int $planId): string
    {
        $fileName = "round_plan_snapshots/{$planId}.png";
        $uploadUrl = "{$this->storageBaseUrl}/{$this->storageZoneName}/{$fileName}";
        $fileContents = is_string($file) ? $file : file_get_contents($file->getRealPath());

        $response = Http::withHeaders([
            'AccessKey' => $this->storagePassword,
            'Content-Type' => 'image/png',
        ])->withBody($fileContents, 'image/png')->put($uploadUrl);

        if (! $response->successful()) {
            \Log::error('BunnyCDN RoundPlan Upload Failed: '.$response->body());
            throw new \Exception('Failed to upload round plan snapshot to Bunny.');
        }

        return "{$this->cdnUrl}/{$fileName}?t=".time();
    }

    /**
     * GET /api/round-plans
     *
     * Lightweight list for the "load existing plan" picker in the strat builder.
     * Filters: stratbook_id, map, side.
     * Does NOT return the full `data` payload — just metadata for the list UI.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $plans = RoundPlan::query()
            ->where(function ($q) use ($userId) {
                $q->whereHas('team.roles', fn ($r) => $r->where('user_id', $userId))
                    ->orWhereHas('team', fn ($r) => $r->where('owned_by', $userId));
            })
            ->with('tactic:id,stratbook_id,name,side,map')
            ->select(['id', 'stratbook_tactic_id', 'team_id', 'name', 'map', 'side', 'snapshot_url', 'created_at', 'updated_at'])
            ->when($request->filled('stratbook_id'), fn ($q) => $q->whereHas('tactic', fn ($r) => $r->where('stratbook_id', $request->integer('stratbook_id'))
            )
            )
            ->when($request->filled('map'), fn ($q) => $q->where('map', $request->string('map')))
            ->when($request->filled('side'), fn ($q) => $q->where('side', strtoupper($request->string('side'))))
            ->orderByDesc('updated_at')
            ->get();

        // Filter out plans on locked maps for map_based teams
        $plans = $plans->filter(function ($plan) {
            $team = $plan->team;
            if (! $team || $team->entitlement_mode !== 'map_based') {
                return true;
            }
            $entitlement = app(EntitlementService::class);
            $mapId = $entitlement->mapNameToId($plan->map);
            return $mapId !== null && $entitlement->canAccessMap($team, $mapId);
        })->values();

        return new JsonResponse($plans);
    }

    /**
     * POST /api/round-plans
     *
     * Creates a new RoundPlan + linked StratbookTactic.
     */
    public function store(Request $request): JsonResponse
    {
        if (($gate = $this->ensureRoundPlannerFeature())) {
            return $gate;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'stratbook_id' => 'required|exists:stratbooks,id',
            'type' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:2000',
            'map' => 'nullable|string|max:80',
            'side' => 'nullable|string|max:2',
            'round_plan' => 'required|string',
            'snapshot' => 'nullable|file|mimes:png,jpg|max:4096',
            'tags' => 'nullable|string',   // JSON array of tag strings
            'players' => 'nullable|string',   // JSON array of player objects
        ]);

        $userId = Auth::id();
        $stratbook = Stratbook::findOrFail($validated['stratbook_id']);
        $teamId = $stratbook->team_id;

        // Map access gate for map_based teams
        if ($stratbook->map_id !== null && $stratbook->team->entitlement_mode === 'map_based') {
            $entitlement = app(EntitlementService::class);
            if (! $entitlement->canEditMap($stratbook->team, $stratbook->map_id)) {
                return new JsonResponse([
                    'app_code' => AppErrorCode::NO_MAP_EDIT_ACCESS,
                    'message' => 'This map is not in your plan. Upgrade to edit.',
                ], 403);
            }
        }

        $isMember = $stratbook->team->roles()->where('user_id', $userId)->exists()
            || $stratbook->team->owned_by === $userId;

        if (! $isMember) {
            return new JsonResponse(['message' => 'Forbidden'], 403);
        }

        $planData = json_decode($validated['round_plan'], true);
        if (! $planData) {
            return new JsonResponse(['message' => 'Invalid round_plan JSON'], 422);
        }

        $snapshotUrl = null;
        if ($request->hasFile('snapshot')) {
            // We need the plan ID before upload — create first, then upload and update
            $snapshotUrl = '__pending__';
        }

        // Parse optional players + tags from the save form
        $playersData = $validated['players'] ? json_decode($validated['players'], true) : [];
        $tagsData = $validated['tags'] ? json_decode($validated['tags'], true) : [];

        $formattedPlayers = [];
        $formattedDescriptions = [];
        $formattedLoadouts = [];

        foreach (($playersData ?: []) as $i => $p) {
            $name = $p['name'] ?: 'Player '.($i + 1);
            $formattedPlayers[] = ['name' => $name, 'role' => $p['role'] ?? 'Player'];
            $formattedDescriptions[] = ['player' => $name, 'descriptions' => $p['description'] ?? ''];
            $formattedLoadouts[] = ['player' => $name, 'loadouts' => $p['loadout'] ?? []];
        }

        $tactic = StratbookTactic::create([
            'stratbook_id' => $validated['stratbook_id'],
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'Execute',
            'description' => $validated['description'] ?? '',
            'map' => $validated['map'] ?? ($planData['map'] ?? ''),
            'side' => strtoupper($validated['side'] ?? ($planData['side'] ?? 'T')),
            'team' => ['name' => $stratbook->team->name ?? '', 'players' => $formattedPlayers],
            'player_descriptions' => $formattedDescriptions,
            'player_loadouts' => $formattedLoadouts,
            'player_timestamps' => [],
            'tags' => $tagsData,
        ]);

        $roundPlan = RoundPlan::create([
            'stratbook_tactic_id' => $tactic->id,
            'team_id' => $teamId,
            'created_by' => $userId,
            'name' => $validated['name'],
            'map' => $planData['map'] ?? null,
            'side' => $planData['side'] ?? 'T',
            'data' => $planData,
            'snapshot_url' => null,
        ]);

        // Now we have an ID — upload snapshot to Bunny
        if ($request->hasFile('snapshot')) {
            try {
                $url = $this->uploadSnapshotToBunny($request->file('snapshot'), $roundPlan->id);
                $roundPlan->update(['snapshot_url' => $url]);
            } catch (\Exception $e) {
                \Log::warning('Round plan snapshot upload failed: '.$e->getMessage());
            }
        }

        return new JsonResponse([
            'round_plan' => $roundPlan->fresh(),
            'tactic' => $tactic,
            'snapshot_url' => $roundPlan->snapshot_url,
        ], 201);
    }

    /**
     * GET /api/round-plans/{roundPlan}
     */
    public function show(RoundPlan $roundPlan): JsonResponse
    {
        $this->authoriseView($roundPlan);
        $roundPlan->load('tactic');

        return new JsonResponse(array_merge(
            $roundPlan->toArray(),
            ['snapshot_url' => $roundPlan->snapshot_url]
        ));
    }

    /**
     * PATCH /api/round-plans/{roundPlan}
     *
     * Re-save after editing — updates plan data, name, and syncs the tactic.
     */
    public function update(Request $request, RoundPlan $roundPlan): JsonResponse
    {
        if (($gate = $this->ensureRoundPlannerFeature())) {
            return $gate;
        }

        $this->authoriseEdit($roundPlan);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string|max:2000',
            'round_plan' => 'sometimes|string',
            'snapshot' => 'nullable|file|mimes:png,jpg|max:4096',
        ]);

        if (! empty($validated['round_plan'])) {
            $planData = json_decode($validated['round_plan'], true);
            if ($planData) {
                $roundPlan->data = $planData;
            }
        }

        if (isset($validated['name'])) {
            $roundPlan->name = $validated['name'];
            $roundPlan->tactic?->update(['name' => $validated['name']]);
        }

        if (array_key_exists('description', $validated)) {
            $roundPlan->tactic?->update(['description' => $validated['description'] ?? '']);
        }

        if ($request->hasFile('snapshot')) {
            try {
                $roundPlan->snapshot_url = $this->uploadSnapshotToBunny(
                    $request->file('snapshot'),
                    $roundPlan->id
                );
            } catch (\Exception $e) {
                \Log::warning('Round plan snapshot update failed: '.$e->getMessage());
            }
        }

        $roundPlan->save();

        return new JsonResponse(array_merge(
            $roundPlan->toArray(),
            ['snapshot_url' => $roundPlan->snapshot_url]
        ));
    }

    /**
     * DELETE /api/round-plans/{roundPlan}
     */
    public function destroy(RoundPlan $roundPlan): JsonResponse
    {
        $this->authoriseEdit($roundPlan);

        // Bunny snapshots are overwritten by plan ID — no delete needed
        // (old file is replaced on next save, orphans are acceptable)
        $roundPlan->delete();

        return new JsonResponse(['message' => 'Deleted'], 200);
    }

    /**
     * POST /api/round-plans/{roundPlan}/share
     *
     * Enable public read-only sharing. Generates a fresh UUID token and
     * returns the public URL. Revokes all prior links (token rotates).
     */
    public function enableShare(RoundPlan $roundPlan): JsonResponse
    {
        $this->authoriseEdit($roundPlan);

        $roundPlan->share_token = (string) \Illuminate\Support\Str::uuid();
        $roundPlan->share_enabled = true;
        $roundPlan->save();

        return new JsonResponse([
            'share_enabled' => true,
            'share_token' => $roundPlan->share_token,
            'share_url' => url('/plan/'.$roundPlan->share_token),
        ]);
    }

    /**
     * DELETE /api/round-plans/{roundPlan}/share
     *
     * Disable sharing. Clears the token so every prior link 404s immediately.
     */
    public function disableShare(RoundPlan $roundPlan): JsonResponse
    {
        $this->authoriseEdit($roundPlan);

        $roundPlan->share_token = null;
        $roundPlan->share_enabled = false;
        $roundPlan->save();

        return new JsonResponse(['share_enabled' => false]);
    }

    /**
     * GET /api/public/round-plans/{token}
     *
     * Public, unauthenticated read of a shared plan. Returns only the fields
     * the read-only viewer needs — no team ids, no user ids, no tactic link.
     * 404 when the token is missing or sharing is disabled.
     */
    public function publicShow(string $token): JsonResponse
    {
        $plan = RoundPlan::where('share_token', $token)
            ->where('share_enabled', true)
            ->first();

        if (! $plan) {
            return new JsonResponse(['message' => 'Plan not found or sharing disabled'], 404);
        }

        return new JsonResponse([
            'name' => $plan->name,
            'map' => $plan->map,
            'side' => $plan->side,
            'data' => $plan->data,
            'snapshot_url' => $plan->snapshot_url,
            'shared_at' => $plan->updated_at?->toIso8601String(),
        ]);
    }

    // ─── Auth helpers ──────────────────────────────────────────────────────────

    private function authoriseView(RoundPlan $plan): void
    {
        $userId = Auth::id();
        $isMember = $plan->team?->roles()->where('user_id', $userId)->exists()
            || $plan->team?->owned_by === $userId;
        if (! $isMember) {
            abort(403);
        }
    }

    private function authoriseEdit(RoundPlan $plan): void
    {
        $userId = Auth::id();
        $team = $plan->team;
        $role = $team?->roles->where('user_id', $userId)->first()?->role ?? 'Player';
        $allowed = in_array($role, ['IGL', 'Coach', 'Owner', 'Captain'])
            || ($team && $team->owned_by === $userId)
            || ($team && in_array($userId, $team->edit_users ?? []));
        if (! $allowed) {
            abort(403);
        }
    }

    /**
     * Tier gate for the Round Planner (Strat Builder) — a Team+ feature.
     * Returns a 403 with PLAN_LIMIT_EXCEEDED (which the frontend turns into
     * the upgrade modal) when the acting user's plan doesn't include it, or
     * null when the action may proceed. Resolved via PlanService so admins
     * who switch themselves to Starter hit the same gate a real user would.
     */
    private function ensureRoundPlannerFeature(): ?JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        $plans = app(PlanService::class);
        if ($plans->can($user, 'round_planner')) {
            return null;
        }

        return new JsonResponse([
            'app_code' => AppErrorCode::PLAN_LIMIT_EXCEEDED,
            'feature' => 'round_planner',
            'required_tier' => $plans->requiredTierForFeature('round_planner'),
            'message' => 'The Strat Builder (Round Planner) is a Team feature. Upgrade to Team or Pro to create and save round plans.',
        ], 403);
    }
}
