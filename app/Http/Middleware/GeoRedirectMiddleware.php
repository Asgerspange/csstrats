<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;

class GeoRedirectMiddleware
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
        // Skip if already on the US domain
        if ($request->getHost() === 'us.csstrats.org') {
            \Log::info('US Hit with IP: ' . $request->ip());
            return $next($request);
        }
        
        // Get location from IP
        $position = Location::get($request->ip());

        // Check if user is from US or Canada
        if ($position && in_array($position->countryCode, ['US', 'CA'])) {

            $uri = $request->getRequestUri();

            if (strpos($uri, 'us.csstrats.org') !== false) {
                return $next($request);
            }

            $redirectUrl = 'https://us.csstrats.org' . $uri;
            
            // Redirect to US-specific domain
            return redirect()->to($redirectUrl);
        }
        
        return $next($request);
    }
}