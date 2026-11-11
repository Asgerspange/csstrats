<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TrackUserActivity
{
    public function handle($request, Closure $next)
    {
        if (Auth::check()) {
            $key = 'user-last-activity:' . Auth::id();

            if (!Cache::has($key)) {
                Auth::user()->userInfo()->update(['last_online' => now()]);
                Cache::put($key, true, now()->addMinutes(5));
            }
        }
        
        return $next($request);
    }
}