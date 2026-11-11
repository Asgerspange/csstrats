<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\UserInfo;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TeamInviteController extends Controller
{
    // Roles that can be selected by someone joining via invite link
    // Owner and Coach are excluded — they must be assigned manually
    const JOINABLE_ROLES = ['IGL', 'Entry', 'AWP', 'Support', 'Lurker', 'Player', 'Stand-in'];

    /**
     * Generate or regenerate an invite token for a team.
     * Only team owner / coach / IGL can generate links.
     *
     * POST /api/teams/{team}/invite/generate
     */
    public function generate(Team $team): JsonResponse
    {
        $user = Auth::user();
        $role = $user->userRole->firstWhere('team_id', $team->id)?->role;

        if ($team->owned_by !== $user->id && !in_array($role, ['Owner', 'Coach', 'IGL'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $token = Str::random(32);
        $team->update(['invite_token' => $token]);

        return response()->json([
            'token' => $token,
            'url'   => url("/invite/{$token}"),
        ]);
    }

    /**
     * Revoke invite token (generate a new one, invalidating old links).
     *
     * POST /api/teams/{team}/invite/revoke
     */
    public function revoke(Team $team): JsonResponse
    {
        $user = Auth::user();
        $role = $user->userRole->firstWhere('team_id', $team->id)?->role;

        if ($team->owned_by !== $user->id && !in_array($role, ['Owner', 'Coach'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $token = Str::random(32);

        $team->update(['invite_token' => $token]);;

        return response()->json(['message' => 'Invite link revoked. New link generated.']);
    }

    /**
     * Get current invite link for a team.
     *
     * GET /api/teams/{team}/invite
     */
    public function getLink(Team $team): JsonResponse
    {
        $user = Auth::user();
        $role = $user->userRole->firstWhere('team_id', $team->id)?->role;

        if ($team->owned_by !== $user->id && !in_array($role, ['Owner', 'Coach', 'IGL'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        // Generate token if missing
        if (!$team->invite_token) {
            $token = Str::random(32);
            $team->update(['invite_token' => $token]);
        }

        return response()->json([
            'token' => $team->invite_token,
            'url'   => url("/invite/{$team->invite_token}"),
        ]);
    }

    /**
     * Show the invite landing page.
     * Serves the Vue SPA with invite context injected.
     * No auth required — guest users will be shown login/register.
     *
     * GET /invite/{token}
     */
    public function show(string $token)
    {
        $team = Team::where('invite_token', $token)
            ->withCount('users')
            ->with(['owner:id,name', 'logo'])
            ->first();

        if (!$team) {
            // Render the app with an error flag — Vue handles the UI
            return view('app', [
                'invite_error' => 'invalid',
            ]);
        }

        $owner = $team->owner;
        $maxMembers = $this->getMaxMembers($team);

        // Pass invite data into the page for Vue to read from window
        return view('invite', [
            'team'        => [
                'id'          => $team->id,
                'name'        => $team->name,
                'owner_name'  => $owner?->name ?? 'Unknown',
                'member_count'=> $team->users_count,
                'max_members' => $maxMembers,
                'is_full'     => $team->users_count >= $maxMembers,
                'logo'        => $team->logo?->base64 ?? null,
            ],
            'token'       => $token,
            'roles'       => self::JOINABLE_ROLES,
            'is_auth'     => Auth::check(),
            'already_member' => Auth::check()
                ? TeamMember::where('user_id', Auth::id())
                    ->where('team_id', $team->id)->exists()
                : false,
        ]);
    }

    /**
     * Accept an invite and join the team with a selected role.
     * User must be authenticated.
     *
     * POST /api/invite/{token}/accept
     */
    public function accept(Request $request, string $token): JsonResponse
    {
        $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', self::JOINABLE_ROLES)],
        ]);

        $team = Team::where('invite_token', $token)->first();

        if (!$team) {
            return response()->json(['message' => 'Invalid invite link'], 404);
        }

        $user = Auth::user();

        // Already a member?
        if (TeamMember::where('user_id', $user->id)->where('team_id', $team->id)->exists()) {
            return response()->json(['message' => 'already_member', 'team_id' => $team->id], 409);
        }

        // Team full?
        $maxMembers = $this->getMaxMembers($team);
        if ($team->users()->count() >= $maxMembers) {
            return response()->json(['message' => 'Team is full'], 403);
        }

        // Ensure UserInfo exists
        UserInfo::firstOrCreate(['uuid' => $user->uuid], ['uuid' => $user->uuid]);

        // Create membership
        TeamMember::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
        ]);

        // Create role
        UserRole::firstOrCreate(
            ['user_id' => $user->id, 'team_id' => $team->id],
            ['role' => $request->role]
        );

        return response()->json([
            'message' => 'Joined successfully',
            'team_id' => $team->id,
        ]);
    }

    private function getMaxMembers(Team $team): int
    {
        $owner = $team->owner;
        if (!$owner) return 7;

        $granted = $owner->grantedAccess()->value('max_members');
        if (!is_null($granted)) return $granted;

        $paddle = $owner->userLimit?->max_members;
        if (!is_null($paddle)) return $paddle;

        $product = $owner->product();
        if ($product && isset($product->max_members)) {
            return $product->max_members ?? 7;
        }

        return 7;
    }
}