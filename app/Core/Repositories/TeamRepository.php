<?php

namespace App\Core\Repositories;

use App\Models\MapSelectionAudit;
use App\Models\ProPlayer;
use App\Models\ProTeam;
use App\Models\Stratbook;
use App\Models\StratbookTactic;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserInfo;
use App\Models\UserRole;
use App\Services\CacheService;
use App\Services\MapProvisioner;
use App\Services\MapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeamRepository
{
    private CacheService $cacheService;

    public function __construct()
    {
        $this->cacheService = new CacheService;
    }

    public function index($userId)
    {
        $user = User::with([
            'teams.stratbooks',
            'teams.users.userRole',
        ])->findOrFail($userId);

        $user->teams->each(function ($team) {

            $formattedUsers = $team->users->map(function ($user) use ($team) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->userRole
                        ->firstWhere('team_id', $team->id)?->role,
                ];
            })->values();

            $team->setRelation('users', $formattedUsers);
        });

        return $user->teams;
    }

    public function show(Team $team)
    {
        $team->load(['users.userInfo', 'users.userRole', 'stratbooks', 'owner', 'logo']);

        $team->max_members = $team->owner ? $this->getMaxMembers($team->owner) : 0;

        $formattedUsers = $team->users->map(function ($user) use ($team) {
            $role = $user->userRole->where('team_id', $team->id)->first()?->role;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'steam_id' => $user->userInfo->steam_id ?? null,
                'profile_picture' => '/images/generic.png',
                'role' => $role,
                'faceit_nickname' => $user->faceit_nickname,
            ];
        })->filter(fn ($u) => $u['role'] !== null);

        $owner = $team->owner;
        $formattedOwner = [
            'id' => $owner->id,
            'name' => $owner->name,
            'steam_id' => $owner->userInfo->steam_id ?? null,
            'profile_picture' => '/images/generic.png',
            'faceit_nickname' => $owner->faceit_nickname,
        ];

        return $team->makeHidden(['users', 'owner'])->toArray() +
            ['users' => $formattedUsers, 'owner' => $formattedOwner];
    }

    public function update($name, $teamId)
    {
        $team = Team::find($teamId);

        if (! $this->isAdministrator($team, false)) {
            return response()->json([
                'message' => 'You are not the owner of this team',
            ], 403);
        }

        $team->update([
            'name' => $name,
        ]);

        return [
            'message' => 'Team name updated',
        ];
    }

    public function store(string $name): JsonResponse
    {
        $user = Auth::user();
        $maxTeams = $user->grantedAccess()->value('max_teams')
            ?? $user->userLimit?->max_teams
            ?? $user->product()->max_teams
            ?? 1;

        if (Team::where('owned_by', $user->id)->count() >= $maxTeams) {
            return new JsonResponse([
                'message' => 'You have reached the maximum amount of teams',
            ], 403);
        }

        if (! $user->subscribed() && ! $user->hasGrantedAccess() && ! $user->userLimit) {
            return new JsonResponse([
                'message' => 'You need to be subscribed to create a team',
            ], 403);
        }

        $team = Team::create([
            'name' => mb_convert_encoding($name, 'UTF-8', 'UTF-8'),
            'owned_by' => $user->id,
            'entitlement_mode' => 'map_based',
            'map_lock_expires_at' => now()->addDays(30),
        ]);

        TeamMember::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'role' => 'Owner',
        ]);

        // Default selection: first 4 active maps by ID
        $mapService = app(MapService::class);
        $defaultMapIds = $mapService->defaultMapIds(4);
        $team->selectedMaps()->sync($defaultMapIds);

        // Provision stratbooks for all active maps
        app(MapProvisioner::class)->provision($team);

        // Audit trail
        MapSelectionAudit::create([
            'team_id' => $team->id,
            'map_ids' => $defaultMapIds,
            'selected_by' => $user->id,
            'reason' => 'initial',
        ]);

        return new JsonResponse([
            'message' => 'Team created',
        ]);
    }

    public function delete($id, $command = false)
    {
        $team = Team::find($id);

        if (! $command) {
            if (! $this->isAdministrator($team, false)) {
                return response()->json([
                    'message' => 'You are not the owner of this team',
                ], 403);
            }
        }

        $team->delete();

        return [
            'message' => 'Team deleted',
        ];
    }

    /**
     * All tactic rows across every stratbook in a team, for the cross-stratbook
     * overview on the Team page. One request, client-side filtered. Hard
     * ceiling so a team with thousands of tactics degrades gracefully.
     */
    public function tactics(Team $team): array
    {
        $query = $team->stratbooks();

        // For map_based teams, only include stratbooks on accessible maps
        if ($team->entitlement_mode === 'map_based') {
            $entitlement = app(\App\Services\EntitlementService::class);
            $accessibleIds = $entitlement->accessibleMapIds($team);
            $query->whereNotNull('map_id')->whereIn('map_id', $accessibleIds);
        }

        $stratbookIds = $query->pluck('id');

        if ($stratbookIds->isEmpty()) {
            return [];
        }

        return StratbookTactic::whereIn('stratbook_id', $stratbookIds)
            ->select([
                'id',
                'name',
                'type',
                'map',
                'side',
                'tags',
                'stratbook_id',
                'tactic_id',
                'sort_order',
                'updated_at',
            ])
            ->with('stratbook:id,stratbook_name')
            ->orderBy('stratbook_id')
            ->orderBy('sort_order')
            ->limit(1000)
            ->get()
            ->map(fn ($tactic) => [
                'id' => $tactic->id,
                'name' => $tactic->name,
                'type' => $tactic->type,
                'map' => $tactic->map,
                'side' => $tactic->side,
                'tags' => $tactic->tags,
                'stratbook_id' => $tactic->stratbook_id,
                'stratbook_name' => $tactic->stratbook?->stratbook_name,
                'tactic_id' => $tactic->tactic_id,
                'sort_order' => $tactic->sort_order,
                'updated_at' => $tactic->updated_at,
            ])
            ->toArray();
    }

    /**
     * Ensure a user owns at least one team. Creates a "New team" if the user
     * has none. This is a system action (not user-initiated), so it bypasses
     * the subscription gate and the (now-removed) name uniqueness check.
     * Used by EnsureUserHasTeam middleware and the teams:backfill command.
     *
     * New teams are created as map_based: entitlement_mode = 'map_based',
     * default 4 maps selected (first active maps by ID), 30-day lock,
     * stratbooks auto-provisioned for all active maps.
     */
    public function ensureUserHasTeam(User $user): Team
    {
        $existing = Team::where('owned_by', $user->id)->first();

        if ($existing) {
            return $existing;
        }

        $team = Team::create([
            'name' => mb_convert_encoding($user->name, 'UTF-8', 'UTF-8')."'s team",
            'owned_by' => $user->id,
            'entitlement_mode' => 'map_based',
            'map_lock_expires_at' => now()->addDays(30),
        ]);

        TeamMember::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        UserRole::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'role' => 'Owner',
        ]);

        // Default selection: first 4 active maps by ID
        $mapService = app(MapService::class);
        $defaultMapIds = $mapService->defaultMapIds(4);
        $team->selectedMaps()->sync($defaultMapIds);

        // Provision stratbooks for all active maps
        app(MapProvisioner::class)->provision($team);

        // Audit trail
        MapSelectionAudit::create([
            'team_id' => $team->id,
            'map_ids' => $defaultMapIds,
            'selected_by' => $user->id,
            'reason' => 'initial',
        ]);

        return $team;
    }

    public function leave($id, $teamId)
    {
        $this->setTeamMemberStatus($id, $teamId);
        $this->resetRole($id, $teamId);

        return [
            'message' => 'You have left the team',
        ];
    }

    public function addMember($teamId, $email)
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $team = Team::find($teamId);
        $team->load('users');

        if (! $this->isAdministrator($team)) {
            return response()->json([
                'message' => 'You are not the owner of this team',
            ], 403);
        }

        if (TeamMember::where('user_id', $user->id)->where('team_id', $teamId)->exists()) {
            return response()->json([
                'message' => 'User is already in this team',
            ], 409);
        }

        $owner = User::find($team->owned_by);

        if (count($team->users) >= $this->getMaxMembers($owner)) {
            return response()->json([
                'message' => 'Team is full',
            ], 403);
        }

        UserInfo::where('uuid', $user->uuid)->firstOrCreate([
            'uuid' => $user->uuid,
        ]);

        UserRole::where('team_id', $teamId)->where('user_id', $user->id)->firstOrCreate([
            'team_id' => $teamId,
            'user_id' => $user->id,
            'role' => 'Player',
        ]);

        TeamMember::create([
            'user_id' => $user->id,
            'team_id' => $teamId,
        ]);

        $user->save();

        return [
            $user,
        ];
    }

    public function removeMember($teamId, $userId)
    {
        $team = Team::find($teamId);

        if ($userId == auth()->id() && $team->owned_by != auth()->id()) {
            return $this->leave($userId, $teamId);
        }

        if (! $this->isAdministrator($team, false)) {
            return response()->json([
                'message' => 'You are not the owner of this team',
            ], 403);
        }

        if ($team->owned_by == $userId) {
            return response()->json([
                'message' => 'You cannot kick the team owner',
            ], 403);
        }

        if ($team->owned_by != auth()->id()) {
            $userRole = UserRole::where('user_id', $userId)->where('team_id', $teamId)->first()->role;
            if ($userRole === 'Coach' || $userRole === 'Owner') {
                return response()->json([
                    'message' => 'You cannot kick a coach or an owner',
                ], 403);
            }
        }

        $this->setTeamMemberStatus($userId, $teamId);
        $this->resetRole($userId, $teamId);

        return [
            'message' => 'User kicked',
        ];
    }

    public function updateTags(int $teamId, array $tags)
    {
        $team = Team::find($teamId);
        if (! $this->isAdministrator($team)) {
            return response()->json([
                'message' => 'You are not the owner of this team',
            ], 403);
        }

        $team->setSetting('tags', $tags);
        $team->save();

        return [
            'message' => 'Tags updated',
        ];
    }

    public function updateTypes(int $teamId, array $types)
    {
        $team = Team::find($teamId);
        if (! $this->isAdministrator($team)) {
            return response()->json([
                'message' => 'You are not the owner of this team',
            ], 403);
        }

        $team->setSetting('types', $types);
        $team->save();

        return [
            'message' => 'Types updated',
        ];
    }

    public function updatePermissions(int $teamId, int $userId, string $perm)
    {
        $user = Auth::user();
        $team = Team::find($teamId);

        $currentUserRole = $user->userRole->firstWhere('team_id', $teamId)->role;

        if ($currentUserRole !== 'Coach' && $team->owned_by !== $user->id && $currentUserRole !== 'Owner') {
            return response()->json(['message' => 'You must be a coach to perform this action'], 403);
        }

        $didUpdate = false;
        $permissionMap = [
            'View' => ['remove' => ['edit', 'admin'], 'add' => []],
            'Edit' => ['remove' => ['admin'], 'add' => ['edit']],
            'Edit, Add, Delete' => ['remove' => ['edit'], 'add' => ['admin']],
        ];

        if (isset($permissionMap[$perm])) {
            $config = $permissionMap[$perm];
            $userId = $userId;

            foreach ($config['remove'] as $type) {
                $key = $type.'_users';
                $team->$key = array_values(array_diff($team->$key, [$userId]));
            }

            foreach ($config['add'] as $type) {
                $key = $type.'_users';
                $team->$key = array_unique(array_merge($team->$key, [$userId]));
            }

            $team->save();
            $didUpdate = true;
        }

        if (! $didUpdate) {
            return response()->json(['message' => 'Invalid permission '.$perm], 400);
        }

        return [
            'message' => 'Permissions updated',
        ];
    }

    public function updateRoles($teamId, $userId, $roleToBe)
    {
        $team = Team::find($teamId);

        $user = Auth::user();
        $role = $user->userRole->firstWhere('team_id', $teamId)->role;

        if ($role !== 'Coach' && $role !== 'Owner' && $team->owned_by !== $user->id) {
            return response()->json(['message' => 'You must be a coach to perform this action'], 403);
        }

        $userRole = UserRole::where('user_id', $userId)->where('team_id', $team->id)->first();

        if ($team->owned_by !== $user->id && ($userRole->role === 'Owner' || $userRole->role === 'Coach')) {
            return response()->json(['message' => 'You cannot change the role of the team owner or a coach'], 403);
        }

        $userRole->role = $roleToBe;
        $userRole->save();

        return response()->json(['message' => 'Role updated']);
    }

    private function getMaxMembers($owner)
    {
        $product = $owner?->product();

        $grantedMax = $owner->grantedAccess()->value('max_members');

        if (! is_null($grantedMax)) {
            return $grantedMax;
        }

        $paddleMax = $owner->userLimit?->max_members;
        if (! is_null($paddleMax)) {
            return $paddleMax;
        }

        if ($product && isset($product->max_members)) {
            return $product->max_members ?? 0;
        }

        return 0;
    }

    private function isAdministrator($team, $iglCheck = true)
    {
        $userRoles = auth()->user()?->userRole;
        $role = $userRoles->where('team_id', $team->id)->first()?->role;

        if ($iglCheck) {
            return $team->owned_by == auth()->user()->id || $role == 'Coach' || $role == 'IGL' || $role == 'Owner' || $role == 'Captain';
        }

        return $team->owned_by == auth()->user()->id || $role == 'Coach' || $role == 'Owner';
    }

    private function setTeamMemberStatus($userId, $teamId)
    {
        $teamMember = TeamMember::where('user_id', $userId)->where('team_id', $teamId)->first();
        if ($teamMember) {
            $teamMember->delete();
        }
    }

    private function resetRole($userId, $teamId)
    {
        UserRole::where('user_id', $userId)->where('team_id', $teamId)->first()->delete();
    }

    public function proTeams()
    {
        return $this->cacheService->getProTeams();
    }

    public function storeProTeam($team)
    {
        $proTeam = ProTeam::create([
            'name' => $team['name'],
        ]);

        foreach ($team['players'] as $player) {
            $proTeam->players()->create([
                'team_id' => $proTeam->id,
                'name' => $player,
                'description' => '',
                'timestamp' => '',
                'loadout' => [],
            ]);
        }

        $this->cacheService->refreshProTeamsCache();
    }

    public function updateProTeam($team, $id)
    {
        $proTeam = ProTeam::find((int) $id);

        $proTeam->update([
            'name' => $team['name'],
        ]);

        $proPlayers = ProPlayer::where('team_id', $id)->get();

        foreach ($proPlayers as $index => $proPlayer) {
            $proPlayer->name = $team['players'][$index];
            $proPlayer->save();
        }

        $this->cacheService->refreshProTeamsCache();
    }

    public function destroyProTeam($id)
    {
        $proTeam = ProTeam::find($id);

        $ProPlayers = ProPlayer::where('team_id', $id)->get();
        foreach ($ProPlayers as $proPlayer) {
            $proPlayer->delete();
        }
        $proTeam->delete();
        $this->cacheService->refreshProTeamsCache();
    }
}
