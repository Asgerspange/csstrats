<?php

namespace App\Http\Controllers;

use App\Mail\TeamInvite as TeamInviteMail;
use App\Models\{Team, TeamInvitation, TeamMember, User, UserInfo, UserRole, MailListing};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Auth, Hash, Mail};
use Illuminate\Support\Str;

class TeamMemberInvitationController extends Controller
{
    private const JOINABLE_ROLES = ['Coach', 'IGL', 'Entry', 'AWP', 'Support', 'Lurker', 'Player', 'Stand-in'];

    private const INVITE_TTL_DAYS = 14;

    // ── List pending invitations for a team ──────────────────────────────
    public function index(Request $request, int $teamId): JsonResponse
    {
        $team = $this->guard($teamId);

        $invites = TeamInvitation::where('team_id', $team->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->with('inviter:id,name')
            ->get()
            ->map(fn ($i) => [
                'id'         => $i->id,
                'email'      => $i->email,
                'role'       => $i->role,
                'inviter'    => $i->inviter?->name,
                'created_at' => $i->created_at?->toIso8601String(),
                'expires_at' => $i->expires_at?->toIso8601String(),
                'expired'    => $i->expires_at && $i->expires_at->isPast(),
            ]);

        return new JsonResponse(['invitations' => $invites]);
    }

    // ── Send a new invite ────────────────────────────────────────────────
    public function store(Request $request, int $teamId): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email:rfc',
            'role'  => 'nullable|string',
        ]);

        $team = $this->guard($teamId, requireAdmin: true);
        $email = strtolower(trim($data['email']));
        $role  = $data['role'] ?? 'Player';

        if (!in_array($role, self::JOINABLE_ROLES, true)) {
            return new JsonResponse(['message' => 'Invalid role for invitation.'], 422);
        }

        $owner = User::find($team->owned_by);
        if (!$owner) {
            return new JsonResponse(['message' => 'Team owner missing.'], 500);
        }

        // Team-size cap (pending invites count toward the limit)
        $memberCount  = TeamMember::where('team_id', $team->id)->count();
        $pendingCount = TeamInvitation::where('team_id', $team->id)->where('status', 'pending')->count();
        $maxMembers   = $this->getMaxMembers($owner);
        if ($maxMembers > 0 && ($memberCount + $pendingCount) >= $maxMembers) {
            return new JsonResponse(['message' => 'Team is full. Cannot send more invites.'], 403);
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $alreadyMember = TeamMember::where('team_id', $team->id)
                ->where('user_id', $existingUser->id)
                ->exists();
            if ($alreadyMember) {
                return new JsonResponse(['message' => 'User is already a member of this team.'], 409);
            }
        }

        $duplicatePending = TeamInvitation::where('team_id', $team->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();
        if ($duplicatePending) {
            return new JsonResponse(['message' => 'A pending invite already exists for this email.'], 409);
        }

        $accountCreated = false;
        $tempPassword   = null;
        if (!$existingUser) {
            $tempPassword = Str::random(10);
            $existingUser = User::create([
                'uuid'              => (string) Str::uuid(),
                'name'              => explode('@', $email)[0],
                'email'             => $email,
                'email_verified_at' => now(),
                'password'          => Hash::make($tempPassword),
                'auth_token'        => bin2hex(openssl_random_pseudo_bytes(30)),
            ]);
            UserInfo::firstOrCreate(['uuid' => $existingUser->uuid]);
            MailListing::firstOrCreate(['user_id' => $existingUser->id], ['news' => 1]);
            $accountCreated = true;
        }

        $invitation = TeamInvitation::create([
            'team_id'         => $team->id,
            'email'           => $email,
            'invited_by'      => Auth::id(),
            'user_id'         => $existingUser?->id,
            'role'            => $role,
            'token'           => bin2hex(random_bytes(24)),
            'status'          => 'pending',
            'expires_at'      => now()->addDays(self::INVITE_TTL_DAYS),
            'account_created' => $accountCreated,
        ]);

        try {
            Mail::to($email)->send(new TeamInviteMail(
                $invitation,
                $team,
                Auth::user(),
                $tempPassword,
                $accountCreated,
            ));
        } catch (\Throwable $e) {
            report($e);
            // Don't fail the whole flow; invite still exists and link can be re-sent.
        }

        return new JsonResponse([
            'message'    => 'Invitation sent.',
            'invitation' => [
                'id'         => $invitation->id,
                'email'      => $invitation->email,
                'role'       => $invitation->role,
                'created_at' => $invitation->created_at?->toIso8601String(),
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ],
            'account_created' => $accountCreated,
        ], 201);
    }

    // ── Revoke a pending invite ─────────────────────────────────────────
    public function revoke(Request $request, int $teamId, int $inviteId): JsonResponse
    {
        $team = $this->guard($teamId, requireAdmin: true);

        $invitation = TeamInvitation::where('team_id', $team->id)->where('id', $inviteId)->first();
        if (!$invitation) {
            return new JsonResponse(['message' => 'Invitation not found.'], 404);
        }
        if ($invitation->status !== 'pending') {
            return new JsonResponse(['message' => 'Invitation already finalized.'], 409);
        }
        $invitation->update(['status' => 'revoked']);

        return new JsonResponse(['message' => 'Invitation revoked.']);
    }

    // ── Public: fetch invite details by token (used by accept landing page) ─
    public function show(string $token): JsonResponse
    {
        $invitation = TeamInvitation::where('token', $token)->with('team:id,name', 'inviter:id,name')->first();
        if (!$invitation) {
            return new JsonResponse(['message' => 'Invitation not found.'], 404);
        }

        return new JsonResponse([
            'team_name'  => $invitation->team?->name,
            'inviter'    => $invitation->inviter?->name,
            'email'      => $invitation->email,
            'role'       => $invitation->role,
            'status'     => $invitation->status,
            'expired'    => $invitation->expires_at && $invitation->expires_at->isPast(),
            'expires_at' => $invitation->expires_at?->toIso8601String(),
        ]);
    }

    // ── Authenticated: accept invite ────────────────────────────────────
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = TeamInvitation::where('token', $token)->first();
        if (!$invitation) {
            return new JsonResponse(['message' => 'Invitation not found.'], 404);
        }
        if ($invitation->status !== 'pending') {
            return new JsonResponse(['message' => 'Invitation no longer pending.'], 409);
        }
        if ($invitation->expires_at && $invitation->expires_at->isPast()) {
            $invitation->update(['status' => 'expired']);
            return new JsonResponse(['message' => 'Invitation expired.'], 410);
        }

        $user = Auth::user();
        if (!$user) {
            return new JsonResponse(['message' => 'Authentication required.'], 401);
        }
        if (strcasecmp($user->email, $invitation->email) !== 0) {
            return new JsonResponse(['message' => 'This invite is for a different email address.'], 403);
        }

        $team = Team::find($invitation->team_id);
        if (!$team) {
            return new JsonResponse(['message' => 'Team no longer exists.'], 404);
        }

        if (TeamMember::where('team_id', $team->id)->where('user_id', $user->id)->exists()) {
            $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);
            return new JsonResponse(['message' => 'Already a member.', 'team_id' => $team->id]);
        }

        UserInfo::firstOrCreate(['uuid' => $user->uuid]);

        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
        ]);

        UserRole::firstOrCreate(
            ['team_id' => $team->id, 'user_id' => $user->id],
            ['role' => $invitation->role]
        );

        $invitation->update(['status' => 'accepted', 'accepted_at' => now(), 'user_id' => $user->id]);

        return new JsonResponse(['message' => 'Joined team.', 'team_id' => $team->id]);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        $invitation = TeamInvitation::where('token', $token)->first();
        if (!$invitation) {
            return new JsonResponse(['message' => 'Invitation not found.'], 404);
        }
        if ($invitation->status !== 'pending') {
            return new JsonResponse(['message' => 'Invitation no longer pending.'], 409);
        }
        $invitation->update(['status' => 'declined']);
        return new JsonResponse(['message' => 'Invitation declined.']);
    }

    // ── helpers ─────────────────────────────────────────────────────────
    private function guard(int $teamId, bool $requireAdmin = false): Team
    {
        $userTeamIds = Auth::user()->teams->pluck('id')->toArray();
        if (!in_array($teamId, $userTeamIds, true)) {
            abort(403, 'You do not have access to this team.');
        }
        $team = Team::findOrFail($teamId);

        if ($requireAdmin) {
            $role = Auth::user()->userRole->where('team_id', $team->id)->first()?->role;
            $isAdmin = $team->owned_by === Auth::id()
                || in_array($role, ['Owner', 'Coach', 'IGL'], true);
            if (!$isAdmin) {
                abort(403, 'You must be an owner, coach, or IGL to manage invitations.');
            }
        }

        return $team;
    }

    private function getMaxMembers(User $owner): int
    {
        $grantedMax = $owner->grantedAccess()->value('max_members');
        if (!is_null($grantedMax)) return (int) $grantedMax;

        $paddleMax = $owner->userLimit?->max_members;
        if (!is_null($paddleMax)) return (int) $paddleMax;

        $product = $owner->product();
        if ($product && isset($product->max_members)) return (int) $product->max_members;

        return 0;
    }
}
