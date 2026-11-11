<?php

namespace App\Services;

use App\Models\ReferralCommission;
use App\Models\ReferralSource;
use App\Models\ReferralVisit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Referral tracking: capture visits + attribution cookies, attribute signups,
 * and record commissions when referred customers pay.
 *
 * Attribution is cookie-based so it survives the OAuth redirect dance:
 *   - cs_vid : anonymous first-party visitor id
 *   - cs_ref : the matched referral_source id
 */
class ReferralService
{
    public const VID_COOKIE = 'cs_vid';
    public const REF_COOKIE = 'cs_ref';
    public const COOKIE_MINUTES = 60 * 24 * 30; // 30 days

    public function resolveSourceByCode(?string $code): ?ReferralSource
    {
        $code = trim(strtolower((string) $code));
        if ($code === '') {
            return null;
        }

        return ReferralSource::where('code', $code)->first();
    }

    /**
     * Record a referred visit and queue the attribution cookies. Safe to call
     * on every marketing page load: visits are deduped per visitor/source/day.
     */
    public function capture(ReferralSource $source, Request $request): void
    {
        $visitorId = $request->cookie(self::VID_COOKIE) ?: (string) Str::uuid();

        // Queue cookies so the next request (and the eventual signup) carries
        // attribution. AddQueuedCookiesToResponse (web group) flushes these.
        Cookie::queue(self::VID_COOKIE, $visitorId, self::COOKIE_MINUTES);
        Cookie::queue(self::REF_COOKIE, (string) $source->id, self::COOKIE_MINUTES);

        $dedupeKey = "ref_visit:{$visitorId}:{$source->id}:" . now()->toDateString();
        if (! Cache::add($dedupeKey, 1, now()->addDay())) {
            return; // already counted this visitor for this source today
        }

        try {
            ReferralVisit::create([
                'referral_source_id' => $source->id,
                'visitor_id' => $visitorId,
                'ip_hash' => $this->hashIp($request->ip()),
                'country_code' => $this->resolveCountry($request->ip()),
                'referrer_url' => Str::limit((string) $request->headers->get('referer'), 500, ''),
                'landing_path' => Str::limit($request->getRequestUri(), 500, ''),
                'utm_medium' => $request->query('utm_medium'),
                'utm_campaign' => $request->query('utm_campaign'),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Referral visit record failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Attach the captured referral to a newly created user (idempotent: never
     * overwrites an existing attribution).
     */
    public function attribute(User $user, Request $request): void
    {
        if ($user->referral_source_id) {
            return;
        }

        $sourceId = (int) $request->cookie(self::REF_COOKIE);
        if (! $sourceId) {
            return;
        }

        if (! ReferralSource::whereKey($sourceId)->exists()) {
            return;
        }

        $user->forceFill([
            'referral_source_id' => $sourceId,
            'referral_visitor_id' => $request->cookie(self::VID_COOKIE),
            'referred_at' => now(),
        ])->save();

        Log::info('Referral attributed', ['user_id' => $user->id, 'source_id' => $sourceId]);
    }

    /**
     * Record a commission for a completed Paddle transaction, if the paying
     * user was referred and the order qualifies. Idempotent per transaction.
     *
     * @param  array  $data  Paddle webhook `data` payload for transaction.completed
     */
    public function recordCommissionForTransaction(array $data, ?User $user): void
    {
        if (! $user || ! $user->referral_source_id) {
            return;
        }

        $transactionId = $data['id'] ?? null;
        if (! $transactionId) {
            return;
        }

        // Already credited (replayed webhook).
        if (ReferralCommission::where('paddle_transaction_id', $transactionId)->exists()) {
            return;
        }

        $source = ReferralSource::with('referrer')->find($user->referral_source_id);
        $referrer = $source?->referrer;
        if (! $referrer || ! $referrer->is_active) {
            return;
        }

        // Paddle totals are strings in the lowest denomination (e.g. "2495").
        $rawTotal = (int) ($data['details']['totals']['total'] ?? 0);
        $orderTotal = round($rawTotal / 100, 2);
        if ($orderTotal <= 0) {
            return;
        }

        if (! $this->qualifies($referrer, $user, $data)) {
            return;
        }

        $rate = (float) $referrer->commission_rate;
        $commission = round($orderTotal * $rate / 100, 2);

        ReferralCommission::create([
            'referrer_id' => $referrer->id,
            'referral_source_id' => $source->id,
            'user_id' => $user->id,
            'paddle_transaction_id' => $transactionId,
            'order_total' => $orderTotal,
            'currency' => $data['currency_code'] ?? 'EUR',
            'commission_rate' => $rate,
            'commission_amount' => $commission,
            'billed_at' => isset($data['billed_at']) ? Carbon::parse($data['billed_at']) : now(),
        ]);

        Log::info('Referral commission recorded', [
            'referrer_id' => $referrer->id,
            'user_id' => $user->id,
            'amount' => $commission,
        ]);
    }

    private function qualifies($referrer, User $user, array $data): bool
    {
        if ($referrer->commission_type === 'first') {
            // Only the customer's first paid order earns.
            return ! ReferralCommission::where('user_id', $user->id)->exists();
        }

        // recurring (optionally capped to N months from signup)
        if ($referrer->recurring_months && $user->referred_at) {
            $billedAt = isset($data['billed_at']) ? Carbon::parse($data['billed_at']) : now();
            $cutoff = $user->referred_at->copy()->addMonths((int) $referrer->recurring_months);

            return $billedAt->lessThanOrEqualTo($cutoff);
        }

        return true;
    }

    private function hashIp(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return hash('sha256', $ip . config('app.key'));
    }

    private function resolveCountry(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        try {
            $position = \Stevebauman\Location\Facades\Location::get($ip);

            return $position->countryCode ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
