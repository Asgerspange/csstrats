<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Cashier\Subscription;
use Illuminate\Support\Facades\Cache;

class VerifyPayment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $user = app('user');
        } catch (\Exception $e) {
            return redirect('/login');
        }

        if ($user) {
            if ($user->hasGrantedAccess()) {
                return $next($request);
            }

            if ($this->getTeamMemberStatus($user)) {
                return $next($request);
            }
            
            if ($user->is_admin) {
                return $next($request);
            }

            if ($user->subscriptions()->first()?->stripe_status === 'past_due') {
                return redirect('/past-due');
            }

            if ($user?->subscription()->ends_at && $user?->subscription()->ends_at <= now()) {
                return redirect('/#pricing');
            }

            if (!$user?->subscribed()) {
                return redirect('/#pricing');
            }
        }

        return $next($request);
    }

    private function getTeamMemberStatus($user)
    {
        $teams = $user->teams;
        foreach ($teams as $team) {
            $owner = $team->owned_by;

            $subscription = Subscription::where('user_id', $owner)->first();
            if ($subscription?->stripe_status === 'active' && (!$subscription->ends_at || $subscription->ends_at > now())) {
                return true;
            }

            if ($owner = User::find($owner)) {
                if ($owner->hasGrantedAccess()) {
                    return true;
                }
            }
        }

        return false;
    }
}