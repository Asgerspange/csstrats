<?php

namespace App\Http\Middleware;

use App\Services\ReferralService;
use Closure;
use Illuminate\Http\Request;

/**
 * Captures ?utm_source=<code> on public marketing pages: records the visit and
 * queues attribution cookies so a later signup can be credited. No-op when the
 * utm_source does not match a known referral source.
 */
class CaptureReferral
{
    public function __construct(private ReferralService $referrals)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && $request->query('utm_source')) {
            $source = $this->referrals->resolveSourceByCode($request->query('utm_source'));
            if ($source) {
                $this->referrals->capture($source, $request);
            }
        }

        return $next($request);
    }
}
