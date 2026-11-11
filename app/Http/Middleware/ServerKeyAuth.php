<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ServerKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Server-Key', $request->input('callback_key'));
        $expected = config('services.subtick_record.callback_key');

        if (! $expected || ! $key || ! hash_equals($expected, $key)) {
            return response()->json(['message' => 'Invalid server key.'], 401);
        }

        return $next($request);
    }
}
