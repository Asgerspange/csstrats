<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Currency conversion for referral earnings. Rates are ECB daily reference
 * rates (EUR base) from the keyless frankfurter.app, cached 12h. Conversion
 * is best-effort: callers get null when rates are unavailable and should fall
 * back to showing per-currency totals only.
 */
class ExchangeRateService
{
    private const CACHE_KEY = 'fx:eur:rates';

    /**
     * EUR-base rates: 1 EUR = rates[X] units of X. EUR itself is 1.0.
     * Returns null if rates cannot be fetched (and not yet cached).
     */
    public function rates(): ?array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $res = Http::timeout(5)->get('https://api.frankfurter.app/latest', ['from' => 'EUR']);
            $rates = $res->ok() ? $res->json('rates') : null;

            if (is_array($rates) && $rates !== []) {
                $rates['EUR'] = 1.0;
                // Only cache on success so a transient failure retries soon.
                Cache::put(self::CACHE_KEY, $rates, now()->addHours(12));

                return $rates;
            }
        } catch (\Throwable $e) {
            Log::warning('FX rate fetch failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Convert an amount from one currency to another. Null if either currency
     * is unknown or rates are unavailable.
     */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return round($amount, 2);
        }

        $rates = $this->rates();
        if (! $rates || ! isset($rates[$from], $rates[$to])) {
            return null;
        }

        $inEur = $amount / $rates[$from];

        return round($inEur * $rates[$to], 2);
    }
}
