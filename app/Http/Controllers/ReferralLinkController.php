<?php

namespace App\Http\Controllers;

use App\Services\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Short referral links: GET /r/{code}. Records the visit, sets attribution
 * cookies, then forwards to the landing page (or an allow-listed internal
 * path passed via ?to=).
 */
class ReferralLinkController extends Controller
{
    public function __construct(private ReferralService $referrals)
    {
    }

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $source = $this->referrals->resolveSourceByCode($code);
        if ($source) {
            $this->referrals->capture($source, $request);
        }

        // Only allow internal redirects to avoid an open-redirect.
        $to = (string) $request->query('to', '/');
        if (! str_starts_with($to, '/') || str_starts_with($to, '//')) {
            $to = '/';
        }

        // Preserve the source as a query param so the marketing bundle's
        // existing useReferral.ts personalisation still kicks in.
        $separator = str_contains($to, '?') ? '&' : '?';
        $to .= $separator . 'utm_source=' . urlencode($source?->code ?? $code);

        return redirect()->to($to);
    }
}
