<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateDevMiddleware
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
        // Asger, Valdemar, Mathias, Hjalmar og Silas
        $allowed_ids = [18, 19, 262, 532, 740];
        if (Auth::check()) {
            if (in_array(Auth::user()->id, $allowed_ids)) {
                return $next($request);
            }
        }

        return redirect('/admin/dashboard/stalker');
    }
}