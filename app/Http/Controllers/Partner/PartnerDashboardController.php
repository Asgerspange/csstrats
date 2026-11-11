<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\ReferralVisit;
use App\Models\User;
use App\Services\ExchangeRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The partner-facing analytics dashboard: visits, signups, subscriptions, and
 * earnings for the logged-in referrer only.
 */
class PartnerDashboardController extends Controller
{
    public function __construct(private ExchangeRateService $fx)
    {
    }

    public function index()
    {
        return view('partner.app');
    }

    public function data(): JsonResponse
    {
        $referrer = Auth::guard('referrer')->user();
        $sourceIds = $referrer->sources()->pluck('id');

        return response()->json([
            'referrer' => [
                'name' => $referrer->name,
                'commission_rate' => (float) $referrer->commission_rate,
                'commission_type' => $referrer->commission_type,
                'recurring_months' => $referrer->recurring_months,
            ],
            'sources' => $referrer->sources()->get(['id', 'code', 'label']),
            'summary' => $this->summary($referrer, $sourceIds),
            'earnings' => [
                'byCurrency' => $this->earningsByCurrency($referrer),
            ],
            // EUR-base rates so the client can convert to any display currency.
            'fx' => ['base' => 'EUR', 'rates' => $this->fx->rates()],
            'visitsByDay' => $this->visitsByDay($sourceIds),
            'recentSignups' => $this->recentSignups($sourceIds),
            'recentCommissions' => $this->recentCommissions($referrer),
        ]);
    }

    private function summary($referrer, $sourceIds): array
    {
        // Currency-agnostic counts. Money lives in earnings.byCurrency.
        return [
            'visits' => ReferralVisit::whereIn('referral_source_id', $sourceIds)->count(),
            'unique_visitors' => ReferralVisit::whereIn('referral_source_id', $sourceIds)
                ->distinct('visitor_id')->count('visitor_id'),
            'signups' => User::whereIn('referral_source_id', $sourceIds)->count(),
            'paying_customers' => ReferralCommission::where('referrer_id', $referrer->id)
                ->distinct('user_id')->count('user_id'),
            'orders' => ReferralCommission::where('referrer_id', $referrer->id)->count(),
        ];
    }

    private function earningsByCurrency($referrer): array
    {
        return ReferralCommission::where('referrer_id', $referrer->id)
            ->selectRaw('currency, COUNT(*) as orders, SUM(order_total) as gross, SUM(commission_amount) as earned')
            ->groupBy('currency')
            ->orderByDesc('earned')
            ->get()
            ->map(fn ($r) => [
                'currency' => $r->currency,
                'orders' => (int) $r->orders,
                'gross' => round((float) $r->gross, 2),
                'earned' => round((float) $r->earned, 2),
            ])
            ->all();
    }

    private function visitsByDay($sourceIds): array
    {
        $since = now()->subDays(29)->startOfDay();

        $rows = ReferralVisit::whereIn('referral_source_id', $sourceIds)
            ->where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->pluck('count', 'day');

        $series = [];
        for ($i = 0; $i < 30; $i++) {
            $day = $since->copy()->addDays($i)->toDateString();
            $series[] = ['day' => $day, 'count' => (int) ($rows[$day] ?? 0)];
        }

        return $series;
    }

    private function recentSignups($sourceIds): array
    {
        return User::whereIn('referral_source_id', $sourceIds)
            ->latest('referred_at')
            ->limit(50)
            ->get(['id', 'name', 'referred_at', 'created_at'])
            ->map(fn (User $u) => [
                // Partners see signup activity, never the customer's email/PII.
                'initials' => $this->initials($u->name),
                'referred_at' => $u->referred_at,
            ])
            ->all();
    }

    private function recentCommissions($referrer): array
    {
        return ReferralCommission::where('referrer_id', $referrer->id)
            ->latest('billed_at')
            ->limit(50)
            ->get(['id', 'order_total', 'currency', 'commission_rate', 'commission_amount', 'billed_at'])
            ->all();
    }

    private function initials(?string $name): string
    {
        if (! $name) {
            return '??';
        }

        $parts = preg_split('/\s+/', trim($name));
        $initials = collect($parts)->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

        return mb_strtoupper($initials ?: '?');
    }
}
