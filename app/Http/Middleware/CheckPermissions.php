<?php

namespace App\Http\Middleware;

use App\Constants\AppErrorCode;
use App\Models\Subscription;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class CheckPermissions
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // if ($user->email === 'celinefrogiz@gmail.com') {
        //     return $next($request);
        // }

        // 1. Basic Auth Check
        if (! $user) {
            return response()->json(['app_code' => AppErrorCode::NO_SUBSCRIPTION], 403);
        }

        // // 2. Immediate Pass for Superusers
        if ($user->is_admin || $user->hasGrantedAccess()) {
            return $next($request);
        }

        // 3. Check Personal Subscription
        // We only pass here if the subscription is active and not past_due
        $subscription = $user->subscriptions()->first();
        $hasValidPaddleSub = $user->hasActivePaddleSubscription();

        $hasValidPersonalSub = $user->subscribed() &&
                            $subscription?->stripe_status === 'active' &&
                            (! $subscription->ends_at || $subscription->ends_at > now());

        // 4. Check Team Membership (using your helper)
        $hasValidTeamSub = $this->getTeamMemberStatus($user);

        // 5. Policy-based Route Checks (Team/Stratbook specific)
        $teamId = $request->route('team');
        if ($teamId && $user->cannot('view', $teamId)) {
            return response()->json(['app_code' => AppErrorCode::NO_TEAM_ACCESS], 403);
        }

        $stratbook = $request->route('stratbook');
        if ($stratbook && $user->cannot('view', $stratbook)) {
            return response()->json(['app_code' => AppErrorCode::NO_STRATBOOK_ACCESS], 403);
        }

        // 6. Final Gate: If they have no personal sub AND no valid team sub, block them

        if (! $hasValidPersonalSub && ! $hasValidTeamSub && ! $hasValidPaddleSub) {
            return response()->json(['app_code' => AppErrorCode::NO_SUBSCRIPTION], 403);
        }

        return $next($request);
    }

    private function getTeamMemberStatus($user)
    {
        $teams = $user->teams;
        foreach ($teams as $team) {
            $ownerId = $team->owned_by;

            // Stripe check
            $subscription = Subscription::where('user_id', $ownerId)->first();
            if ($subscription?->stripe_status === 'active' && (! $subscription->ends_at || $subscription->ends_at > now())) {
                return true;
            }

            $owner = User::find($ownerId);
            if (! $owner) {
                continue;
            }

            // Paddle check
            if ($owner->hasActivePaddleSubscription()) {
                return true;
            }

            // GrantedAccess check
            if ($owner->hasGrantedAccess()) {
                return true;
            }
        }

        return false;
    }
}
