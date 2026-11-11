<?php

namespace App\Http\Middleware;

use App\Core\Repositories\TeamRepository;
use Closure;
use Illuminate\Http\Request;

/**
 * Ensures an authenticated user owns at least one team before the SPA shell
 * loads. The team record is created regardless of subscription: paid access
 * is still gated by CheckPermissions on the API layer. The check is
 * session-cached so it only runs once per session, not on every SPA route
 * (which all resolve through the same /{vue?} catch-all).
 */
class EnsureUserHasTeam
{
    public function __construct(
        private TeamRepository $teamRepository
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Check if the user actually has a team — don't rely on a session
        // flag, because if team creation failed on the first request the
        // flag would be set and the user would never get a team.
        if (session('ensure_team_done')) {
            // Even if the session says done, verify the team exists
            if (! \App\Models\Team::where('owned_by', $user->id)->exists()) {
                session()->forget('ensure_team_done');
            } else {
                return $next($request);
            }
        }

        $this->teamRepository->ensureUserHasTeam($user);

        session(['ensure_team_done' => true]);

        return $next($request);
    }
}
