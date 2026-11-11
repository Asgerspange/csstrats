<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Stratbook;
use App\Models\Team;

class StratbookMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        try {
            $user = app('user');
        } catch (\Exception $e) {
            return redirect('/login');
        }

        $teams = $user->teams;
        $stratbookFound = false;

        foreach ($teams as $team) {
            $stratbook = Stratbook::where('team_id', $team->id)
                ->where('id', $request->route('id'))
                ->first();
            
            if ($stratbook) {
                $stratbookFound = true;
                break;
            }
        }

        if (!$stratbookFound) {
            return redirect('/tactics');
        }
    
        return $next($request);
    }
}